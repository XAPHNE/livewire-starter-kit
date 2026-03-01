<?php

use Livewire\Component;
use Spatie\Permission\Models\Permission;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;

new class extends Component {
    public ?int $permissionId = null;
    public array $permissionIds = [];
    public string $name = '';
    public string $guard_name = 'web';

    public function create(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Create Permissions');
        $this->reset();
        $this->resetErrorBag();
    }

    #[On('edit-permission')]
    public function edit(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Edit Permissions');
        $permission = Permission::findOrFail($id);
        $this->permissionId = $permission->id;
        $this->name = $permission->name;
        $this->guard_name = $permission->guard_name;

        $this->resetErrorBag();

        $this->dispatch('modal-show', name: 'create-update-permission');
    }

    #[On('delete-permission')]
    public function delete(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Permissions');
        $this->permissionId = $id;
        $this->dispatch('modal-show', name: 'delete-permission');
    }

    public function deleteConfirmed(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Permissions');
        try {
            $permission = Permission::findOrFail($this->permissionId);
            $permission->delete();

            $this->dispatch('pg:eventRefresh-permissionTable');
            $this->dispatch('modal-close', name: 'delete-permission');
            flash()->option('position', 'bottom-right')->success('Permission deleted successfully.');
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }

    #[On('bulk-delete-permissions')]
    public function bulkDelete(array $ids): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Permissions');
        $this->permissionIds = $ids;
        $this->dispatch('modal-show', name: 'bulk-delete-permissions');
    }

    public function bulkDeleteConfirmed(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Permissions');
        try {
            $permissions = Permission::whereIn('id', $this->permissionIds)->get();
            foreach ($permissions as $permission) {
                $permission->delete();
            }

            $this->dispatch('pg:eventRefresh-permissionTable');
            $this->dispatch('modal-close', name: 'bulk-delete-permissions');
            flash()->option('position', 'bottom-right')->success('Permissions deleted successfully.');
            $this->permissionIds = [];
            $this->dispatch('clear-checkboxes', name: 'permissionTable');
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'min:2', 'max:100', new \App\Rules\AllowedCharactersRule(), Rule::unique('permissions', 'name')->ignore($this->permissionId)],
            'guard_name' => ['required', 'min:3', 'max:50', new \App\Rules\AllowedCharactersRule()],
        ]);

        try {
            if ($this->permissionId) {
                \Illuminate\Support\Facades\Gate::authorize('Edit Permissions');
                $permission = Permission::findOrFail($this->permissionId);
                $permission->update([
                    'name' => $this->name,
                    'guard_name' => $this->guard_name,
                ]);
                $message = 'Permission updated successfully.';
            } else {
                \Illuminate\Support\Facades\Gate::authorize('Create Permissions');
                Permission::create(['name' => $this->name, 'guard_name' => $this->guard_name]);
                $message = 'Permission created successfully.';
            }

            $this->dispatch('pg:eventRefresh-permissionTable');
            $this->dispatch('modal-close', name: 'create-update-permission');
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
        <flux:breadcrumbs.item>Permissions</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <br />
    <flux:separator />
    <br />

    <flux:card class="space-y-6">
        <flux:heading size="lg" class="flex justify-between">Permission Table
            @can('Create Permissions')
                <flux:modal.trigger name="create-update-permission">
                    <flux:button variant="primary" color="green" wire:click="create">Create new</flux:button>
                </flux:modal.trigger>
            @endcan
        </flux:heading>
        @livewire('tables.permissionTable')
    </flux:card>

    <flux:modal name="create-update-permission" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $permissionId ? 'Update Permission' : 'Create Permission' }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ $permissionId ? 'Make changes to permission details.' : 'Create a new permission.' }}
                </flux:text>
            </div>

            <flux:input label="Name" badge="Required" wire:model="name" />
            <flux:select label="Guard Name" badge="Required" wire:model="guard_name" searchable
                placeholder="Choose guard...">
                @foreach (array_keys(config('auth.guards')) as $guard)
                    <flux:select.option value="{{ $guard }}">{{ $guard }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost"
                    x-on:click="$flux.modal('create-update-permission').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="green">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-permission" class="md:w-96">
        <form wire:submit="deleteConfirmed" class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Permission</flux:heading>
                <flux:text class="mt-2">Are you sure you want to delete this permission? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('delete-permission').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="red">Delete</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="bulk-delete-permissions" class="md:w-96">
        <form wire:submit="bulkDeleteConfirmed" class="space-y-6">
            <div>
                <flux:heading size="lg">Bulk Delete Permissions</flux:heading>
                <flux:text class="mt-2">Are you sure you want to delete <span
                        x-text="$wire.permissionIds.length"></span> permissions? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('bulk-delete-permissions').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="red">Delete Selected</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
