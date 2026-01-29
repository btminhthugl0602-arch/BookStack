<?php

namespace BookStack\Activity\Notifications\Handlers;

use BookStack\Activity\Models\Activity;
use BookStack\Activity\Notifications\ProjectMemberAddedNotification;
use BookStack\Entities\Models\Book;
use BookStack\Users\Models\User;

class ProjectMemberAddedNotificationHandler extends BaseNotificationHandler
{
    public function handle(Activity $activity, $detail, User $actor): void
    {
        // detail = user vừa được thêm
        if (!$detail instanceof User) {
            return;
        }

        $book = \BookStack\Entities\Models\Book::find($activity->loggable_id);
        if (!$book) {
            return;
        }

        // Chỉ notify người vừa được thêm
        $detail->notify(
            new ProjectMemberAddedNotification($book, $actor)
        );
    }
}
