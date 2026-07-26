<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SupportTicketController extends Controller
{
    /**
     * Retailer: Create a new support ticket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'category' => 'required|in:delivery,order,payment,account,other',
            'description' => 'required|string|min:10',
            'related_order' => 'nullable|string',
            'attachment' => 'nullable|image|max:5120',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['category'] = strtolower($validated['category']);

        if ($request->hasFile('attachment')) {
            $validated['attachment'] = $request->file('attachment')->store('support-tickets', 'public');
        }

        $ticket = SupportTicket::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully',
            'data' => $ticket,
        ], 201);
    }

    /**
     * Retailer: List own support tickets.
     */
    public function index(Request $request)
    {
        $query = SupportTicket::where('user_id', $request->user()->id);

        $status = $request->get('status');
        if ($status) {
            $query->byStatus($status);
        }

        $tickets = $query->latest()->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Retailer: Show a single own ticket.
     */
    public function show($id, Request $request)
    {
        $ticket = SupportTicket::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with('respondedBy')
            ->first();

        if (!$ticket) {
            return response()->json(['message' => 'Ticket not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Admin: List all support tickets with optional filters.
     */
    public function adminIndex(Request $request)
    {
        $query = SupportTicket::with('user')->latest();

        if ($request->has('status') && $request->status !== '') {
            $query->byStatus($request->status);
        }

        if ($request->has('category') && $request->category !== '') {
            $query->byCategory($request->category);
        }

        if ($request->has('user_type') && $request->user_type !== '') {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('role', $request->user_type);
            });
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q2) use ($search) {
                      $q2->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $tickets = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $tickets->items(),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    /**
     * Admin: Show a single support ticket.
     */
    public function adminShow($id)
    {
        $ticket = SupportTicket::with(['user', 'respondedBy'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $ticket,
        ]);
    }

    /**
     * Admin: Update ticket status and/or add response.
     */
    public function adminUpdate(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
            'admin_response' => 'nullable|string',
        ]);

        $ticket->status = $validated['status'];

        if (isset($validated['admin_response'])) {
            $ticket->admin_response = $validated['admin_response'];
            $ticket->responded_by = $request->user()->id;
            $ticket->responded_at = now();
        }

        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Ticket updated successfully',
            'data' => $ticket->load(['user', 'respondedBy']),
        ]);
    }

    /**
     * Admin: Add a response to a support ticket.
     */
    public function adminRespond(Request $request, $id)
    {
        $ticket = SupportTicket::findOrFail($id);

        $validated = $request->validate([
            'admin_response' => 'required|string',
            'status' => 'nullable|in:open,in_progress,resolved,closed',
        ]);

        $ticket->admin_response = $validated['admin_response'];
        $ticket->responded_by = $request->user()->id;
        $ticket->responded_at = now();

        if (isset($validated['status'])) {
            $ticket->status = $validated['status'];
        }

        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Response saved successfully',
            'data' => $ticket->load(['user', 'respondedBy']),
        ]);
    }

    /**
     * Admin: Delete a support ticket.
     */
    public function adminDestroy($id)
    {
        $ticket = SupportTicket::findOrFail($id);

        if ($ticket->attachment) {
            Storage::disk('public')->delete($ticket->attachment);
        }

        $ticket->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ticket deleted successfully',
        ]);
    }
}
