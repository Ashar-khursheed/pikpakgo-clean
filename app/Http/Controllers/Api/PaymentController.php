<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Process guest payment
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
     * Process authenticated user payment
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
     * Get payment status
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
     * Get guest payment status
     */
    public function getGuestPaymentStatus($transactionId)
    {
        return $this->getPaymentStatus($transactionId);
    }
    
    /**
     * Get user payment history
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
     * Authorize.Net webhook handler
     */
    public function authorizeNetWebhook(Request $request)
    {
        // TODO: Implement webhook signature verification
        // TODO: Handle payment notifications from Authorize.Net
        
        Log::info('Authorize.Net webhook received', $request->all());
        
        return response()->json(['success' => true]);
    }
    
    /**
     * Process payment with Authorize.Net
     * TODO: Implement actual Authorize.Net integration
     */
    protected function processWithAuthorizeNet($transaction, $cardData)
    {
        // This is a placeholder - implement actual Authorize.Net API calls
        // For now, simulate successful payment
        
        return [
            'success' => true,
            'transaction_id' => 'AUTH-' . Str::random(12),
            'response_code' => '1',
            'message' => 'This transaction has been approved.'
        ];
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
