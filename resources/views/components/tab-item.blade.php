@props(['icon' => null, 'active' => false])

@php
    // Define the style variants
    $activeStyles = 'border-zinc-800 dark:border-white text-zinc-900 dark:text-white';
    $inactiveStyles =
        'border-transparent text-zinc-500 hover:text-zinc-700 hover:border-zinc-300 dark:text-zinc-400 dark:hover:text-zinc-200 dark:hover:border-zinc-600';

    $activeIconStyles = 'text-zinc-900 dark:text-white';
    $inactiveIconStyles = 'text-zinc-400 group-hover:text-zinc-500 dark:text-zinc-500 dark:group-hover:text-zinc-300';

    // To avoid colon-collision with Blade props, we look for 'x-active' as our Alpine binding key
    $alpineActive = $attributes->get('x-active');

    // Filter out internal and helper props from the final button attributes
    $buttonAttributes = $attributes->except(['x-active', 'active']);

    // Common base classes for the button
    // flex-shrink-0 is CRITICAL to prevent tabs from overlapping or collapsing in scroll containers
    $baseClasses =
        'group inline-flex items-center py-4 px-1 border-b-2 font-medium text-sm transition-all duration-200 focus:outline-none whitespace-nowrap flex-shrink-0';

    // Determine the initial static classes (used for Livewire/PHP driven tabs)
    $initialClasses = $baseClasses;
    if (!$alpineActive) {
        $initialClasses .= ' ' . ($active ? $activeStyles : $inactiveStyles);
    }
@endphp

<button {{ $buttonAttributes->merge(['type' => 'button', 'class' => $initialClasses]) }}
    @if ($alpineActive) x-bind:class="({{ $alpineActive }}) ? '{{ $activeStyles }}' : '{{ $inactiveStyles }}'" @endif>
    @if ($icon)
        <span
            class="mr-2 inline-flex items-center justify-center {{ !$alpineActive ? ($active ? $activeIconStyles : $inactiveIconStyles) : '' }}"
            @if ($alpineActive) x-bind:class="({{ $alpineActive }}) ? '{{ $activeIconStyles }}' : '{{ $inactiveIconStyles }}'" @endif>
            <flux:icon :icon="$icon" variant="mini" class="h-5 w-5" />
        </span>
    @endif
    <span>{{ $slot }}</span>
</button>
