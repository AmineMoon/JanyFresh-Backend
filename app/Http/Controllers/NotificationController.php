<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'nullable|in:general,order,delivery,system',
            'data' => 'nullable|array',
            'recipient_type' => 'required|in:all,retailers,drivers,everyone,specific',
            'specific_user_ids' => 'required_if:recipient_type,specific|array',
            'specific_user_ids.*' => 'exists:users,id',
        ]);

        $notification = $this->notificationService->createAndSend(
            array_merge($validated, ['sender_id' => $request->user()->id]),
            $validated['recipient_type'],
            $validated['specific_user_ids'] ?? null
        );

        return response()->json([
            'message' => 'Notification created and sending',
            'notification' => $notification->load('sender'),
        ], 201);
    }

    public function index(Request $request)
    {
        $query = Notification::with('sender')->latest();

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $notifications = $query->paginate($request->get('per_page', 15));

        return response()->json($notifications);
    }

    public function show($id)
    {
        $notification = Notification::with(['sender', 'recipients.user'])->findOrFail($id);

        return response()->json($notification);
    }

    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted successfully']);
    }

    public function stats()
    {
        $stats = [
            'total' => Notification::count(),
            'sent' => Notification::where('status', 'sent')->count(),
            'sending' => Notification::where('status', 'sending')->count(),
            'failed' => Notification::where('status', 'failed')->count(),
            'total_recipients' => \App\Models\NotificationRecipient::count(),
            'total_read' => \App\Models\NotificationRecipient::where('status', 'read')->count(),
        ];

        return response()->json($stats);
    }
}
