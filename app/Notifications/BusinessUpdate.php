<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class BusinessUpdate extends Notification
{
    public function __construct(public int $organizationId, public int $requestId, public string $title, public string $message, public string $eventKey) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['organization_id' => $this->organizationId, 'request_id' => $this->requestId, 'title' => $this->title, 'message' => $this->message, 'event_key' => $this->eventKey];
    }
}
