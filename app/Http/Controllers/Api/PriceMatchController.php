<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PriceMatchClaim;
use App\Models\PaymentTransaction;
use App\Services\StripeService;
use App\Services\AuthorizeNetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="Price Match Claims",
 *     description="Price match claim requests, verification and processing"
 * )
 */
class PriceMatchController extends Controller
{
    /**
     * @OA\Post(
     *     path="/price-match/claim",
     *     summary="Submit a price-match claim for a booking",
     *     tags={"Price Match Claims"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"booking_reference", "competitor_url", "competitor_price"},
     *         @OA\Property(property="booking_reference", type="string", example="PKG-ABCD1234"),
     *         @OA\Property(property="competitor_url", type="string", format="url", example="https://cheaptravel.com/miami-hotel"),
     *         @OA\Property(property="competitor_price", type="number", format="float", example=180.00),
     *         @OA\Property(property="screenshot", type="string", format="binary", description="Optional screenshot of the cheaper deal")
     *     )),
     *     @OA\Response(response=201, description="Claim submitted successfully"),
     *     @OA\Response(response=400, description="Validation errors or price not lower")
     * )
     */
    public function submitClaim(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_reference' => 'required|string|exists:bookings,booking_reference',
            'competitor_url' => 'required|url',
            'competitor_price' => 'required|numeric|min:0.01',
            'screenshot' => 'nullable|image|max:5120', // Max 5MB image
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $booking = Booking::where('booking_reference', $request->booking_reference)->firstOrFail();

            if ($request->competitor_price >= $booking->total_price) {
                return response()->json([
                    'success' => false,
                    'message' => 'Competitor price must be lower than your booking total price.'
                ], 400);
            }

            $screenshotPath = null;
            if ($request->hasFile('screenshot')) {
                $screenshotPath = $request->file('screenshot')->store('claims', 'public');
            }

            $claim = PriceMatchClaim::create([
                'user_id' => auth()->id(),
                'booking_reference' => $request->booking_reference,
                'competitor_url' => $request->competitor_url,
                'competitor_price' => $request->competitor_price,
                'screenshot_path' => $screenshotPath,
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Price match claim submitted successfully.',
                'data' => $claim
            ], 201);

        } catch (\Exception $e) {
            Log::error('Price match claim submission error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit price match claim.'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/admin/price-match/claims",
     *     summary="List price match claims (Admin)",
     *     tags={"Price Match Claims"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending", "approved", "rejected"})),
     *     @OA\Response(response=200, description="Claims retrieved successfully")
     * )
     */
    public function adminListClaims(Request $request)
    {
        $query = PriceMatchClaim::with(['user:id,first_name,last_name,email', 'booking']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $claims = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $claims
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/price-match/claims/{id}/verify",
     *     summary="Approve or reject price match claim and issue refund (Admin)",
     *     tags={"Price Match Claims"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status"},
     *         @OA\Property(property="status", type="string", enum={"approved", "rejected"}),
     *         @OA\Property(property="verification_notes", type="string", example="Verified cheaper deal on booking.com")
     *     )),
     *     @OA\Response(response=200, description="Verification processed successfully"),
     *     @OA\Response(response=404, description="Claim not found")
     * )
     */
    public function adminVerifyClaim(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'verification_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $claim = PriceMatchClaim::findOrFail($id);

            if ($claim->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This claim has already been verified.'
                ], 400);
            }

            $booking = Booking::where('booking_reference', $claim->booking_reference)->firstOrFail();

            if ($request->status === 'rejected') {
                $claim->update([
                    'status' => 'rejected',
                    'verification_notes' => $request->verification_notes,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Price match claim rejected.',
                    'data' => $claim
                ]);
            }

            // Approved: calculate refund amount
            $refundAmount = (float)($booking->total_price - $claim->competitor_price);
            if ($refundAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Calculated refund amount is zero or negative.'
                ], 400);
            }

            // Look up original transaction to determine payment gateway
            $origTxn = PaymentTransaction::where('booking_id', $booking->id)
                ->where('status', 'success')
                ->where('transaction_type', 'payment')
                ->first();

            $gatewayUsed = $origTxn ? $origTxn->payment_gateway : 'stripe';
            $gatewayTxnId = $origTxn ? $origTxn->gateway_transaction_id : null;

            $gatewayRefundSuccess = false;
            $gatewayRefundId = 'REF_PM_' . strtoupper(Str::random(12));
            $refundMessage = 'Simulated refund for Price Match Claim';

            // Attempt live gateway refund if not mocked
            if ($gatewayTxnId && !env('MOCK_SERVICES', true)) {
                if ($gatewayUsed === 'stripe') {
                    $stripeService = app(StripeService::class);
                    $result = $stripeService->refundPayment($gatewayTxnId, $refundAmount);
                    if ($result['success']) {
                        $gatewayRefundSuccess = true;
                        $gatewayRefundId = $result['refund_id'];
                        $refundMessage = 'Stripe refund successful';
                    } else {
                        $refundMessage = 'Stripe refund failed: ' . $result['message'];
                    }
                } elseif ($gatewayUsed === 'authorize_net') {
                    $anetService = app(AuthorizeNetService::class);
                    // Authorize.Net requires card number for refund or transaction ID
                    $result = $anetService->refundTransaction($gatewayTxnId, $refundAmount, $origTxn->card_last_four ?? '');
                    if ($result['success']) {
                        $gatewayRefundSuccess = true;
                        $gatewayRefundId = $result['transaction_id'];
                        $refundMessage = 'Authorize.Net refund successful';
                    } else {
                        $refundMessage = 'Authorize.Net refund failed: ' . $result['message'];
                    }
                }
            } else {
                // Mocked environment
                $gatewayRefundSuccess = true;
            }

            if (!$gatewayRefundSuccess && !env('MOCK_SERVICES', true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to issue refund via payment gateway: ' . $refundMessage
                ], 500);
            }

            // Create refund payment transaction record
            $transactionId = 'TXN-' . strtoupper(Str::random(16));
            PaymentTransaction::create([
                'transaction_id' => $transactionId,
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'guest_session_id' => $booking->guest_session_id,
                'payment_gateway' => $gatewayUsed,
                'gateway_transaction_id' => $gatewayRefundId,
                'amount' => $refundAmount,
                'currency' => $booking->currency,
                'transaction_type' => 'refund',
                'payment_method' => 'refund',
                'status' => 'success',
                'gateway_response_message' => $refundMessage,
                'processed_at' => now(),
            ]);

            // Update booking details
            $booking->update([
                'payment_status' => 'partially_refunded',
                'refund_amount' => ($booking->refund_amount ?? 0) + $refundAmount,
            ]);

            $claim->update([
                'status' => 'approved',
                'refund_amount' => $refundAmount,
                'verification_notes' => $request->verification_notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Price match claim approved and refund processed successfully.',
                'data' => $claim
            ]);

        } catch (\Exception $e) {
            Log::error('Price match claim verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify price match claim.'
            ], 500);
        }
    }
}
