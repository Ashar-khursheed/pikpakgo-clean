<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class AdminBookingController extends Controller
{
    /**
     * List all bookings
     */
    public function index(Request $request)
    {
        $query = Booking::query();

        if ($request->has('status')) {
            $query->where('booking_status', $request->status);
        }
        
        if ($request->has('provider')) {
            $query->where('provider', $request->provider);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                  ->orWhere('holder_email', 'like', "%{$search}%")
                  ->orWhere('holder_last_name', 'like', "%{$search}%");
            });
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    /**
     * Show single booking
     */
    public function show($id)
    {
        $booking = Booking::findOrFail($id);
        return response()->json(['success' => true, 'data' => $booking]);
    }

    /**
     * Update booking status (Manual Workflow)
     */
    public function updateStatus(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);
        
        $validated = $request->validate([
            'booking_status' => 'required|in:pending,confirmed,cancelled,completed',
            'payment_status' => 'nullable|in:pending,paid,refunded,failed',
            'admin_notes' => 'nullable|string' // Use existing column or add via migration if needed, assuming one exists or using generic logic
        ]);

        // If 'admin_notes' is passed but doesn't exist in DB, it might error. 
        // For now, we'll try to update only valid columns.
        // Checking Booking model via view_file showed 'special_requests' but not 'admin_notes'.
        // We will assume generic update for now, or use 'special_requests' if admin writes there (unlikely).
        // Let's stick to status updates for now.

        $booking->update([
            'booking_status' => $validated['booking_status'],
            'payment_status' => $validated['payment_status'] ?? $booking->payment_status
        ]);

        return response()->json([
            'success' => true, 
            'message' => 'Booking status updated successfully', 
            'data' => $booking
        ]);
    }
}
