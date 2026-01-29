<?php

namespace BookStack\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class TaskCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Có task mới cần duyệt')
            ->line($this->payload['message'])
            ->action('Xem chi tiết', $this->payload['link'] ?? url('/'));
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'Task mới',
            'message' => $this->payload['message'],
            'link' => $this->payload['link'] ?? null,
        ];
    }
}
