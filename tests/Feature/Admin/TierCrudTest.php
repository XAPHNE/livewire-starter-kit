<?php

use App\Models\User;
use App\Models\Tier;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Livewire;
use App\Livewire\Tables\TierTable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $permissions = ["Create Tiers", "Edit Tiers", "Delete Tiers", "View Tiers"];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }
    $this->adminRole = Role::findOrCreate("Admin");
    $this->adminRole->syncPermissions($permissions);
    $this->adminUser = User::factory()->withoutTwoFactor()->create();
    $this->adminUser->assignRole($this->adminRole);
    
    // Ensure setting is enabled
    \App\Models\Setting::set("enable_tier_based_concurrency", true);
});

test("admins can access the tier management page", function () {
    $response = $this->actingAs($this->adminUser)->get(route("tiers"));
    $response->assertOk();
    $response->assertSee("Tier Table");
});

test("admins can create a new tier", function () {
    $this->actingAs($this->adminUser);

    Livewire::test("pages::admin.tiers")
        ->call("create")
        ->set("name", "Extra Tier")
        ->set("description", "A very cool tier")
        ->set("concurrent_sessions", 5)
        ->call("save")
        ->assertHasNoErrors()
        ->assertDispatched("pg:eventRefresh-tierTable")
        ->assertDispatched("modal-close", name: "create-update-tier");

    $this->assertDatabaseHas("tiers", [
        "name" => "Extra Tier",
        "description" => "A very cool tier",
        "concurrent_sessions" => 5,
        "created_by" => $this->adminUser->id,
    ]);
});

test("admins can update an existing tier", function () {
    $this->actingAs($this->adminUser);
    $tier = Tier::create(["name" => "Old Tier", "description" => "Old Desc"]);

    Livewire::test("pages::admin.tiers")
        ->call("edit", $tier->id)
        ->assertSet("name", "Old Tier")
        ->set("name", "New Tier Name")
        ->set("description", "New Desc")
        ->call("save")
        ->assertHasNoErrors()
        ->assertDispatched("pg:eventRefresh-tierTable")
        ->assertDispatched("modal-close", name: "create-update-tier");

    $tier->refresh();
    expect($tier->name)->toBe("New Tier Name");
    expect($tier->updated_by)->toBe($this->adminUser->id);
});

test("it validates required fields for tiers", function () {
    $this->actingAs($this->adminUser);

    Livewire::test("pages::admin.tiers")
        ->call("create")
        ->set("name", "")
        ->call("save")
        ->assertHasErrors(["name" => "required"]);
});

test("it validates name uniqueness for tiers", function () {
    $this->actingAs($this->adminUser);
    Tier::create(["name" => "Existing Tier"]);

    Livewire::test("pages::admin.tiers")
        ->call("create")
        ->set("name", "Existing Tier")
        ->call("save")
        ->assertHasErrors(["name" => "unique"]);
});

test("admins can delete a tier", function () {
    $this->actingAs($this->adminUser);
    $tier = Tier::create(["name" => "Tier to Delete"]);

    Livewire::test("pages::admin.tiers")
        ->call("delete", $tier->id)
        ->assertDispatched("modal-show", name: "delete-tier")
        ->call("deleteConfirmed")
        ->assertDispatched("pg:eventRefresh-tierTable")
        ->assertDispatched("modal-close", name: "delete-tier");

    $this->assertSoftDeleted("tiers", ["id" => $tier->id]);
    $tier->refresh();
    expect($tier->deleted_by)->toBe($this->adminUser->id);
});

test("admins can bulk delete tiers", function () {
    $this->actingAs($this->adminUser);
    $tiers = collect([
        Tier::create(["name" => "Tier 1"]),
        Tier::create(["name" => "Tier 2"]),
        Tier::create(["name" => "Tier 3"]),
    ]);
    $ids = $tiers->pluck("id")->toArray();

    Livewire::test("pages::admin.tiers")
        ->call("bulkDelete", $ids)
        ->assertDispatched("modal-show", name: "bulk-delete-tiers")
        ->call("bulkDeleteConfirmed")
        ->assertDispatched("pg:eventRefresh-tierTable")
        ->assertDispatched("modal-close", name: "bulk-delete-tiers");

    foreach ($ids as $id) {
        $this->assertSoftDeleted("tiers", ["id" => $id]);
        $deletedTier = Tier::withTrashed()->find($id);
        expect($deletedTier->deleted_by)->toBe($this->adminUser->id);
    }
});

test("it can search for tiers in the table", function () {
    $this->actingAs($this->adminUser);
    Tier::create(["name" => "UniqueSearchName"]);
    Tier::create(["name" => "SomethingElse"]);

    Livewire::test(TierTable::class)
        ->set("search", "UniqueSearchName")
        ->assertSee("UniqueSearchName")
        ->assertDontSee("SomethingElse");
});

test("users without permission cannot see tiers", function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route("tiers"));
    $response->assertStatus(403);
});

test("users with view only permission cannot create tiers", function () {
    $user = User::factory()->create();
    $role = Role::findOrCreate("ViewOnlyTiers");
    $role->syncPermissions(["View Tiers"]);
    $user->assignRole($role);

    $this->actingAs($user);

    Livewire::test("pages::admin.tiers")
        ->call("create")
        ->assertForbidden();
});
