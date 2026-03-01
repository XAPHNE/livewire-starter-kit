<?php

namespace App\Livewire\Settings;

use Livewire\Component;
use App\Models\UserSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Sessions extends Component
{
    /**
     * Terminate the specified user session.
     */
    public function logoutSession(int $sessionId): void
    {
        $sessionRecord = UserSession::where('user_id', Auth::id())
            ->where('id', $sessionId)
            ->first();

        if ($sessionRecord) {
            // In a real Laravel session driver (like database), 
            // you'd also want to delete the actual key from the sessions table 
            // if you are using the database driver for BOTH UserSession model AND Laravel sessions.
            // 
            // Here we assume our custom UserSession table is the source of truth for "active" devices.
            
            $sessionRecord->delete();

            flash()->option('position', 'bottom-right')->success('Session terminated successfully.');
        }
    }

    /**
     * Log out from all other devices except the current one.
     */
    public function logoutOtherBrowserSessions(): void
    {
        $currentSessionId = Session::getId();

        UserSession::where('user_id', Auth::id())
            ->where('session_id', '!=', $currentSessionId)
            ->delete();

        flash()->option('position', 'bottom-right')->success('All other sessions terminated.');
    }

    public function render()
    {
        $sessions = UserSession::where('user_id', Auth::id())
            ->orderBy('last_activity', 'desc')
            ->get();

        return view('livewire.settings.sessions', [
            'sessions' => $sessions,
            'currentSessionId' => Session::getId(),
        ]);
    }
}
