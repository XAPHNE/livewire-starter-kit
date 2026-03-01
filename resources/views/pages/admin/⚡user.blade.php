<?php

use Livewire\Component;
use App\Models\User;
use App\Models\Tier;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\On;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Concerns\PasswordValidationRules;

new class extends Component {
    use PasswordValidationRules;

    public ?int $userId = null;
    public array $userIds = [];
    public array $selectedRoles = [];
    public array $availableRoles = [];
    public array $selectedTiers = [];
    public array $availableTiers = [];
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $reset_password = false;
    public bool $enable_tfa = true;
    public bool $unlock_login = false;
    public bool $unlock_tfa = false;
    public bool $is_login_locked = false;
    public bool $is_tfa_locked = false;

    public function create(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Create Users');

        $this->reset();
        $this->availableRoles = Role::all()->toArray();
        $this->availableTiers = Tier::all()->toArray();
        $this->selectedRoles = [];
        $this->selectedTiers = [];
        $this->is_login_locked = false;
        $this->is_tfa_locked = false;
        $this->unlock_login = false;
        $this->unlock_tfa = false;
        $this->resetErrorBag();
    }

    #[On('edit-user')]
    public function edit(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Edit Users');
        $user = User::findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->password_confirmation = '';
        $this->reset_password = $user->reset_password;
        $this->enable_tfa = $user->two_factor_type !== 'disabled';

        $this->availableRoles = Role::all()->toArray();
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->availableTiers = Tier::all()->toArray();
        $this->selectedTiers = $user->tiers->pluck('id')->toArray();

        $this->is_login_locked = $user->login_locked_until && \Illuminate\Support\Carbon::parse($user->login_locked_until)->isFuture();
        $this->is_tfa_locked = $user->two_factor_locked_until && \Illuminate\Support\Carbon::parse($user->two_factor_locked_until)->isFuture();
        $this->unlock_login = false;
        $this->unlock_tfa = false;

        $this->resetErrorBag();

        $this->dispatch('modal-show', name: 'create-update-user');
    }

    #[On('delete-user')]
    public function delete(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Users');
        $this->userId = $id;
        $this->dispatch('modal-show', name: 'delete-user');
    }

    public function deleteConfirmed(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Users');
        try {
            $user = User::findOrFail($this->userId);
            $user->update(['deleted_by' => auth()->id()]);
            $user->delete();

            $this->dispatch('pg:eventRefresh-userTable');
            $this->dispatch('modal-close', name: 'delete-user');
            flash()->option('position', 'bottom-right')->success('User deleted successfully.');
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }

    #[On('bulk-delete-users')]
    public function bulkDelete(array $ids): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Users');
        $this->userIds = $ids;
        $this->dispatch('modal-show', name: 'bulk-delete-users');
    }

    public function bulkDeleteConfirmed(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Users');
        try {
            $users = User::whereIn('id', $this->userIds)->get();
            foreach ($users as $user) {
                $user->update(['deleted_by' => auth()->id()]);
                $user->delete();
            }

            $this->dispatch('pg:eventRefresh-userTable');
            $this->dispatch('modal-close', name: 'bulk-delete-users');
            flash()->option('position', 'bottom-right')->success('Users deleted successfully.');
            $this->userIds = [];
            $this->dispatch('clear-checkboxes', name: 'userTable');
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }

    public function save(): void
    {
        $pwdRules = $this->passwordRules($this->userId ? User::find($this->userId) : null);
        if ($this->userId) {
            if (empty($this->password)) {
                $pwdRules = ['nullable'];
            } else {
                if (($key = array_search('required', $pwdRules)) !== false) {
                    unset($pwdRules[$key]);
                }
                $pwdRules[] = 'nullable';
            }
        }

        $this->validate([
            'name' => ['required', 'min:2', 'max:100', new \App\Rules\AllowedCharactersRule()],
            'email' => ['required', 'email', 'min:5', 'max:255', Rule::unique('users')->ignore($this->userId)],
            'password' => $pwdRules,
            'selectedRoles' => ['required', 'array', 'min:1'],
            'reset_password' => ['required', 'boolean'],
            'enable_tfa' => ['required', 'boolean'],
        ]);

        try {
            if ($this->userId) {
                \Illuminate\Support\Facades\Gate::authorize('Edit Users');
                $user = User::findOrFail($this->userId);
                $data = [
                    'name' => $this->name,
                    'email' => $this->email,
                    'reset_password' => $this->reset_password ?? false,
                    'two_factor_type' => $this->enable_tfa ? ($user->two_factor_type === 'disabled' ? 'email' : $user->two_factor_type ?? 'email') : 'disabled',
                    'updated_by' => auth()->id(),
                ];
                if ($this->password) {
                    $data['password'] = Hash::make($this->password);
                }

                if ($this->is_login_locked && $this->unlock_login) {
                    $data['login_locked_until'] = null;
                    $data['login_attempts'] = 0;
                }

                if ($this->is_tfa_locked && $this->unlock_tfa) {
                    $data['two_factor_locked_until'] = null;
                    $data['two_factor_resend_count'] = 0;
                }

                $user->update($data);
                $user->syncRoles($this->selectedRoles);
                // sync tiers (many-to-many)
                $user->tiers()->sync($this->selectedTiers);

                $message = 'User updated successfully.';
            } else {
                \Illuminate\Support\Facades\Gate::authorize('Create Users');
                $user = User::create(['name' => $this->name, 'email' => $this->email, 'password' => Hash::make($this->password), 'reset_password' => $this->reset_password ?? false, 'two_factor_type' => $this->enable_tfa ? 'email' : 'disabled', 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
                $user->syncRoles($this->selectedRoles);
                // attach tiers if provided
                if (!empty($this->selectedTiers)) {
                    $user->tiers()->sync($this->selectedTiers);
                }
                $message = 'User created successfully.';
            }

            $this->dispatch('pg:eventRefresh-userTable');
            $this->dispatch('modal-close', name: 'create-update-user');
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
        <flux:breadcrumbs.item>Users</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <br />
    <flux:separator />
    <br />

    <flux:card class="space-y-6">
        <flux:heading size="lg" class="flex justify-between">User Table
            @can('Create Users')
                <flux:modal.trigger name="create-update-user">
                    <flux:button variant="primary" color="green" wire:click="create">Create new</flux:button>
                </flux:modal.trigger>
            @endcan
        </flux:heading>
        @livewire('tables.userTable')
    </flux:card>

    <flux:modal name="create-update-user" class="w-100 md:w-200">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $userId ? 'Update User' : 'Create User' }}</flux:heading>
                <flux:text class="mt-2">{{ $userId ? 'Make changes to user details.' : 'Create a new user.' }}
                </flux:text>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column: Core Identity -->
                <div class="space-y-6">
                    <flux:input label="Name" badge="Required" wire:model="name" />
                    <flux:input label="Email" badge="Required" wire:model="email" />

                    <div class="space-y-4 pt-2">
                        <flux:field variant="inline">
                            <flux:label>Must Reset Password</flux:label>
                            <flux:switch wire:model="reset_password" />
                            <flux:error name="reset_password" />
                        </flux:field>

                        <flux:field variant="inline">
                            <flux:label>Enable Two-Factor Authentication</flux:label>
                            <flux:switch wire:model="enable_tfa" />
                            <flux:error name="enable_tfa" />
                        </flux:field>

                        @if ($userId && $is_login_locked)
                            <flux:field variant="inline">
                                <flux:label>Clear Login Lock & Attempts</flux:label>
                                <flux:switch wire:model="unlock_login" color="orange" />
                            </flux:field>
                        @endif

                        @if ($userId && $is_tfa_locked)
                            <flux:field variant="inline">
                                <flux:label>Clear 2FA Lock & Resend Limit</flux:label>
                                <flux:switch wire:model="unlock_tfa" color="purple" />
                            </flux:field>
                        @endif
                    </div>
                </div>

                <!-- Right Column: Security & Access -->
                <div class="space-y-6">
                    <flux:input label="Password" :badge="$userId ? false : 'Required'" type="password"
                        wire:model="password" viewable />
                    <flux:input label="Confirm Password" :badge="$userId ? false : 'Required'" type="password"
                        wire:model="password_confirmation" viewable />

                    <div class="space-y-4">
                        <div class="relative">
                            <flux:label class="mb-2" badge="Required">User Roles</flux:label>
                            <livewire:components.pillbox-select :options="array_column($availableRoles, 'name')" wire:model="selectedRoles"
                                direction="down" />
                            <flux:error name="selectedRoles" />
                        </div>

                        @if (\App\Models\Setting::get('enable_tier_based_concurrency', false))
                            <div class="relative">
                                <flux:label class="mb-2">Tiers</flux:label>
                                <flux:select wire:model="selectedTiers" class="w-full">
                                    @foreach (Tier::all() as $tier)
                                        <flux:select.option value="{{ $tier->id }}">{{ $tier->name }} @if ($tier->concurrent_sessions)
                                                ({{ $tier->concurrent_sessions }} sessions)
                                            @endif
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <flux:separator />

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-update-user').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="green">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-user" class="md:w-96">
        <form wire:submit="deleteConfirmed" class="space-y-6">
            <div>
                <flux:heading size="lg">Delete User</flux:heading>
                <flux:text class="mt-2">Are you sure you want to delete this user? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('delete-user').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="red">Delete</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="bulk-delete-users" class="md:w-96">
        <form wire:submit="bulkDeleteConfirmed" class="space-y-6">
            <div>
                <flux:heading size="lg">Bulk Delete Users</flux:heading>
                <flux:text class="mt-2">Are you sure you want to delete <span x-text="$wire.userIds.length"></span>
                    users? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('bulk-delete-users').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="red">Delete Selected</flux:button>
            </div>
        </form>
    </flux:modal>


</div>
