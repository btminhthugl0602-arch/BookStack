<?php

namespace BookStack\Services;

use BookStack\Users\Models\User;
use BookStack\Notifications\TaskCreatedNotification;

class NotificationService
{
    public static function send(
        int $userId,
        string $message,
        ?string $link = null
    ): void {
        $user = User::find($userId);

        if (!$user) {
            return;
        }

        $user->notify(new TaskCreatedNotification([
            'message' => $message,
            'link' => $link,
        ]));
    }
}
