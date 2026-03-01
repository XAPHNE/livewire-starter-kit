<?php

use Livewire\Component;
use App\Models\Tier;
use Livewire\Attributes\On;

new class extends Component {
    public ?int $tierId = null;
    public string $name = '';
    public string $description = '';
    public ?int $concurrent_sessions = 1;

    public function mount()
    {
        // Only allow access when tier-based concurrency is enabled
        if (!\App\Models\Setting::get('enable_tier_based_concurrency', false)) {
            abort(404);
        }

        \Illuminate\Support\Facades\Gate::authorize('View Tiers');
    }

    public function create(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Create Tiers');

        $this->reset(['tierId', 'name', 'description', 'concurrent_sessions']);
        $this->concurrent_sessions = 1;
        $this->resetErrorBag();
    }

    #[On('edit-tier')]
    public function edit(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Edit Tiers');
        $tier = Tier::findOrFail($id);
        $this->tierId = $tier->id;
        $this->name = $tier->name;
        $this->description = $tier->description;
        $this->concurrent_sessions = $tier->concurrent_sessions;

        $this->resetErrorBag();

        $this->dispatch('modal-show', name: 'create-update-tier');
    }

    #[On('delete-tier')]
    public function delete(int $id): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Tiers');
        $this->tierId = $id;
        $this->dispatch('modal-show', name: 'delete-tier');
    }

    public function deleteConfirmed(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('Delete Tiers');
        try {
            $tier = Tier::findOrFail($this->tierId);
            $tier->delete();

            $this->dispatch('pg:eventRefresh-tierTable');
            $this->dispatch('modal-close', name: 'delete-tier');
            flash()->option('position', 'bottom-right')->success('Tier deleted successfully.');
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'concurrent_sessions' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        try {
            if ($this->tierId) {
                \Illuminate\Support\Facades\Gate::authorize('Edit Tiers');
                $tier = Tier::findOrFail($this->tierId);
                $tier->update([
                    'name' => $this->name,
                    'description' => $this->description,
                    'concurrent_sessions' => $this->concurrent_sessions,
                ]);
                $message = 'Tier updated successfully.';
            } else {
                \Illuminate\Support\Facades\Gate::authorize('Create Tiers');
                Tier::create([
                    'name' => $this->name,
                    'description' => $this->description,
                    'concurrent_sessions' => $this->concurrent_sessions,
                ]);
                $message = 'Tier created successfully.';
            }

            $this->dispatch('pg:eventRefresh-tierTable');
            $this->dispatch('modal-close', name: 'create-update-tier');
            flash()->option('position', 'bottom-right')->success($message);
            $this->reset(['tierId', 'name', 'description', 'concurrent_sessions']);
        } catch (\Exception $e) {
            flash()->option('position', 'bottom-right')->error('Something went wrong.');
        }
    }
};
?>

<div>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item href="{{ route('dashboard') }}">Home</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Tiers</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <br />
    <flux:separator />
    <br />

    <flux:card class="space-y-6">
        <flux:heading size="lg" class="flex justify-between">Tier Table
            @can('Create Tiers')
                <flux:modal.trigger name="create-update-tier">
                    <flux:button variant="primary" color="green" wire:click="create">Create new</flux:button>
                </flux:modal.trigger>
            @endcan
        </flux:heading>
        @livewire('tables.tierTable')
    </flux:card>

    <flux:modal name="create-update-tier" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $tierId ? 'Update Tier' : 'Create Tier' }}</flux:heading>
                <flux:text class="mt-2">
                    {{ $tierId ? 'Make changes to tier details.' : 'Define a new subscription tier.' }}
                </flux:text>
            </div>

            <flux:input label="Name" badge="Required" wire:model="name" />
            <flux:textarea label="Description" wire:model="description" />
            <flux:input label="Concurrent Sessions" wire:model="concurrent_sessions" type="number" min="1"
                max="1000"
                description="Max number of devices a user in this tier can use simultaneously. Leave empty for system default." />

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('create-update-tier').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="green">Save</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="delete-tier" class="md:w-96">
        <form wire:submit="deleteConfirmed" class="space-y-6">
            <div>
                <flux:heading size="lg">Delete Tier</flux:heading>
                <flux:text class="mt-2">Are you sure you want to delete this tier? This action cannot be undone.
                </flux:text>
            </div>

            <div class="flex justify-between">
                <flux:button type="button" variant="ghost" x-on:click="$flux.modal('delete-tier').close()">
                    Cancel</flux:button>
                <flux:button type="submit" variant="primary" color="red">Delete</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
