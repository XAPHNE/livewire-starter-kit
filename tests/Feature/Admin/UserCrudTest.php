<?php

use App\Models\User;
use App\Models\Tier;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Livewire;
use Illuminate\Support\Facades\Hash;
use App\Livewire\Tables\UserTable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    $permissions = ["Create Users", "Edit Users", "Delete Users", "View Users"];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }
    $this->adminRole = Role::findOrCreate("Admin");
    $this->adminRole->syncPermissions($permissions);
    $this->adminUser = User::factory()->withoutTwoFactor()->create();
    $this->adminUser->assignRole($this->adminRole);
});

test("super admins can access the user management page", function () {
    $response = $this->actingAs($this->adminUser)->get(route("users"));
    $response->assertOk();
    $response->assertSee("User Table");
});

test("admins can create a new user", function () {
    $this->actingAs($this->adminUser);

    Livewire::test("pages::admin.user")
        ->call("create")
        ->set("name", "John Doe")
        ->set("email", "john@example.com")
        ->set("password", "Password123!")
        ->set("password_confirmation", "Password123!")
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasNoErrors()
        ->assertDispatched("pg:eventRefresh-userTable")
        ->assertDispatched("modal-close", name: "create-update-user");

    $this->assertDatabaseHas("users", [
        "name" => "John Doe",
        "email" => "john@example.com",
    ]);

    $user = User::where("email", "john@example.com")->first();
    expect($user->hasRole("Admin"))->toBeTrue();
});

test("it tracks the user who created the record", function () {
    $this->actingAs($this->adminUser);

    Livewire::test("pages::admin.user")
        ->call("create")
        ->set("name", "Audit User")
        ->set("email", "audit@example.com")
        ->set("password", "Password123!")
        ->set("password_confirmation", "Password123!")
        ->set("selectedRoles", ["Admin"])
        ->call("save");

    $user = User::where("email", "audit@example.com")->first();
    expect($user->created_by)->toBe($this->adminUser->id);
    expect($user->updated_by)->toBe($this->adminUser->id);
});

test("admins can update an existing user", function () {
    $this->actingAs($this->adminUser);
    $user = User::factory()->create(["name" => "Old Name"]);

    Livewire::test("pages::admin.user")
        ->call("edit", $user->id)
        ->assertSet("name", "Old Name")
        ->set("name", "New Name")
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasNoErrors()
        ->assertDispatched("pg:eventRefresh-userTable")
        ->assertDispatched("modal-close", name: "create-update-user");

    $user->refresh();
    expect($user->name)->toBe("New Name");
    expect($user->updated_by)->toBe($this->adminUser->id);
});

test("it persists tier associations", function () {
    $this->actingAs($this->adminUser);
    $tier = Tier::create(["name" => "Test Tier", "concurrent_sessions" => 1]);
    $user = User::factory()->create();

    // Mock setting enabled
    \App\Models\Setting::set("enable_tier_based_concurrency", true);

    Livewire::test("pages::admin.user")
        ->call("edit", $user->id)
        ->set("selectedTiers", [$tier->id])
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasNoErrors();

    expect($user->refresh()->tiers->pluck("id")->toArray())->toContain($tier->id);
});

test("it can unlock a locked user account", function () {
    $this->actingAs($this->adminUser);
    $user = User::factory()->create([
        "login_locked_until" => Carbon::now()->addHour(),
        "login_attempts" => 5
    ]);

    Livewire::test("pages::admin.user")
        ->call("edit", $user->id)
        ->assertSet("is_login_locked", true)
        ->set("unlock_login", true)
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->login_locked_until)->toBeNull();
    expect($user->login_attempts)->toBe(0);
});

test("it can unlock a locked 2FA", function () {
    $this->actingAs($this->adminUser);
    $user = User::factory()->create([
        "two_factor_locked_until" => Carbon::now()->addHour(),
        "two_factor_resend_count" => 3
    ]);

    Livewire::test("pages::admin.user")
        ->call("edit", $user->id)
        ->assertSet("is_tfa_locked", true)
        ->set("unlock_tfa", true)
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->two_factor_locked_until)->toBeNull();
    expect($user->two_factor_resend_count)->toBe(0);
});

test("it does not change password if not provided during update", function () {
    $this->actingAs($this->adminUser);
    $oldHash = Hash::make("old-password");
    $user = User::factory()->create(["password" => $oldHash]);

    Livewire::test("pages::admin.user")
        ->call("edit", $user->id)
        ->set("name", "Updated Name")
        ->set("password", "")
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check("old-password", $user->password))->toBeTrue();
});

test("it updates password if provided during update", function () {
    $this->actingAs($this->adminUser);
    $user = User::factory()->create();

    Livewire::test("pages::admin.user")
        ->call("edit", $user->id)
        ->set("password", "NewSecurePassword123!")
        ->set("password_confirmation", "NewSecurePassword123!")
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasNoErrors();

    $user->refresh();
    expect(Hash::check("NewSecurePassword123!", $user->password))->toBeTrue();
});

