<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BookingConfirmationMail;
use App\Mail\PaymentReceiptMail;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Payments",
 *     description="Payment processing for guest and authenticated users"
 * )
 */
class PaymentController extends Controller
{
    protected $authorizeNetService;

    public function __construct(\App\Services\AuthorizeNetService $authorizeNetService)
    {
        $this->authorizeNetService = $authorizeNetService;
    }

    /**
     * @OA\Post(
     *     path="/payments/guest/process",
     *     summary="Process payment for a guest booking",
     *     tags={"Payments"},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"booking_reference","payment_method","card_number","card_holder_name","card_expiry_month","card_expiry_year","card_cvv","billing_first_name","billing_last_name","billing_email","billing_address","billing_city","billing_country","billing_postal_code"},
     *         @OA\Property(property="booking_reference", type="string", example="PKG-ABCD1234"),
     *         @OA\Property(property="payment_method", type="string", enum={"credit_card","debit_card"}),
     *         @OA\Property(property="card_number", type="string", example="4111111111111111"),
     *         @OA\Property(property="card_holder_name", type="string", example="John Doe"),
     *         @OA\Property(property="card_expiry_month", type="string", example="12"),
     *         @OA\Property(property="card_expiry_year", type="string", example="2027"),
     *         @OA\Property(property="card_cvv", type="string", example="123"),
     *         @OA\Property(property="billing_first_name", type="string"),
     *         @OA\Property(property="billing_last_name", type="string"),
     *         @OA\Property(property="billing_email", type="string", format="email"),
     *         @OA\Property(property="billing_address", type="string"),
     *         @OA\Property(property="billing_city", type="string"),
     *         @OA\Property(property="billing_country", type="string"),
     *         @OA\Property(property="billing_postal_code", type="string")
     *     )),
     *     @OA\Response(response=200, description="Payment successful"),
     *     @OA\Response(response=400, description="Payment failed or already paid")
     * )
     */
    public function processGuestPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_reference' => 'required|string|exists:bookings,booking_reference',
            'payment_method' => 'required|in:credit_card,debit_card',
            'card_number' => 'required|string',
            'card_holder_name' => 'required|string',
            'card_expiry_month' => 'required|string|size:2',
            'card_expiry_year' => 'required|string|size:4',
            'card_cvv' => 'required|string|size:3',
            'billing_first_name' => 'required|string',
            'billing_last_name' => 'required|string',
            'billing_email' => 'required|email',
            'billing_address' => 'required|string',
            'billing_city' => 'required|string',
            'billing_state' => 'nullable|string',
            'billing_country' => 'required|string',
            'billing_postal_code' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $booking = Booking::where('booking_reference', $request->booking_reference)->firstOrFail();
            
