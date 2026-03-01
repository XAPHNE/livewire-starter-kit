<x-layouts::auth>
    <div class="flex flex-col gap-6 text-center">
        <x-auth-header
            :title="session('lockout_title', __('Temporarily Blocked'))"
            :description="session('lockout_reason', __('You have been temporarily blocked due to multiple incorrect attempts. For security reasons, you must wait before trying again.'))"
        />

        <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800">
            <div class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                {{ __('You can try again after:') }}
            </div>
            
            <div id="countdown-timer" class="mt-2 text-2xl font-bold text-red-600 dark:text-red-500">
            </div>
            
            <div class="flex flex-col gap-3 mt-6">
                <flux:button id="try-again-btn" variant="primary" data-href="{{ route('login') }}" class="w-full opacity-50 pointer-events-none">
                    {{ __('Try Again') }}
                </flux:button>
                <flux:button variant="subtle" href="{{ url('/') }}" class="w-full">
                    {{ __('Return Home') }}
                </flux:button>
            </div>
        </div>

        <div class="text-sm text-zinc-500">
            If you need immediate assistance, please contact support.
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @php
                $tfaLockedUntil = session('tfa_locked_until');
                if ($tfaLockedUntil) {
                    $initialTimeLeft = max(0, $tfaLockedUntil - time());
                } else {
                    $initialTimeLeft = session('throttle_seconds', 60);
                }
            @endphp
            
            let timeLeft = {{ (int) $initialTimeLeft }};
            const timerEl = document.getElementById('countdown-timer');
            const tryAgainBtn = document.getElementById('try-again-btn');
            
            function updateDisplay() {
                if (timeLeft <= 0) {
                    timerEl.textContent = 'You can now try again!';
                    if (tryAgainBtn) {
                        tryAgainBtn.classList.remove('opacity-50', 'pointer-events-none');
                        tryAgainBtn.setAttribute('href', tryAgainBtn.getAttribute('data-href'));
                    }
                    return false;
                }
                
                let days = Math.floor(timeLeft / 86400);
                let hours = Math.floor((timeLeft % 86400) / 3600);
                let minutes = Math.floor((timeLeft % 3600) / 60);
                let sec = timeLeft % 60;

                let parts = [];
                if (days > 0) parts.push(days + 'd');
                if (hours > 0) parts.push(hours + 'h');
                if (minutes > 0) parts.push(minutes + 'm');
                if (sec > 0 || parts.length === 0) parts.push(sec + 's');

                timerEl.textContent = parts.join(' ');
                return true;
            }
            
            if (updateDisplay()) {
                const interval = setInterval(function() {
                    timeLeft--;
                    if (!updateDisplay()) {
                        clearInterval(interval);
                    }
                }, 1000);
            }
        });
    </script>
</x-layouts::auth>
