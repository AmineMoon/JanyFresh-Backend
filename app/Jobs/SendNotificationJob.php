<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public int $notificationId)
    {
    }

    public function handle(FcmService $fcmService): void
    {
        $notification = Notification::find($this->notificationId);

        if (!$notification) {
            Log::error("SendNotificationJob: Notification {$this->notificationId} not found");
            return;
        }

        $pendingRecipients = NotificationRecipient::where('notification_id', $notification->id)
            ->where('status', 'pending')
            ->with('user')
            ->get();

        if ($pendingRecipients->isEmpty()) {
            $notification->update(['status' => Notification::STATUS_SENT]);
            return;
        }

        $sentCount = 0;
        $failedCount = 0;

        foreach ($pendingRecipients->chunk(50) as $chunk) {
            foreach ($chunk as $recipient) {
                if (!$recipient->user) {
                    $recipient->update(['status' => 'failed', 'error_message' => 'User not found']);
                    $failedCount++;
                    continue;
                }

                $deviceTokens = DeviceToken::where('user_id', $recipient->user_id)
                    ->active()
                    ->get();

                if ($deviceTokens->isEmpty()) {
                    $recipient->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                    $sentCount++;
                    continue;
                }

                $tokenSent = false;

                foreach ($deviceTokens as $deviceToken) {
                    $success = $fcmService->sendToDevice(
                        $deviceToken,
                        $notification->title,
                        $notification->message,
                        [
                            'notification_id' => (string) $notification->id,
                            'type' => $notification->type,
                            'screen' => $notification->data['screen'] ?? 'notifications',
                        ]
                    );

                    if ($success) {
                        $tokenSent = true;
                    }
                }

                $recipient->update([
                    'status' => $tokenSent ? 'sent' : 'failed',
                    'sent_at' => now(),
                    'error_message' => $tokenSent ? null : 'Failed to deliver to all devices',
                ]);

                if ($tokenSent) {
                    $sentCount++;
                } else {
                    $failedCount++;
                }
            }
        }

        $notification->update([
            'status' => $failedCount === 0 ? Notification::STATUS_SENT : Notification::STATUS_SENT,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SendNotificationJob failed for notification {$this->notificationId}", [
            'error' => $exception->getMessage(),
        ]);

        Notification::where('id', $this->notificationId)
            ->update(['status' => Notification::STATUS_FAILED]);
    }
}
