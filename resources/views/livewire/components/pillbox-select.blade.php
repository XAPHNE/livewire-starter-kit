<?php

use Livewire\Component;
use Livewire\Attributes\Modelable;

new class extends Component
{
    public array $options = [];
    
    #[Modelable]
    public array $selected = [];
    
    public string $search = '';
    
    public string $direction = 'down';

    public function mount(array $options = [], array $selected = [], string $direction = 'down')
    {
        $this->options = $options;
        $this->direction = $direction;
    }

    public function selectOption($option)
    {
        if (!in_array($option, $this->selected)) {
            $this->selected[] = $option;
        }
        $this->search = '';
    }

    public function removeOption($option)
    {
        $this->selected = array_values(array_filter($this->selected, fn($o) => $o !== $option));
    }
};
?>

<div x-data="{ open: false }" @click.outside="open = false" class="relative text-left">
    <!-- Pills & Input Area -->
    <div 
        @click="open = true; $nextTick(() => $refs.searchInput.focus())"
        class="min-h-[2.5rem] w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-1.5 shadow-sm focus-within:ring-2 focus-within:ring-zinc-800 dark:focus-within:ring-white focus-within:border-zinc-800 cursor-text flex flex-wrap gap-1 items-center transition duration-75 relative z-10"
    >
        @foreach($selected as $pill)
            <span class="inline-flex items-center gap-1.5 rounded-md bg-zinc-100 dark:bg-zinc-700/50 px-2 py-1 text-xs font-medium text-zinc-700 dark:text-zinc-200">
                {{ $pill }}
                <button type="button" wire:click.stop="removeOption('{{ $pill }}')" class="text-zinc-400 hover:text-red-500 focus:outline-none">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </span>
        @endforeach

        <input 
            x-ref="searchInput"
            type="text" 
            wire:model.live="search"
            @focus="open = true"
            @keydown.escape.prevent="open = false"
            class="flex-1 bg-transparent min-w-[120px] text-sm text-zinc-900 dark:text-zinc-100 placeholder-zinc-400 dark:placeholder-zinc-500 border-none focus:ring-0 p-0 m-0 outline-none shadow-none focus:shadow-none ring-0 focus:border-none focus:border-0"
            placeholder="{{ empty($selected) ? 'Search and choose roles...' : '' }}"
        >
    </div>

    <!-- Dropdown Options -->
    <div 
        x-cloak
        x-show="open" 
        x-transition.opacity.duration.150ms
        class="absolute z-[100] {{ $direction === 'up' ? 'bottom-full mb-1' : 'mt-1' }} w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 py-1 shadow-xl max-h-48 overflow-y-auto"
        style="display: none;"
    >
        @php
            $filteredOptions = collect($options)->filter(fn($o) => str_contains(strtolower($o), strtolower($search)));
        @endphp
        
        @forelse($filteredOptions as $option)
            <div 
                wire:click.stop="selectOption('{{ $option }}')" 
                @click="$refs.searchInput.focus()"
                class="cursor-pointer px-3 py-2 text-sm text-zinc-900 dark:text-zinc-200 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex items-center justify-between transition-colors {{ in_array($option, $selected) ? 'opacity-50' : '' }}"
            >
                {{ $option }}
                @if(in_array($option, $selected))
                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                @endif
            </div>
        @empty
            <div class="px-3 py-2 text-sm text-zinc-500 dark:text-zinc-400">
                No roles found.
            </div>
        @endforelse
    </div>
</div>
