<?php

use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;

new class extends Component {
    public ?int $roleId = null;
    public array $roleIds = [];
    public array $selectedPermissions = [];
    public array $availablePermissions = [];
    public string $name = '';
    public string $guard_name = 'web';

    public function create(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Create Roles');
        $this->reset();
        $this->availablePermissions = Permission::where('guard_name', $this->guard_name ?? 'web')
            ->get()
            ->toArray();
        $this->resetErrorBag();
    }

    public function updatedGuardName($value): void
    {
        $this->availablePermissions = Permission::where('guard_name', $value)->get()->toArray();
        // Optional: Remove old selected that don't belong to the new guard scope to prevent orphaned DB syncs.
        $availableNames = array_column($this->availablePermissions, 'name');
        $this->selectedPermissions = array_values(array_intersect($this->selectedPermissions, $availableNames));
    }

    #[On('edit-role')]
    public function edit(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Edit Roles');
        $role = Role::findOrFail($id);
        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->guard_name = $role->guard_name;

        $this->availablePermissions = Permission::where('guard_name', $this->guard_name)->get()->toArray();
        $this->selectedPermissions = $role->permissions->pluck('name')->toArray();

        $this->resetErrorBag();

        $this->dispatch('modal-show', name: 'create-update-role');
    }

    #[On('delete-role')]
    public function delete(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Roles');
        $this->roleId = $id;
        $this->dispatch('modal-show', name: 'delete-role');
    }

    public function deleteConfirmed(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Roles');
        try {
            $role = Role::findOrFail($this->roleId);
            $role->delete();

            $this->dispatch('pg:eventRefresh-roleTable');
            $this->dispatch('modal-close', name: 'delete-role');
            flash()->option('position', 'bottom-right')->success('Role deleted successfully.');
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }

    #[On('bulk-delete-roles')]
    public function bulkDelete(array $ids): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Roles');
        $this->roleIds = $ids;
        $this->dispatch('modal-show', name: 'bulk-delete-roles');
    }

    public function bulkDeleteConfirmed(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Roles');
        try {
            $roles = Role::whereIn('id', $this->roleIds)->get();
            foreach ($roles as $role) {
                $role->delete();
            }

            $this->dispatch('pg:eventRefresh-roleTable');
            $this->dispatch('modal-close', name: 'bulk-delete-roles');
            flash()->option('position', 'bottom-right')->success('Roles deleted successfully.');
            $this->roleIds = [];
            $this->dispatch('clear-checkboxes', name: 'roleTable');
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'min:2', 'max:50', new \App\Rules\AllowedCharactersRule(), Rule::unique('roles', 'name')->ignore($this->roleId)],
            'guard_name' => ['required', 'min:3', 'max:50', new \App\Rules\AllowedCharactersRule()],
        ]);

        try {
            if ($this->roleId) {
                \Illuminate\Support\Facades\Gate::authorize('Edit Roles');
                $role = Role::findOrFail($this->roleId);
                $role->update([
                    'name' => $this->name,
                    'guard_name' => $this->guard_name,
                ]);
                $role->syncPermissions($this->selectedPermissions);
                $message = 'Role updated successfully.';
            } else {
                \Illuminate\Support\Facades\Gate::authorize('Create Roles');
                $role = Role::create(['name' => $this->name, 'guard_name' => $this->guard_name]);
                $role->syncPermissions($this->selectedPermissions);
                $message = 'Role created successfully.';
            }

            $this->dispatch('pg:eventRefresh-roleTable');
            $this->dispatch('modal-close', name: 'create-update-role');
            flash()->option('position', 'bottom-right')->success($message);
            $this->reset();
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }
};
?>

<div>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}">Home</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Roles</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <br />
    <flux:separator />
    <br />

    <flux:card class="space-y-6">
        <flux:heading size="lg" class="flex justify-between">Role Table
            @can('Create Roles')
                <flux:modal.trigger name="create-update-role">
                    <flux:button variant="primary" color="green" wire:click="create">Create new</flux:button>
                </flux:modal.trigger>
            @endcan
        </flux:heading>
        @livewire('tables.roleTable')
    </flux:card>

    <flux:modal name="create-update-role" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $roleId ? 'Update Role' : 'Create Role' }}</flux:heading>
                <flux:text class="mt-2">{{ $roleId ? 'Make changes to role details.' : 'Create a new role.' }}
                </flux:text>
            </div>

            <flux:input label="Name" badge="Required" wire:model="name" />
            <flux:select label="Guard Name" badge="Required" wire:model.live="guard_name" searchable
                placeholder="Choose guard...">
                @foreach (array_keys(config('auth.guards')) as $guard)
                    <flux:select.option value="{{ $guard }}">{{ $guard }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="mb-4 relative z-50">
                <flux:label class="mb-2">Permissions</flux:label>
                <livewire:components.pillbox-select :options="array_column($availablePermissions, 'name')" wire:model="selectedPermissions" direction="up" />
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-update-role').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="green">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-role" class="md:w-96">
        <form wire:submit="deleteConfirmed" class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Role</flux:heading>
                <flux:text class="mt-2">Are you sure you want to delete this role? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('delete-role').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="red">Delete</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="bulk-delete-roles" class="md:w-96">
        <form wire:submit="bulkDeleteConfirmed" class="space-y-6">
            <div>
                <flux:heading size="lg">Bulk Delete Roles</flux:heading>
                <flux:text class="mt-2">Are you sure you want to delete <span x-text="$wire.roleIds.length"></span>
                    roles? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('bulk-delete-roles').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="red">Delete Selected</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