test("it validates required fields", function () {
    $this->actingAs($this->adminUser);

    Livewire::test("pages::admin.user")
        ->call("create")
        ->set("name", "")
        ->set("email", "")
        ->set("selectedRoles", [])
        ->call("save")
        ->assertHasErrors(["name" => "required", "email" => "required", "selectedRoles" => "required"]);
});

test("it validates email uniqueness", function () {
    $this->actingAs($this->adminUser);
    User::factory()->create(["email" => "duplicate@example.com"]);

    Livewire::test("pages::admin.user")
        ->call("create")
        ->set("name", "New User")
        ->set("email", "duplicate@example.com")
        ->set("password", "Password123!")
        ->set("password_confirmation", "Password123!")
        ->set("selectedRoles", ["Admin"])
        ->call("save")
        ->assertHasErrors(["email" => "unique"]);
});

test("it validates field lengths and constraints", function () {
    $this->actingAs($this->adminUser);

    Livewire::test("pages::admin.user")
        ->call("create")
        ->set("name", "a") // min:2
        ->set("email", "not-an-email") // format
        ->set("password", "short") // min:8 (typical default)
        ->call("save")
        ->assertHasErrors(["name" => "min", "email" => "email", "password" => "min"]);
});

test("admins can delete a user", function () {
    $this->actingAs($this->adminUser);
    $user = User::factory()->create();

    Livewire::test("pages::admin.user")
        ->call("delete", $user->id)
        ->assertDispatched("modal-show", name: "delete-user")
        ->call("deleteConfirmed")
        ->assertDispatched("pg:eventRefresh-userTable")
        ->assertDispatched("modal-close", name: "delete-user");

    $this->assertSoftDeleted("users", ["id" => $user->id]);
    $user->refresh();
    expect($user->deleted_by)->toBe($this->adminUser->id);
});

test("admins can bulk delete users", function () {
    $this->actingAs($this->adminUser);
    $users = User::factory()->count(3)->create();
    $ids = $users->pluck("id")->toArray();

    Livewire::test("pages::admin.user")
        ->call("bulkDelete", $ids)
        ->assertDispatched("modal-show", name: "bulk-delete-users")
        ->call("bulkDeleteConfirmed")
        ->assertDispatched("pg:eventRefresh-userTable")
        ->assertDispatched("modal-close", name: "bulk-delete-users");

    foreach ($ids as $id) {
        $this->assertSoftDeleted("users", ["id" => $id]);
        $deletedUser = User::withTrashed()->find($id);
        expect($deletedUser->deleted_by)->toBe($this->adminUser->id);
    }
});

test("it can search for users in the table", function () {
    $this->actingAs($this->adminUser);
    User::factory()->create(["name" => "SearchTargetUser", "email" => "target@example.com"]);
    User::factory()->create(["name" => "OtherUser", "email" => "other@example.com"]);

    Livewire::test(UserTable::class)
        ->set("search", "SearchTargetUser")
        ->assertSet("search", "SearchTargetUser")
        ->assertSee("SearchTargetUser");
});

test("it can filter users by role in the table", function () {
    $this->actingAs($this->adminUser);
    $adminRole = Role::findByName("Admin");
    $userRole = Role::findOrCreate("RegularUserRole");
    
    $adminUser = User::factory()->create(["name" => "AdminUserInTable"]);
    $adminUser->assignRole($adminRole);
    
    $regularUser = User::factory()->create(["name" => "RegularUserInTable"]);
    $regularUser->assignRole($userRole);

    Livewire::test(UserTable::class)
        ->set("filters", ["multi_select" => ["roles" => ["Admin"]]])
        ->assertSet("filters.multi_select.roles", ["Admin"])
        ->assertSee("AdminUserInTable");
});

test("it can toggle column visibility in the table", function () {
    $this->actingAs($this->adminUser);
    
    Livewire::test(UserTable::class)
        ->assertSet("columns.0.hidden", true)
        ->call("toggleColumn", "id")
        ->assertSet("columns.0.hidden", false)
        ->call("toggleColumn", "id")
        ->assertSet("columns.0.hidden", true);
});

test("it can export users to excel and csv", function () {
    $this->actingAs($this->adminUser);
    
    Livewire::test(UserTable::class)
        ->call("exportToXLS")
        ->assertStatus(200);
        
    Livewire::test(UserTable::class)
        ->call("exportToCsv")
        ->assertStatus(200);
});

test("users with only view permission cannot create", function () {
    $viewOnlyUser = User::factory()->withoutTwoFactor()->create();
    $viewOnlyRole = Role::findOrCreate("ViewOnly");
    $viewOnlyRole->syncPermissions(["View Users"]);
    $viewOnlyUser->assignRole($viewOnlyRole);

    $this->actingAs($viewOnlyUser);

    Livewire::test("pages::admin.user")
        ->call("create")
        ->assertForbidden();
});
