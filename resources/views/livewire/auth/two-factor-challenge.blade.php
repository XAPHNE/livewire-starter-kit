    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data
            @focus-2fa-auth-code.window="$dispatch('focus-2fa-auth-code')"
        >
            <div x-show="!$wire.showRecoveryInput">
                @php
                    $isEmail = true;
                    if (session()->has('login.id')) {
                        $user = \App\Models\User::find(session('login.id'));
                        $isEmail = $user?->two_factor_type === 'email';
                    }
                @endphp
                <x-auth-header
                    :title="__('Authentication Code')"
                    :description="$isEmail ? __('Enter the 6-digit authentication code that was emailed to you.') : __('Enter the authentication code provided by your authenticator application.')"
                />
            </div>

            <div x-show="$wire.showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery Code')"
                    :description="__('Please confirm access to your account by entering one of your emergency recovery codes.')"
                />
            </div>

            <form wire:submit.prevent="verify">
                <div class="space-y-5 text-center">
                    <div x-show="!$wire.showRecoveryInput">
                        <div class="flex items-center justify-center my-5">
                            <flux:otp
                                wire:model="code"
                                length="6"
                                name="code"
                                label="OTP Code"
                                label:sr-only
                                class="mx-auto"
                             />
                        </div>

                        @if ($isEmail)
                           <div class="mt-2 text-sm text-right">
                               <flux:link wire:click="resendOtp" class="cursor-pointer">{{ __('Resend OTP') }}</flux:link>
                           </div>
                        @endif
                    </div>

                    <div x-show="$wire.showRecoveryInput">
                        <div class="my-5">
                            <flux:input
                                type="text"
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-bind:required="$wire.showRecoveryInput"
                                autocomplete="one-time-code"
                                wire:model="recovery_code"
                            />
                        </div>

                        @error('code')
                            <flux:text color="red">
                                {{ $message }}
                            </flux:text>
                        @enderror

                        @error('recovery_code')
                            <flux:text color="red">
                                {{ $message }}
                            </flux:text>
                        @enderror
                    </div>

                    <flux:button
                        variant="primary"
                        type="submit"
                        class="w-full"
                    >
                        {{ __('Continue') }}
                    </flux:button>
                </div>

                <div class="mt-5 space-x-0.5 text-sm leading-5 text-center">
                    <span class="opacity-50">{{ __('or you can') }}</span>
                    <div class="inline font-medium underline cursor-pointer opacity-80">
                        <span x-show="!$wire.showRecoveryInput" wire:click="toggleInput">{{ __('login using a recovery code') }}</span>
                        <span x-show="$wire.showRecoveryInput" wire:click="toggleInput">{{ __('login using an authentication code') }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
