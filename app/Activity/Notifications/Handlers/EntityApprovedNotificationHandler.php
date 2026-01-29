<?php

namespace BookStack\Activity\Notifications\Handlers;

use BookStack\Activity\Models\Activity;
use BookStack\Activity\Notifications\EntityApprovedNotification;
use BookStack\Entities\Models\Entity;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\DB;

class EntityApprovedNotificationHandler extends BaseNotificationHandler
{
    public function handle(Activity $activity, $detail, User $actor): void
    {
        // Chỉ xử lý khi detail là Entity
        if (!$detail instanceof Entity) {
            return;
        }

        // Xác định book của entity
        $bookId = match ($detail->type) {
            'book'    => $detail->id,
            default   => $detail->book_id,
        };

        if (!$bookId) {
            return;
        }

        // Lấy danh sách member của project
        $memberIds = DB::table('project_members')
            ->where('book_id', $bookId)
            ->pluck('user_id')
            ->toArray();

        // Thêm chủ dự án (leader)
        $ownerId = DB::table('entities')
            ->where('id', $bookId)
            ->where('type', 'book')
            ->value('owned_by');

        if ($ownerId) {
            $memberIds[] = $ownerId;
        }

        $memberIds = array_unique($memberIds);

        // Notify từng member
        User::whereIn('id', $memberIds)->each(function (User $user) use ($detail, $actor) {
            // Không notify người tự duyệt
            if ($user->id === $actor->id) {
                return;
            }

            $user->notify(
                new EntityApprovedNotification($detail, $actor)
            );
        });
    }
}
