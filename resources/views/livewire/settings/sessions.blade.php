<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Device Management') }}</flux:heading>

    <x-settings.layout :heading="__('Browser Sessions')" :subheading="__('Manage and log out your active sessions on other browsers and devices')">
        <div class="space-y-6 my-6">
            <div class="max-w-xl text-sm text-zinc-600 dark:text-zinc-400">
                {{ __('If necessary, you may log out of all of your other browser sessions across all of your devices. Some of your recent sessions are listed below; however, this list may not be exhaustive. If you feel your account has been compromised, you should also update your password.') }}
            </div>

            @if (count($sessions) > 0)
                <div class="mt-5 space-y-6">
                    @foreach ($sessions as $session)
                        <div class="flex items-center">
                            <div>
                                @if (str_contains($session->user_agent, 'Windows'))
                                    <flux:icon name="computer-desktop" class="h-8 w-8 text-zinc-500" />
                                @elseif (str_contains($session->user_agent, 'iPhone') || str_contains($session->user_agent, 'Android'))
                                    <flux:icon name="device-phone-mobile" class="h-8 w-8 text-zinc-500" />
                                @else
                                    <flux:icon name="globe-alt" class="h-8 w-8 text-zinc-500" />
                                @endif
                            </div>

                            <div class="ms-3">
                                <div class="text-sm text-zinc-600 dark:text-zinc-200">
                                    {{ $session->ip_address }}
                                    @if ($session->session_id === Session::getId())
                                        <span
                                            class="text-emerald-500 font-semibold text-xs ml-2">{{ __('This device') }}</span>
                                    @endif
                                </div>

                                <div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500">
                                        {{ $session->user_agent }}
                                    </div>
                                    <div class="text-xs text-zinc-400 dark:text-zinc-500 mt-1">
                                        {{ __('Last active') }} {{ $session->last_activity->diffForHumans() }}
                                    </div>
                                </div>
                            </div>

                            <flux:spacer />

                            @if ($session->session_id !== Session::getId())
                                <flux:button variant="ghost" color="red" size="sm"
                                    wire:click="logoutSession({{ $session->id }})"
                                    wire:confirm="Are you sure you want to terminate this session?">
                                    {{ __('Log out') }}
                                </flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center gap-4 mt-8">
                <flux:button variant="primary" color="red" wire:click="logoutOtherBrowserSessions"
                    wire:confirm="Are you sure you want to log out of all other browser sessions?">
                    {{ __('Log out other browser sessions') }}
                </flux:button>
            </div>
        </div>
    </x-settings.layout>
</section>
