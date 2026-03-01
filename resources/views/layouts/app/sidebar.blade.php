<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
        class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
            <flux:sidebar.collapse class="lg:hidden" />
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')"
                    wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:spacer />

        <flux:sidebar.nav>
            @canany(['View Users', 'View Roles', 'View Permissions'])
                <flux:sidebar.group :heading="__('User Management')" class="grid">
                    @can('View Users')
                        <flux:sidebar.item icon="users" :href="route('users')" :current="request()->routeIs('users')"
                            wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                    @endcan

                    @canany(['View Roles', 'View Permissions'])
                        <flux:sidebar.group expandable :expanded="request()->routeIs(['roles', 'permissions'])"
                            heading="Roles & Permissions">
                            @can('View Roles')
                                <flux:sidebar.item icon="shield-check" :href="route('roles')" :current="request()->routeIs('roles')"
                                    wire:navigate>
                                    {{ __('Roles') }}
                                </flux:sidebar.item>
                            @endcan
                            @can('View Permissions')
                                <flux:sidebar.item icon="lock-closed" :href="route('permissions')"
                                    :current="request()->routeIs('permissions')" wire:navigate>
                                    {{ __('Permissions') }}
                                </flux:sidebar.item>
                            @endcan
                        </flux:sidebar.group>
                    @endcanany
                </flux:sidebar.group>
            @endcanany

            @can('View Settings')
                <flux:sidebar.group :heading="__('Preferences')" class="grid" x-data="{ enableTiers: {{ \App\Models\Setting::get('enable_tier_based_concurrency', false) ? 'true' : 'false' }} }"
                    @tier-concurrency-updated.window="enableTiers = $event.detail.enabled">
                    <flux:sidebar.item icon="cog-8-tooth" :href="route('system-settings')"
                        :current="request()->routeIs('system-settings')" wire:navigate>
                        {{ __('System Settings') }}
                    </flux:sidebar.item>
                    @canany(['View Audit Trail', 'View Authentication Log'])
                        <flux:sidebar.item icon="finger-print" :href="route('audit-hub')"
                            :current="request()->routeIs('audit-hub')" wire:navigate>
                            {{ __('Audit Hub') }}
                        </flux:sidebar.item>
                    @endcanany

                    @can('View Tiers')
                        <flux:sidebar.item icon="building-library" :href="route('tiers')"
                            :current="request()->routeIs('tiers')" wire:navigate x-show="enableTiers">
                            {{ __('Tiers') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            @endcan
        </flux:sidebar.nav>

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header x-data="{
        atTop: true,
        isScrollingDown: false,
        lastScrollTop: 0
    }"
        @scroll.window="
            atTop = window.pageYOffset < 10;
            isScrollingDown = window.pageYOffset > lastScrollTop && window.pageYOffset > 50;
            lastScrollTop = window.pageYOffset;
        "
        class="lg:hidden sticky top-0 z-10 transition-transform duration-300 bg-white dark:bg-zinc-800"
        x-bind:class="{
            '-translate-y-full': isScrollingDown,
            'translate-y-0': !isScrollingDown,
            'shadow-sm border-b border-zinc-200 dark:border-zinc-700 bg-white/80 dark:bg-zinc-900/80 backdrop-blur-md':
                !atTop
        }">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-4" inset="left" />

        <flux:spacer />

        <flux:dropdown position="top" align="end">
            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>

                <flux:menu.separator />

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator />

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer" data-test="logout-button">
                        {{ __('Log Out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
        </flux:dropdown>
    </flux:header>

    {{ $slot }}

    @fluxScripts
</body>

</html>
