<?php

namespace BookStack\Activity\Notifications;

use BookStack\Activity\Notifications\Messages\BaseActivityNotification;
use BookStack\Entities\Models\Entity;
use BookStack\Users\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

class EntityApprovedNotification extends BaseActivityNotification
{
    public function __construct(
        protected Entity $entity,
        protected User $approvedBy
    ) {}

    public function getMessage(): string
    {
        return trans('notifications.entity_approved', [
            'approver' => $this->approvedBy->name,
            'entity'   => $this->entity->name,
            'author'   => optional($this->entity->creator)->name,
        ]);
    }

    public function getUrl(): string
    {
        return $this->entity->getUrl();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject(trans('notifications.entity_approved_subject'))
            ->line($this->getMessage())
            ->action(trans('notifications.view_entity'), $this->getUrl());
    }
}
