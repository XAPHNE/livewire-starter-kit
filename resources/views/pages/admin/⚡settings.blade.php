<?php

use App\Models\Setting;
use Livewire\Component;

new class extends Component {
    public $tfa_max_resend_attempts = 3;
    public $tfa_lockdown_hours = 24;
    public $max_login_attempts = 5;
    public $login_lockdown_hours = 2;
    public $forgot_password_max_attempts = 3;
    public $forgot_password_lockdown_hours = 24;
    public $login_throttle_strategy = 'account';
    public $enable_public_registration = true;
    public $default_public_role = '';
    public $default_public_tier = '';
    public $password_history_limit = 3;
    public $password_expiry_days = 90;
    public $password_min_length = 8;
    public $password_max_length = 30;
    public $password_require_uppercase = true;
    public $password_require_lowercase = true;
    public $password_require_numeric = true;
    public $password_require_special_character = true;
    public $password_special_characters = '!@#$%&';
    // Session management settings
    public $session_timeout_minutes = 60;
    public $session_expire_on_close = false;
    public $enable_concurrent_sessions = true;
    public $default_concurrent_sessions = 1;
    public $enable_tier_based_concurrency = false;
    public $allow_user_deletion = true;
    public $allow_email_update = true;

    /**
     * The current active tab.
     */
    public $tab = 'security';

    public function mount()
    {
        $this->tfa_max_resend_attempts = Setting::get('tfa_max_resend_attempts', 3);
        $this->tfa_lockdown_hours = Setting::get('tfa_lockdown_hours', 24);
        $this->max_login_attempts = Setting::get('max_login_attempts', 5);
        $this->login_lockdown_hours = Setting::get('login_lockdown_hours', 2);
        $this->forgot_password_max_attempts = Setting::get('forgot_password_max_attempts', 3);
        $this->forgot_password_lockdown_hours = Setting::get('forgot_password_lockdown_hours', 24);
        $this->login_throttle_strategy = Setting::get('login_throttle_strategy', 'account');
        $this->enable_public_registration = filter_var(Setting::get('enable_public_registration', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->default_public_role = Setting::get('default_public_role', '');
        $this->default_public_tier = Setting::get('default_public_tier', '');
        $this->password_history_limit = Setting::get('password_history_limit', 3);
        $this->password_expiry_days = Setting::get('password_expiry_days', 90);
        $this->password_min_length = Setting::get('password_min_length', 8);
        $this->password_max_length = Setting::get('password_max_length', 30);
        $this->password_require_uppercase = filter_var(Setting::get('password_require_uppercase', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->password_require_lowercase = filter_var(Setting::get('password_require_lowercase', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->password_require_numeric = filter_var(Setting::get('password_require_numeric', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->password_require_special_character = filter_var(Setting::get('password_require_special_character', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->password_special_characters = Setting::get('password_special_characters', '!@#$%&');
        $this->session_timeout_minutes = Setting::get('session_timeout_minutes', 60);
        $this->session_expire_on_close = filter_var(Setting::get('session_expire_on_close', 'false'), FILTER_VALIDATE_BOOLEAN);
        $this->enable_concurrent_sessions = filter_var(Setting::get('enable_concurrent_sessions', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->default_concurrent_sessions = Setting::get('default_concurrent_sessions', 1);
        $this->enable_tier_based_concurrency = filter_var(Setting::get('enable_tier_based_concurrency', 'false'), FILTER_VALIDATE_BOOLEAN);
        $this->allow_user_deletion = filter_var(Setting::get('allow_user_deletion', 'true'), FILTER_VALIDATE_BOOLEAN);
        $this->allow_email_update = filter_var(Setting::get('allow_email_update', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public function save()
    {
        \Illuminate\Support\Facades\Gate::authorize('Edit Settings');

        $validated = $this->validate([
            'tfa_max_resend_attempts' => 'required|integer|min:1|max:10',
            'tfa_lockdown_hours' => 'required|integer|min:1|max:72',
            'max_login_attempts' => 'required|integer|min:1|max:20',
            'login_lockdown_hours' => 'required|integer|min:1|max:72',
            'forgot_password_max_attempts' => 'required|integer|min:1|max:20',
            'forgot_password_lockdown_hours' => 'required|integer|min:1|max:72',
            'login_throttle_strategy' => 'required|in:account,hybrid',
            'default_public_role' => 'nullable|string',
            'default_public_tier' => 'nullable|string',
            'password_history_limit' => 'required|integer|min:0|max:20',
            'password_expiry_days' => 'required|integer|min:0|max:365',
            'password_min_length' => 'required|integer|min:4|max:128',
            'password_max_length' => 'required|integer|min:4|max:128|gte:password_min_length',
            'password_require_uppercase' => 'required|boolean',
            'password_require_lowercase' => 'required|boolean',
            'password_require_numeric' => 'required|boolean',
            'password_require_special_character' => 'required|boolean',
            'password_special_characters' => 'required_if:password_require_special_character,true|string|max:20',
            // session settings
            'session_timeout_minutes' => 'required|integer|min:1|max:1440',
            'session_expire_on_close' => 'required|boolean',
            'enable_concurrent_sessions' => 'required|boolean',
            'default_concurrent_sessions' => 'required|integer|min:1|max:100',
            'enable_tier_based_concurrency' => 'required|boolean',
            'allow_user_deletion' => 'required|boolean',
            'allow_email_update' => 'required|boolean',
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        $this->dispatch('tier-concurrency-updated', enabled: (bool) $this->enable_tier_based_concurrency);

        flash()->option('position', 'bottom-right')->success('System settings updated successfully.');
    }
};
?>

<div>
    <div
        class="sticky top-0 z-20 -mt-6 -mx-4 px-4 pt-4 pb-4 mb-6 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-700">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ route('dashboard') }}">Home</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Settings</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        <flux:separator class="-mx-4 mt-3 mb-4" />

        <div class="flex items-center justify-between">
            <flux:heading size="xl">System Settings</flux:heading>

            @can('Edit Settings')
                <flux:button wire:click="save" variant="primary">Save Changes</flux:button>
            @endcan
        </div>

        <nav class="mt-1 flex gap-x-8 border-b border-zinc-200 dark:border-zinc-700 overflow-x-auto no-scrollbar"
            aria-label="Tabs">
            <x-tab-item wire:click="$set('tab', 'security')" :active="$tab === 'security'" icon="shield-check">
                Security & Throttling
            </x-tab-item>

            <x-tab-item wire:click="$set('tab', 'user_mgmt')" :active="$tab === 'user_mgmt'" icon="users">
                User Registration
            </x-tab-item>

            <x-tab-item wire:click="$set('tab', 'sessions')" :active="$tab === 'sessions'" icon="finger-print">
                Sessions
            </x-tab-item>

            <x-tab-item wire:click="$set('tab', 'password_policy')" :active="$tab === 'password_policy'" icon="key">
                Password Policy
            </x-tab-item>
        </nav>
    </div>

    <form wire:submit="save" class="space-y-6">
        <!-- Security & Throttling Tab -->
        <div x-show="$wire.tab === 'security'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Two-Factor Limits</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500">Configure global throttling parameters for Email OTP
                        requests.</flux:text>
                </div>

                <flux:input label="Max Resend Attempts" badge="Required" wire:model="tfa_max_resend_attempts"
                    type="number" min="1" max="10" description="Permitted resends before account lock." />
                <flux:input label="Lockdown Duration (Hours)" badge="Required" wire:model="tfa_lockdown_hours"
                    type="number" min="1" max="72"
                    description="Time user is blocked after failing OTP." />
            </flux:card>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Login Limits</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500">Configure global throttling for incorrect login
                        attempts.</flux:text>
                </div>

                <flux:select label="Throttling Strategy" badge="Required" wire:model="login_throttle_strategy"
                    description="Hybrid blocks both User and IP.">
                    <flux:select.option value="account">Account Based</flux:select.option>
                    <flux:select.option value="hybrid">IP & Hybrid Based</flux:select.option>
                </flux:select>
                <flux:input label="Maximum Login Attempts" badge="Required" wire:model="max_login_attempts"
                    type="number" min="1" max="20" />
                <flux:input label="Login Lockout (Hours)" badge="Required" wire:model="login_lockdown_hours"
                    type="number" min="1" max="72" />
            </flux:card>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Password Reset Limits</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500">Configure global IP throttling for forgot password
                        requests.</flux:text>
                </div>

                <flux:input label="Maximum Reset Attempts" badge="Required" wire:model="forgot_password_max_attempts"
                    type="number" min="1" max="20" />
                <flux:input label="Reset Lockout (Hours)" badge="Required" wire:model="forgot_password_lockdown_hours"
                    type="number" min="1" max="72" />
            </flux:card>
        </div>

        <!-- User Registration Tab -->
        <div x-show="$wire.tab === 'user_mgmt'" class="max-w-2xl">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Registration & Lifecycle</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500">Configure how the platform handles public user signups
                        and account deletion.</flux:text>
                </div>

                <flux:field variant="inline">
                    <flux:label>Enable Public Registration</flux:label>
                    <flux:switch wire:model="enable_public_registration" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>Allow Users to Delete Accounts</flux:label>
                    <flux:switch wire:model="allow_user_deletion" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>Allow Users to Update Email</flux:label>
                    <flux:switch wire:model="allow_email_update" />
                </flux:field>

                <flux:select label="Default Assigned Role" wire:model="default_public_role"
                    description="Role automatically assigned to newly registered users.">
                    <flux:select.option value="">No Automatic Role / Guest</flux:select.option>
                    @foreach (\Spatie\Permission\Models\Role::all() as $role)
                        <flux:select.option value="{{ $role->name }}">{{ $role->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($enable_tier_based_concurrency)
                    <flux:select label="Default Assigned Tier" wire:model="default_public_tier"
                        description="Tier automatically assigned to newly registered users.">
                        <flux:select.option value="">No Automatic Tier</flux:select.option>
                        @foreach (\App\Models\Tier::all() as $tier)
                            <flux:select.option value="{{ $tier->id }}">{{ $tier->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
            </flux:card>
        </div>

        <!-- Sessions Tab -->
        <div x-show="$wire.tab === 'sessions'" class="max-w-2xl">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Session Management</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500">Configure timeout, concurrent sessions, and device
                        limits.</flux:text>
                </div>

                <flux:input label="Session Timeout (Minutes)" badge="Required" wire:model="session_timeout_minutes"
                    type="number" min="1" max="1440" />

                <flux:field variant="inline">
                    <flux:label>Logout on Browser Close</flux:label>
                    <flux:switch wire:model="session_expire_on_close" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:label>Enable Concurrent Sessions</flux:label>
                    <flux:switch wire:model.live="enable_concurrent_sessions" />
                </flux:field>

                @if ($enable_concurrent_sessions)
                    <flux:input label="Default Concurrent Sessions" badge="Required"
                        wire:model="default_concurrent_sessions" type="number" min="1" max="100"
                        description="Fallback limit for users without a specific tier." />

                    <flux:field variant="inline">
                        <flux:label>Enable Tier-Based Concurrency</flux:label>
                        <flux:switch wire:model.live="enable_tier_based_concurrency" />
                    </flux:field>

                    @if ($enable_tier_based_concurrency)
                        @can('View Tiers')
                            <flux:button as="a" href="{{ route('tiers') }}" variant="subtle" size="sm">
                                Manage Tiers</flux:button>
                        @endcan
                    @endif
                @endif
            </flux:card>
        </div>

        <!-- Password Policy Tab -->
        <div x-show="$wire.tab === 'password_policy'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">History & Expiry</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500">Configure password rotation and history tracking.
                    </flux:text>
                </div>

                <flux:input label="Password History Limit" badge="Required" wire:model="password_history_limit"
                    type="number" min="0" max="20"
                    description="Number of previous passwords to remember." />
                <flux:input label="Password Expiry Days" badge="Required" wire:model="password_expiry_days"
                    type="number" min="0" max="365"
                    description="Days before mandatory rotation (0 = Never)." />
            </flux:card>

            <flux:card class="space-y-6">
                <div>
                    <flux:heading size="lg">Complexity Requirements</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500">Strict requirements for new user passwords.
                    </flux:text>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input label="Minimum Length" badge="Required" wire:model="password_min_length"
                        type="number" min="4" max="128" />
                    <flux:input label="Maximum Length" badge="Required" wire:model="password_max_length"
                        type="number" min="4" max="128" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:field variant="inline">
                        <flux:label>Require Uppercase</flux:label>
                        <flux:switch wire:model="password_require_uppercase" />
                    </flux:field>
                    <flux:field variant="inline">
                        <flux:label>Require Lowercase</flux:label>
                        <flux:switch wire:model="password_require_lowercase" />
                    </flux:field>
                    <flux:field variant="inline">
                        <flux:label>Require Numbers</flux:label>
                        <flux:switch wire:model="password_require_numeric" />
                    </flux:field>
                    <flux:field variant="inline">
                        <flux:label>Require Special Chars</flux:label>
                        <flux:switch wire:model.live="password_require_special_character" />
                    </flux:field>
                </div>

                <div x-show="$wire.password_require_special_character" x-transition>
                    <flux:input label="Allowed Special Characters" badge="Required"
                        wire:model="password_special_characters" />
                </div>
            </flux:card>
        </div>
    </form>
</div>
