<?php

namespace BookStack\Activity\Notifications;

use BookStack\Activity\Notifications\Messages\BaseActivityNotification;
use BookStack\Entities\Models\Book;
use BookStack\Users\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class ProjectMemberAddedNotification extends BaseActivityNotification
{
    public function __construct(
        protected Book $book,
        protected User $addedBy
    ) {}

    public function getMessage(): string
    {
        return trans('notifications.project_member_added', [
            'user'    => $this->addedBy->name,
            'project' => $this->book->name,
        ]);
    }

    public function getUrl(): string
    {
        return $this->book->getUrl();
    }

    /**
     * BẮT BUỘC: Email notification
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('notifications.project_member_added_subject'))
            ->line($this->getMessage())
            ->action(
                trans('notifications.view_project'),
                $this->getUrl()
            );
    }
}
