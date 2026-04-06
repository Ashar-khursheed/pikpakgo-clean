<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\UserNotification;
use App\Services\OwnerRezService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Dispatched after a payment is captured on our platform.
 *
 * Responsibility: forward the booking to the upstream provider (OwnerRez) using
 * their Channel API with "channel collected" payment, then record the payout.
 *
 * Financial model:
 *   - We already collected  booking->total_price  from the customer.
 *   - OwnerRez will invoice us  booking->base_price  via their billing system.
 *   - Our net profit = booking->markup_amount  (stored at booking creation).
 */
class ConfirmBookingWithProvider implements ShouldQueue
{
    use Queueable;

    public int $tries   = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(public readonly int $bookingId) {}

    public function handle(OwnerRezService $ownerrez): void
    {
        $booking = Booking::find($this->bookingId);

        if (!$booking) {
            Log::error("ConfirmBookingWithProvider: booking #{$this->bookingId} not found");
            return;
        }

        // Only process paid bookings that haven't been sent yet
        if ($booking->payment_status !== 'paid') {
            Log::warning("ConfirmBookingWithProvider: booking #{$this->bookingId} not yet paid, skipping");
            return;
        }

        if (in_array($booking->provider_payout_status, ['sent', 'not_required'])) {
            Log::info("ConfirmBookingWithProvider: booking #{$this->bookingId} already processed");
            return;
        }

        try {
            if ($booking->provider === 'ownerrez') {
                $this->sendToOwnerRez($booking, $ownerrez);
            } else {
                // 'direct' provider — we own the property, no upstream booking needed
                $booking->update([
                    'provider_payout_status' => 'not_required',
                    'provider_payout_at'     => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("ConfirmBookingWithProvider: failed for booking #{$this->bookingId} — {$e->getMessage()}");

            $booking->update([
                'provider_payout_status' => 'failed',
                'provider_payout_error'  => $e->getMessage(),
            ]);

            // Notify admin in-app
            $this->notifyAdminPayoutFailed($booking, $e->getMessage());

            throw $e; // triggers job retry
        }
    }

    // ---------------------------------------------------------------

    private function sendToOwnerRez(Booking $booking, OwnerRezService $ownerrez): void
    {
        // Step 1 — get a fresh quote so we have orderItems + paymentSchedule
        $quote = $ownerrez->getPricing($booking->property_code, [
            'checkin'  => $booking->check_in_date,
            'checkout' => $booking->check_out_date,
            'guests'   => $booking->total_adults,
        ]);

        if (!$quote['success']) {
            throw new \RuntimeException('OwnerRez quote failed: ' . ($quote['message'] ?? 'unknown'));
        }

        $order     = $quote['data']['quoteResponseDetails']['orderList']['order'] ?? [];
        $currency  = $order['currency'] ?? $booking->currency;

        $rawItems  = $order['orderItemList']['orderItem'] ?? [];
        if (isset($rawItems['feeType'])) $rawItems = [$rawItems];
        $orderItems = array_map(fn($i) => array_merge($i, ['currency' => $currency]), $rawItems);

        $rawSched  = $order['paymentSchedule']['paymentScheduleItemList']['paymentScheduleItem'] ?? [];
        if (isset($rawSched['amount'])) $rawSched = [$rawSched];
        $scheduleItems = array_map(fn($s) => [
            'amount'   => $s['amount'],
            'dueDate'  => $s['dueDate'],
            'currency' => $currency,
        ], $rawSched);

        // Step 2 — create the booking on OwnerRez
        // We are the channel; payment was collected by us, not by OwnerRez.
        $payload = [
            'listingExternalId'    => $booking->property_code,
            'unitExternalId'       => $booking->property_code,
            'arrivalDate'          => $booking->check_in_date,
            'departureDate'        => $booking->check_out_date,
            'traveler'             => [
                'firstName'    => $booking->holder_first_name,
                'lastName'     => $booking->holder_last_name,
                'emailAddress' => $booking->holder_email,
                'phoneNumbers' => [$booking->holder_phone],
            ],
            'travelers'            => [
                'adults'   => (int) $booking->total_adults,
                'children' => (int) $booking->total_children,
                'pets'     => 0,
            ],
            'orderItems'           => $orderItems,
            'paymentScheduleItems' => $scheduleItems,
            // Mark that payment was already collected by us (the channel)
            'channelCollectedPayment' => true,
            'channelPaymentAmount'    => (float) $booking->base_price,
            'channelPaymentCurrency'  => $booking->currency,
        ];

        $result = $ownerrez->createBooking($payload);

        if (!$result['success']) {
            throw new \RuntimeException('OwnerRez booking creation failed: ' . ($result['error'] ?? json_encode($result)));
        }

        // Step 3 — save OwnerRez reference back to our booking
        $providerRef = $result['data']['externalId']
                    ?? $result['data']['bookingId']
                    ?? null;

        $booking->update([
            'provider_booking_reference' => $providerRef,
            'provider_payout_status'     => 'sent',
            'provider_payout_at'         => now(),
            'provider_payout_error'      => null,
            'net_profit'                 => $booking->markup_amount,
        ]);

        Log::info("ConfirmBookingWithProvider: booking #{$booking->id} sent to OwnerRez as {$providerRef}");
    }

    private function notifyAdminPayoutFailed(Booking $booking, string $reason): void
    {
        // Notify all admin users
        $adminIds = \App\Models\User::where('user_type', 'admin')->pluck('id');

        foreach ($adminIds as $adminId) {
            UserNotification::notify(
                $adminId,
                'payout_failed',
                'Provider Payout Failed',
                "Booking {$booking->booking_reference} could not be sent to {$booking->provider}: {$reason}",
                [
                    'booking_reference' => $booking->booking_reference,
                    'booking_id'        => $booking->id,
                ]
            );
        }
    }
}
