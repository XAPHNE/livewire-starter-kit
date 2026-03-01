<?php

use Livewire\Component;

new class extends Component {
    public function mount()
    {
        // only allow users who have one of the relevant permissions; don't
        // pass a closure to Gate::authorize because Spatie's registrar will
        // attempt to register it as an ability.
        $user = auth()->user();
        if (!($user->can('View Audit Trail') || $user->can('View Authentication Log'))) {
            abort(403);
        }
    }
};
?>

<div x-data="{ tab: '{{ auth()->user()->can('View Audit Trail') ? 'audits' : 'auth' }}' }">
    <div
        class="sticky top-0 z-19 -mt-6 -mx-4 px-4 pt-4 pb-4 mb-6 bg-white/80 dark:bg-zinc-800/80 backdrop-blur-md border-b border-zinc-200 dark:border-zinc-700 font-sans">
        <div>
            <flux:breadcrumbs>
                <flux:breadcrumbs.item href="{{ route('dashboard') }}">Home</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Audit Hub</flux:breadcrumbs.item>
            </flux:breadcrumbs>
        </div>

        <flux:separator class="-mx-4 mt-3 mb-4" />

        <div class="flex items-center justify-between">
            <flux:heading size="xl">Audit Hub</flux:heading>
        </div>

        <nav class="mt-1 flex gap-x-8 border-b border-zinc-200 dark:border-zinc-700 overflow-x-auto no-scrollbar"
            aria-label="Tabs">
            @can('View Audit Trail')
                <x-tab-item @click="tab = 'audits'" x-active="tab === 'audits'" icon="document-magnifying-glass">
                    Audit Trail
                </x-tab-item>
            @endcan

            @can('View Authentication Log')
                <x-tab-item @click="tab = 'auth'" x-active="tab === 'auth'" icon="finger-print">
                    Authentication Logs
                </x-tab-item>
            @endcan
        </nav>
    </div>

    <div class="space-y-6">
        @can('View Audit Trail')
            <div x-show="tab === 'audits'" x-transition>
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">User Activity</flux:heading>
                        <flux:text class="mt-2 text-sm text-zinc-500">Track all structural data changes across the
                            platform's
                            models.</flux:text>
                    </div>
                    @livewire('tables.audit-trail-table')
                </flux:card>
            </div>
        @endcan

        @can('View Authentication Log')
            <div x-show="tab === 'auth'" x-transition>
                <flux:card class="space-y-6">
                    <div>
                        <flux:heading size="lg">Authentication History</flux:heading>
                        <flux:text class="mt-2 text-sm text-zinc-500">View detailed login attempts, device tracking, and
                            security
                            events.</flux:text>
                    </div>
                    @livewire('tables.authentication-log-table')
                </flux:card>
            </div>
        @endcan
    </div>
</div>