            // Check if already paid
            if ($booking->payment_status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'Booking already paid'
                ], 400);
            }
            
            // Generate transaction ID
            $transactionId = 'TXN-' . strtoupper(Str::random(16));
            
            // Create payment transaction record
            $transaction = PaymentTransaction::create([
                'transaction_id' => $transactionId,
                'booking_id' => $booking->id,
                'guest_session_id' => $booking->guest_session_id,
                'payment_gateway' => 'authorize_net',
                'amount' => $booking->total_price,
                'currency' => $booking->currency,
                'transaction_type' => 'payment',
                'payment_method' => $request->payment_method,
                'card_brand' => $this->detectCardBrand($request->card_number),
                'card_last_four' => substr($request->card_number, -4),
                'card_expiry_month' => $request->card_expiry_month,
                'card_expiry_year' => $request->card_expiry_year,
                'card_holder_name' => $request->card_holder_name,
                'billing_first_name' => $request->billing_first_name,
                'billing_last_name' => $request->billing_last_name,
                'billing_email' => $request->billing_email,
                'billing_address' => $request->billing_address,
                'billing_city' => $request->billing_city,
                'billing_state' => $request->billing_state,
                'billing_country' => $request->billing_country,
                'billing_postal_code' => $request->billing_postal_code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'pending',
            ]);
            
            // TODO: Integrate with Authorize.Net API
            // For now, simulate successful payment
            $paymentResult = $this->processWithAuthorizeNet($transaction, [
                'card_number' => $request->card_number,
                'card_cvv' => $request->card_cvv,
            ]);
            
            if ($paymentResult['success']) {
                // Update transaction
                $transaction->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $paymentResult['transaction_id'] ?? null,
                    'gateway_response_code' => $paymentResult['response_code'] ?? null,
                    'gateway_response_message' => $paymentResult['message'] ?? 'Payment successful',
                    'processed_at' => now(),
                ]);
                
                // Update booking
                $booking->update([
                    'payment_status' => 'paid',
                    'payment_transaction_id' => $transactionId,
                    'paid_amount' => $booking->total_price,
                    'paid_at' => now(),
                    'booking_status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);

                // Send confirmation + receipt emails + in-app notification
                try {
                    Mail::to($booking->holder_email)->queue(new BookingConfirmationMail($booking));
                    Mail::to($booking->holder_email)->queue(new PaymentReceiptMail($booking, $transaction));
                    if ($booking->user_id) {
                        UserNotification::notify(
                            $booking->user_id,
                            'booking_confirmed',
                            'Booking Confirmed!',
                            "Your booking {$booking->booking_reference} for {$booking->property_name} is confirmed.",
                            ['booking_reference' => $booking->booking_reference]
                        );
                    }
                } catch (\Exception $mailEx) {
                    Log::warning('Post-payment notification failed: ' . $mailEx->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'data' => [
                        'transaction_id' => $transactionId,
                        'booking_reference' => $booking->booking_reference,
                        'amount' => $booking->total_price,
                        'currency' => $booking->currency,
                    ]
                ]);
                
            } else {
                // Update transaction as failed
                $transaction->update([
                    'status' => 'failed',
                    'gateway_response_code' => $paymentResult['response_code'] ?? 'ERROR',
                    'gateway_response_message' => $paymentResult['message'] ?? 'Payment failed',
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => $paymentResult['message'] ?? 'Payment failed'
                ], 400);
            }
            
        } catch (\Exception $e) {
            Log::error('Payment processing error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed'
            ], 500);
        }
    }
    
    /**
     * @OA\Post(
     *     path="/payments/process",
     *     summary="Process payment for an authenticated user booking",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"booking_reference","payment_method","card_number","card_holder_name","card_expiry_month","card_expiry_year","card_cvv"},
     *         @OA\Property(property="booking_reference", type="string", example="PKG-ABCD1234"),
     *         @OA\Property(property="payment_method", type="string", enum={"credit_card","debit_card"}),
     *         @OA\Property(property="card_number", type="string", example="4111111111111111"),
     *         @OA\Property(property="card_holder_name", type="string"),
     *         @OA\Property(property="card_expiry_month", type="string", example="12"),
     *         @OA\Property(property="card_expiry_year", type="string", example="2027"),
     *         @OA\Property(property="card_cvv", type="string", example="123")
     *     )),
     *     @OA\Response(response=200, description="Payment successful"),
     *     @OA\Response(response=400, description="Payment failed"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function processPayment(Request $request)
    {
        // Similar to processGuestPayment but with user_id
        $validator = Validator::make($request->all(), [
            'booking_reference' => 'required|string',
            'payment_method' => 'required|in:credit_card,debit_card',
            'card_number' => 'required|string',
            'card_holder_name' => 'required|string',
            'card_expiry_month' => 'required|string|size:2',
            'card_expiry_year' => 'required|string|size:4',
            'card_cvv' => 'required|string|size:3',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $user = auth()->user();
            $booking = Booking::where('booking_reference', $request->booking_reference)
                ->where('user_id', $user->id)
                ->firstOrFail();
            
            // Use user's saved billing info
            $transactionId = 'TXN-' . strtoupper(Str::random(16));
            
            $transaction = PaymentTransaction::create([
                'transaction_id' => $transactionId,
                'booking_id' => $booking->id,
                'user_id' => $user->id,
                'payment_gateway' => 'authorize_net',
                'amount' => $booking->total_price,
                'currency' => $booking->currency,
                'transaction_type' => 'payment',
                'payment_method' => $request->payment_method,
                'card_brand' => $this->detectCardBrand($request->card_number),
                'card_last_four' => substr($request->card_number, -4),
                'card_holder_name' => $request->card_holder_name,
                'billing_email' => $user->email,
                'status' => 'pending',
            ]);
            
            $paymentResult = $this->processWithAuthorizeNet($transaction, [
                'card_number' => $request->card_number,
                'card_cvv' => $request->card_cvv,
            ]);
            
            if ($paymentResult['success']) {
                $transaction->update([
                    'status' => 'success',
                    'gateway_transaction_id' => $paymentResult['transaction_id'],
                    'processed_at' => now(),
                ]);
                
                $booking->update([
                    'payment_status' => 'paid',
                    'payment_transaction_id' => $transactionId,
                    'paid_amount' => $booking->total_price,
                    'paid_at' => now(),
                    'booking_status' => 'confirmed',
                    'confirmed_at' => now(),
                ]);

                try {
                    Mail::to($booking->holder_email)->queue(new BookingConfirmationMail($booking));
                    Mail::to($booking->holder_email)->queue(new PaymentReceiptMail($booking, $transaction));
                } catch (\Exception $mailEx) {
                    Log::warning('Confirmation email failed: ' . $mailEx->getMessage());
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment successful',
                    'data' => [
                        'transaction_id' => $transactionId,
                        'booking_reference' => $booking->booking_reference,
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Payment failed'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Payment error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed'
            ], 500);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/payments/{transactionId}/status",
     *     summary="Get payment transaction status",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="transactionId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Transaction status"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function getPaymentStatus($transactionId)
    {
        try {
            $transaction = PaymentTransaction::where('transaction_id', $transactionId)->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->transaction_id,
                    'status' => $transaction->status,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'processed_at' => $transaction->processed_at,
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }
    }
    
    /**
     * @OA\Get(
     *     path="/payments/guest/{transactionId}/status",
     *     summary="Get guest payment transaction status",
     *     tags={"Payments"},
     *     @OA\Parameter(name="transactionId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Transaction status"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function getGuestPaymentStatus($transactionId)
    {
        return $this->getPaymentStatus($transactionId);
    }
    
    /**
     * @OA\Get(
     *     path="/payments/history",
     *     summary="Get authenticated user's payment history",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Paginated payment history"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function getPaymentHistory(Request $request)
    {
        try {
            $user = auth()->user();
            
            $transactions = PaymentTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
            
            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment history'
            ], 500);
        }
    }
    
    /**
     * Authorize.Net webhook handler with HMAC-SHA512 signature verification
     */
    public function authorizeNetWebhook(Request $request)
    {
        // Verify Authorize.Net webhook signature
        $signatureKey = config('services.authorize_net.webhook_secret', env('AUTHORIZE_NET_WEBHOOK_SECRET', ''));

        if ($signatureKey) {
            $receivedSig = $request->header('X-ANET-Signature');
            if (!$receivedSig) {
                Log::warning('Authorize.Net webhook: missing signature header');
                return response()->json(['error' => 'Missing signature'], 401);
            }

            // Authorize.Net sends "sha512=<hex>"
            $rawBody   = $request->getContent();
            $computed  = 'sha512=' . strtoupper(hash_hmac('sha512', $rawBody, $signatureKey));

            if (!hash_equals($computed, strtoupper($receivedSig))) {
                Log::warning('Authorize.Net webhook: invalid signature');
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        $payload   = $request->json()->all();
        $eventType = $payload['eventType'] ?? '';

        Log::info('Authorize.Net webhook', ['event' => $eventType, 'payload' => $payload]);

        // Handle payment events
        match ($eventType) {
            'net.authorize.payment.authcapture.created',
            'net.authorize.payment.capture.created' => $this->handlePaymentCapture($payload),

            'net.authorize.payment.void.created'    => $this->handlePaymentVoid($payload),

            'net.authorize.payment.refund.created'  => $this->handlePaymentRefund($payload),

            default => null,
        };

        return response()->json(['success' => true]);
    }

    protected function handlePaymentCapture(array $payload): void
    {
        $transactionId = $payload['payload']['id'] ?? null;
        if (!$transactionId) return;

        $transaction = PaymentTransaction::where('gateway_transaction_id', $transactionId)->first();
        if ($transaction && $transaction->status !== 'success') {
            $transaction->update(['status' => 'success', 'processed_at' => now()]);
            $booking = Booking::find($transaction->booking_id);
            if ($booking) {
                $booking->update([
                    'payment_status' => 'paid',
                    'paid_amount'    => $transaction->amount,
                    'paid_at'        => now(),
                    'booking_status' => 'confirmed',
                    'confirmed_at'   => now(),
                ]);
            }
        }
    }

    protected function handlePaymentVoid(array $payload): void
    {
        $transactionId = $payload['payload']['id'] ?? null;
        if (!$transactionId) return;

        $transaction = PaymentTransaction::where('gateway_transaction_id', $transactionId)->first();
        if ($transaction) {
            $transaction->update(['status' => 'voided']);
        }
    }

    protected function handlePaymentRefund(array $payload): void
    {
        $transactionId = $payload['payload']['id'] ?? null;
        if (!$transactionId) return;

        $transaction = PaymentTransaction::where('gateway_transaction_id', $transactionId)->first();
        if ($transaction) {
            $transaction->update(['status' => 'refunded', 'transaction_type' => 'refund']);
            $booking = Booking::find($transaction->booking_id);
            if ($booking) {
                $booking->update([
                    'payment_status' => 'refunded',
                    'refund_amount'  => $transaction->amount,
                ]);
            }
        }
    }
    
    /**
     * Process payment with Authorize.Net
     * TODO: Implement actual Authorize.Net integration
     */
    protected function processWithAuthorizeNet($transaction, $cardData)
    {
        $amount = $transaction->amount;
        $refId = $transaction->transaction_id;
        
        $cardDetails = [
            'cardNumber' => $cardData['card_number'],
            'expirationDate' => $transaction->card_expiry_year . '-' . $transaction->card_expiry_month,
            'cvv' => $cardData['card_cvv'],
        ];

        return $this->authorizeNetService->chargeCreditCard($cardDetails, $amount, $refId);
    }
    
    /**
     * Detect card brand from card number
     */
    protected function detectCardBrand($cardNumber)
    {
        $cardNumber = preg_replace('/\s+/', '', $cardNumber);
        
        if (preg_match('/^4/', $cardNumber)) {
            return 'Visa';
        } elseif (preg_match('/^5[1-5]/', $cardNumber)) {
            return 'Mastercard';
        } elseif (preg_match('/^3[47]/', $cardNumber)) {
            return 'American Express';
        } elseif (preg_match('/^6(?:011|5)/', $cardNumber)) {
            return 'Discover';
        }
        
        return 'Unknown';
    }
}
