<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Admin - Bookings",
 *     description="Admin booking management endpoints"
 * )
 */
class AdminBookingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/bookings",
     *     summary="List all bookings",
     *     tags={"Admin - Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","confirmed","cancelled","completed","no_show","rejected"})),
     *     @OA\Parameter(name="payment_status", in="query", @OA\Schema(type="string", enum={"pending","processing","paid","failed","refunded"})),
     *     @OA\Parameter(name="provider", in="query", @OA\Schema(type="string", enum={"ownerrez","hotelbeds"})),
     *     @OA\Parameter(name="search", in="query", description="Search by reference, email, or last name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date", example="2027-01-01")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date", example="2027-12-31")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", example=20)),
     *     @OA\Response(response=200, description="Paginated booking list"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = Booking::with('user:id,first_name,last_name,email');

        if ($request->has('status')) {
            $query->where('booking_status', $request->status);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('booking_reference', 'like', "%{$search}%")
                  ->orWhere('holder_email', 'like', "%{$search}%")
                  ->orWhere('holder_last_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json(['success' => true, 'data' => $bookings]);
    }

    /**
     * @OA\Get(
     *     path="/admin/bookings/{id}",
     *     summary="Get single booking details",
     *     tags={"Admin - Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Booking details"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        $booking = Booking::with('user:id,first_name,last_name,email,phone')->findOrFail($id);
        return response()->json(['success' => true, 'data' => $booking]);
    }

    /**
     * @OA\Put(
     *     path="/admin/bookings/{id}/status",
     *     summary="Update booking status and/or add internal notes",
     *     tags={"Admin - Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="booking_status", type="string", enum={"pending","confirmed","cancelled","completed","no_show","rejected"}),
     *         @OA\Property(property="payment_status", type="string", enum={"pending","processing","paid","failed","refunded","partially_refunded"}),
     *         @OA\Property(property="internal_notes", type="string", example="Called guest to confirm.")
     *     )),
     *     @OA\Response(response=200, description="Booking updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $validated = $request->validate([
            'booking_status' => 'sometimes|in:pending,confirmed,cancelled,completed,no_show,rejected',
            'payment_status' => 'sometimes|in:pending,processing,paid,failed,refunded,partially_refunded',
            'internal_notes' => 'sometimes|nullable|string|max:2000',
        ]);

        $updates = array_filter($validated, fn($v) => $v !== null);

        if (isset($validated['booking_status']) && $validated['booking_status'] === 'cancelled') {
            $updates['cancelled_at']  = now();
            $updates['cancelled_by']  = 'admin';
        }

        $booking->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Booking updated successfully',
            'data'    => $booking,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/bookings/export/csv",
     *     summary="Export bookings as CSV",
     *     tags={"Admin - Bookings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="CSV file download")
     * )
     */
    public function exportCsv(Request $request)
    {
        $query = Booking::query();

        if ($request->has('status')) {
            $query->where('booking_status', $request->status);
        }
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $bookings = $query->orderBy('created_at', 'desc')->limit(5000)->get([
            'booking_reference', 'provider', 'holder_first_name', 'holder_last_name',
            'holder_email', 'property_name', 'property_city', 'check_in_date',
            'check_out_date', 'nights', 'total_adults', 'total_price', 'currency',
            'booking_status', 'payment_status', 'created_at',
        ]);

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="bookings_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($bookings) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Reference', 'Provider', 'First Name', 'Last Name', 'Email',
                'Property', 'City', 'Check In', 'Check Out', 'Nights',
                'Adults', 'Total Price', 'Currency', 'Booking Status', 'Payment Status', 'Created At',
            ]);
            foreach ($bookings as $b) {
                fputcsv($handle, $b->toArray());
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
