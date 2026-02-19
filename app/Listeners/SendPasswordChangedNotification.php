<?php

namespace App\Listeners;

use App\Notifications\PasswordChanged;
use Illuminate\Auth\Events\PasswordReset;

class SendPasswordChangedNotification
{
    /**
     * Handle the event.
     */
    public function handle(PasswordReset $event): void
    {
        $event->user->notify(new PasswordChanged);
    }
}
