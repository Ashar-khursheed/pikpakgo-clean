<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripeService
{
    private $secretKey;
    private $baseUrl;

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret_key') ?? env('STRIPE_SECRET_KEY', '');
        $this->baseUrl = 'https://api.stripe.com/v1';
    }

    /**
     * Get headers for Stripe API requests.
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];
    }

    /**
     * Create a Stripe Checkout Session for a booking.
     */
    public function createCheckoutSession(Booking $booking, string $successUrl, string $cancelUrl): array
    {
        if (empty($this->secretKey) || env('MOCK_SERVICES', true)) {
            return [
                'success' => true,
                'session_id' => 'cs_test_mock_' . uniqid(),
                'url' => route('health') . '?mock_stripe_checkout=' . $booking->booking_reference,
            ];
        }

        try {
            $url = "{$this->baseUrl}/checkout/sessions";

            $payload = [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'payment_method_types' => ['card'],
                'mode' => 'payment',
                'client_reference_id' => $booking->booking_reference,
                'customer_email' => $booking->holder_email,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($booking->currency ?? 'usd'),
                            'product_data' => [
                                'name' => $booking->property_name ?? 'Booking Reservation',
                                'description' => "Booking reference: {$booking->booking_reference}",
                            ],
                            'unit_amount' => (int)round($booking->total_price * 100), // Stripe expects cents
                        ],
                        'quantity' => 1,
                    ]
                ],
                'metadata' => [
                    'booking_reference' => $booking->booking_reference,
                    'booking_id' => $booking->id,
                ],
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->asForm()
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'session_id' => $data['id'],
                    'url' => $data['url'],
                ];
            }

            Log::error('Stripe createCheckoutSession error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Failed to create Stripe checkout session',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe createCheckoutSession exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create a Stripe Payment Intent.
     */
    public function createPaymentIntent(float $amount, string $currency, array $metadata = []): array
    {
        if (empty($this->secretKey) || env('MOCK_SERVICES', true)) {
            return [
                'success' => true,
                'client_secret' => 'pi_mock_secret_' . uniqid(),
                'payment_intent_id' => 'pi_mock_' . uniqid(),
            ];
        }

        try {
            $url = "{$this->baseUrl}/payment_intents";

            $payload = [
                'amount' => (int)round($amount * 100),
                'currency' => strtolower($currency),
                'metadata' => $metadata,
            ];

            $response = Http::withHeaders($this->getHeaders())
                ->asForm()
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'payment_intent_id' => $data['id'],
                    'client_secret' => $data['client_secret'],
                    'status' => $data['status'],
                ];
            }

            Log::error('Stripe createPaymentIntent error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Failed to create Stripe payment intent',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe createPaymentIntent exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Refund a Payment Intent.
     */
    public function refundPayment(string $paymentIntentId, ?float $amount = null): array
    {
        if (empty($this->secretKey) || env('MOCK_SERVICES', true) || str_starts_with($paymentIntentId, 'pi_mock_')) {
            return [
                'success' => true,
                'refund_id' => 're_mock_' . uniqid(),
            ];
        }

        try {
            $url = "{$this->baseUrl}/refunds";

            $payload = [
                'payment_intent' => $paymentIntentId,
            ];

            if ($amount !== null) {
                $payload['amount'] = (int)round($amount * 100);
            }

            $response = Http::withHeaders($this->getHeaders())
                ->asForm()
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'refund_id' => $data['id'],
                    'status' => $data['status'],
                ];
            }

            Log::error('Stripe refundPayment error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json()['error']['message'] ?? 'Failed to refund payment on Stripe',
            ];
        } catch (\Exception $e) {
            Log::error('Stripe refundPayment exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
