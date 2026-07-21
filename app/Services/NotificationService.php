<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\DeviceToken;
use App\Models\User;
use App\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    protected FcmService $fcmService;

    public function __construct(FcmService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    public function createAndSend(array $data, string $recipientType, ?array $specificUserIds = null): Notification
    {
        $recipients = $this->getRecipients($recipientType, $specificUserIds);

        $notification = DB::transaction(function () use ($data, $recipients, $recipientType) {
            $notification = Notification::create([
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'] ?? Notification::TYPE_GENERAL,
                'data' => $data['data'] ?? null,
                'sender_id' => $data['sender_id'] ?? null,
                'recipient_type' => $recipientType,
                'status' => Notification::STATUS_SENDING,
                'total_recipients' => $recipients->count(),
            ]);

            $recipientRecords = $recipients->map(function ($user) use ($notification) {
                return [
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            NotificationRecipient::insert($recipientRecords);

            return $notification;
        });

        SendNotificationJob::dispatch($notification->id);

        return $notification;
    }

    public function getRecipients(string $recipientType, ?array $specificUserIds = null)
    {
        $query = User::where('is_active', true);

        switch ($recipientType) {
            case Notification::RECIPIENT_RETAILERS:
                $query->where('role', 'retailer');
                break;
            case Notification::RECIPIENT_DRIVERS:
                $query->where('role', 'driver');
                break;
            case Notification::RECIPIENT_EVERYONE:
                $query->whereIn('role', ['retailer', 'driver', 'admin']);
                break;
            case Notification::RECIPIENT_SPECIFIC:
                if ($specificUserIds) {
                    $query->whereIn('id', $specificUserIds);
                }
                break;
            case Notification::RECIPIENT_ALL:
            default:
                $query->whereIn('role', ['retailer', 'driver']);
                break;
        }

        return $query->get();
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $recipient = NotificationRecipient::where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($recipient && $recipient->status !== 'read') {
            $recipient->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    public function markAllAsRead(int $userId): int
    {
        return NotificationRecipient::where('user_id', $userId)
            ->where('status', '!=', 'read')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
    }

    public function getUnreadCount(int $userId): int
    {
        return NotificationRecipient::where('user_id', $userId)
            ->where('status', '!=', 'read')
            ->count();
    }
}
