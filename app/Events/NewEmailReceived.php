<?php

namespace App\Events;

use App\Models\InboxEmail;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NewEmailReceived
 *
 * Fired by InboxSyncService::processMessage() whenever a new inbox message
 * is successfully stored.
 *
 * Broadcasting
 * ------------
 * Implements ShouldBroadcast so it works with Pusher, Soketi, or any
 * Laravel Echo–compatible driver.  With BROADCAST_DRIVER=log (the current
 * default) the event is written to the log; the frontend falls back to
 * polling via GET /admin/email/inbox/poll so real-time still works without
 * a WebSocket server.
 *
 * Channel: 'admin.inbox'  (public — no auth required within the admin panel)
 * Event:   'new-email'
 */
class NewEmailReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public readonly array $emailData;

    public function __construct(InboxEmail $email)
    {
        // Serialize only what the frontend needs — never expose body_html here
        $this->emailData = [
            'id'           => $email->id,
            'from_email'   => $email->from_email,
            'from_name'    => $email->from_name,
            'display_name' => $email->display_name,
            'subject'      => $email->subject,
            'sender_type'  => $email->sender_type,
            'thread_id'    => $email->thread_id,
            'status'       => $email->status,
            'received_at'  => $email->received_at?->toIso8601String(),
            'show_url'     => route('admin.email.inbox.show', $email->id),
        ];
    }

    public function broadcastOn(): Channel
    {
        return new Channel('admin.inbox');
    }

    public function broadcastAs(): string
    {
        return 'new-email';
    }

    /**
     * Only broadcast the emailData payload — not the full model.
     */
    public function broadcastWith(): array
    {
        return $this->emailData;
    }
}
