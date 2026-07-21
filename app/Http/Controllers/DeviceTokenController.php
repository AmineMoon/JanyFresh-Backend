<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'platform' => 'required|in:android,ios,web',
            'device_name' => 'nullable|string|max:255',
        ]);

        $deviceToken = DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'device_name' => $validated['device_name'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device token registered successfully',
            'device_token' => $deviceToken,
        ], 201);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:500',
            'new_token' => 'required|string|max:500',
        ]);

        $deviceToken = DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->first();

        if ($deviceToken) {
            $deviceToken->update([
                'token' => $validated['new_token'],
                'last_used_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Device token updated successfully']);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        DeviceToken::where('token', $validated['token'])
            ->where('user_id', $request->user()->id)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Device token removed']);
    }

    public function notifications(Request $request)
    {
        $userId = $request->user()->id;

        $notifications = Notification::whereHas('recipients', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->with(['sender', 'recipients' => function ($q) use ($userId) {
            $q->where('user_id', $userId);
        }])
        ->latest()
        ->paginate($request->get('per_page', 20));

        return response()->json($notifications);
    }

    public function unreadCount(Request $request)
    {
        $count = NotificationRecipient::where('user_id', $request->user()->id)
            ->where('status', '!=', 'read')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markAsRead(Request $request, $id)
    {
        $recipient = NotificationRecipient::where('notification_id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if ($recipient && $recipient->status !== 'read') {
            $recipient->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllAsRead(Request $request)
    {
        NotificationRecipient::where('user_id', $request->user()->id)
            ->where('status', '!=', 'read')
            ->update([
                'status' => 'read',
                'read_at' => now(),
            ]);

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
