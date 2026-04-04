<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class OwnerRezService
{
    private $username;
    private $password;
    private $baseUrl;
    private $advertiserId;
    private $systemExternalId;

    public function __construct()
    {
        $this->username         = config('services.ownerrez.username');
        $this->password         = config('services.ownerrez.password');
        $this->baseUrl          = config('services.ownerrez.base_url');
        $this->advertiserId     = config('services.ownerrez.advertiser_id');
        $this->systemExternalId = config('services.ownerrez.system_external_id', 'PikPakGo');
    }

    /**
     * Basic Auth headers for HAXML / HAOLB JSON endpoints.
     */
    private function basicAuthHeaders(bool $xml = false): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode("{$this->username}:{$this->password}"),
            'Accept'        => $xml ? 'application/xml' : 'application/json',
            'Content-Type'  => $xml ? 'application/xml' : 'application/json',
            'User-Agent'    => 'PikPakGo/1.0',
        ];
    }

    /**
     * Build URL for XML OLB endpoints that use query-param auth.
     */
    private function olbUrl(string $endpoint): string
    {
        return "{$this->baseUrl}/haapi/haolb/{$endpoint}?type=" . urlencode($this->username)
            . "&key=" . urlencode($this->password);
    }

    // ---------------------------------------------------------------
    // Listing Index — GET /haapi/haxml/{advertiser_id}/listingindex
    // ---------------------------------------------------------------
    public function getListingIndex(): array
    {
        if (env('MOCK_SERVICES', false)) {
            return $this->getMockProperties();
        }

        if (!$this->advertiserId) {
            return ['success' => false, 'message' => 'Advertiser ID not configured.'];
        }

        try {
            return Cache::remember('ownerrez_listing_index', 3600, function () {
                $url = "{$this->baseUrl}/haapi/haxml/{$this->advertiserId}/listingindex";

                $response = Http::withHeaders($this->basicAuthHeaders(true))
                    ->timeout(30)
                    ->get($url);

                if (!$response->successful()) {
                    Log::error('OwnerRez listingIndex error', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                    ]);
                    return [
                        'success' => false,
                        'message' => 'Failed to fetch listing index',
                        'status'  => $response->status(),
                        'error'   => $response->body(),
                    ];
                }

                $xml = simplexml_load_string($response->body());

                if ($xml === false) {
                    return ['success' => false, 'message' => 'Failed to parse XML listing index'];
                }

                $listings = [];
                if (isset($xml->advertiser->listingContentIndexEntry)) {
                    foreach ($xml->advertiser->listingContentIndexEntry as $entry) {
                        $listings[] = [
                            'listingExternalId' => (string) $entry->listingExternalId,
                            'active'            => ((string) $entry->active) === 'true',
                            'lastUpdatedDate'   => (string) $entry->lastUpdatedDate,
                            'listingUrl'        => (string) $entry->listingUrl,
                        ];
                    }
                }

                return [
                    'success' => true,
                    'data'    => ['items' => $listings, 'total' => count($listings)],
                ];
            });
        } catch (\Exception $e) {
            Log::error('OwnerRez listingIndex exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------
    // Search Properties (wraps listing index, filters active)
    // ---------------------------------------------------------------
    public function searchProperties(array $params = []): array
    {
        if (env('MOCK_SERVICES', false)) {
            $mock = $this->getMockProperties();
            $items = array_map(fn($p) => ['propertyId' => $p['property_id'], 'available' => true],
                $mock['data']['properties'] ?? []);
            return ['success' => true, 'data' => ['items' => $items, 'total' => count($items)]];
        }

        $index = $this->getListingIndex();
        if (!$index['success']) {
            return $index;
        }

        $active = array_values(array_filter(
            $index['data']['items'],
            fn($p) => $p['active'] === true
        ));

        return [
            'success' => true,
            'data'    => ['items' => $active, 'total' => count($active)],
        ];
    }

    // ---------------------------------------------------------------
    // Property Details — GET /haapi/haxml/{advertiser_id}/listing/{id}
    // ---------------------------------------------------------------
    public function getPropertyDetails(string $propertyId): array
    {
        if (env('MOCK_SERVICES', false)) {
            return $this->getMockPropertyDetails($propertyId);
        }

        if (!$this->advertiserId) {
            return ['success' => false, 'message' => 'Advertiser ID not configured.'];
        }

        try {
            return Cache::remember("ownerrez_property_{$propertyId}", 86400, function () use ($propertyId) {
                $url = "{$this->baseUrl}/haapi/haxml/{$this->advertiserId}/listing/{$propertyId}";

                $response = Http::withHeaders($this->basicAuthHeaders(true))
                    ->timeout(30)
                    ->get($url);

                if ($response->successful()) {
                    $xml = simplexml_load_string($response->body());
                    if ($xml === false) {
                        return ['success' => false, 'message' => 'Failed to parse listing XML'];
                    }
                    return ['success' => true, 'data' => json_decode(json_encode($xml), true)];
                }

                return [
                    'success' => false,
                    'message' => 'Property not found',
                    'status'  => $response->status(),
                    'error'   => $response->body(),
                ];
            });
        } catch (\Exception $e) {
            Log::error('OwnerRez getPropertyDetails exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------
    // Availability — POST /haapi/haolbjson/fastavailability
    // NOTE: Requires OLB-capable channel credentials.
    //       The HaXmlSandboxMoR sandbox only supports listing content
    //       (listing index + details), not OLB booking endpoints.
    // ---------------------------------------------------------------
    public function checkAvailability(string $propertyId, array $params): array
    {
        if (!$this->advertiserId) {
            return ['success' => false, 'message' => 'Advertiser ID not configured.'];
        }

        try {
            $url = "{$this->baseUrl}/haapi/haolbjson/fastavailability";

            // Per OwnerRez docs: adults/children/pets are TOP-LEVEL fields, NOT inside a travelers object.
            // units array with unitExternalId is also required.
            $payload = [
                'requestVersion'       => '1.0',
                'systemExternalId'     => $this->systemExternalId,
                'advertiserExternalId' => $this->advertiserId,
                'listingExternalId'    => $propertyId,
                'dateRange'            => [
                    'arrivalDate'   => $params['checkin'],
                    'departureDate' => $params['checkout'],
                ],
                'adults'   => (int)($params['guests'] ?? 2),
                'children' => 0,
                'pets'     => 0,
                'units'    => [
                    ['unitExternalId' => $propertyId],
                ],
            ];

            $response = Http::withHeaders($this->basicAuthHeaders())
                ->timeout(30)
                ->post($url, $payload);

            if ($response->successful()) {
                $data        = $response->json();
                $isAvailable = $data['units'][0]['available'] ?? ($data['available'] ?? false);
                return [
                    'success' => true,
                    'data'    => ['available' => $isAvailable, 'raw_response' => $data],
                ];
            }

            // 500 from OwnerRez sandbox means OLB not enabled for this channel type
            if ($response->status() === 500) {
                return [
                    'success' => false,
                    'message' => 'Availability check requires OLB-capable channel credentials. The current sandbox (HaXmlSandboxMoR) only supports listing content endpoints.',
                    'sandbox_limitation' => true,
                ];
            }

            Log::error('OwnerRez checkAvailability error', [
                'status' => $response->status(), 'body' => $response->body(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to check availability',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('OwnerRez checkAvailability exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------
    // Pricing / Quote — POST /haapi/haolb/quote?type=X&key=Y  (XML)
    // Per docs: quote uses XML format with query-param auth (same as createbooking)
    // ---------------------------------------------------------------
    public function getPricing(string $propertyId, array $params): array
    {
        if (!$this->advertiserId) {
            return ['success' => false, 'message' => 'Advertiser ID not configured.'];
        }

        try {
            $url = $this->olbUrl('quote');

            $guests   = (int)($params['guests'] ?? 2);
            $checkin  = $params['checkin'];
            $checkout = $params['checkout'];

            // Quote uses XML format per OwnerRez docs
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><quoteRequest/>');
            $xml->addChild('documentVersion', '1.3');

            $details = $xml->addChild('quoteRequestDetails');
            $details->addChild('advertiserAssignedId', htmlspecialchars($this->advertiserId));
            $details->addChild('listingExternalId', htmlspecialchars($propertyId));
            $details->addChild('unitExternalId', htmlspecialchars($propertyId));
            $details->addChild('clientIPAddress', htmlspecialchars(request()->ip() ?? '127.0.0.1'));

            $reservation = $details->addChild('reservation');
            $reservation->addChild('numberOfAdults', $guests);
            $reservation->addChild('numberOfChildren', 0);
            $reservation->addChild('numberOfPets', 0);
            $dates = $reservation->addChild('reservationDates');
            $dates->addChild('beginDate', htmlspecialchars($checkin));
            $dates->addChild('endDate', htmlspecialchars($checkout));
            $reservation->addChild('reservationOriginationDate', now()->toIso8601String());

            $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode("{$this->username}:{$this->password}"),
                    'Content-Type'  => 'application/xml',
                    'Accept'        => 'application/xml',
                    'User-Agent'    => 'PikPakGo/1.0',
                ])
                ->timeout(30)
                ->send('POST', $url, ['body' => $xml->asXML()]);

            if ($response->successful()) {
                $res = simplexml_load_string($response->body());
                if ($res === false) {
                    return ['success' => false, 'message' => 'Failed to parse quote XML response'];
                }
                return [
                    'success' => true,
                    'data'    => json_decode(json_encode($res), true),
                    'raw_xml' => $response->body(),
                ];
            }

            Log::error('OwnerRez getPricing error', [
                'status' => $response->status(), 'body' => $response->body(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to get pricing',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('OwnerRez getPricing exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------
    // Create Booking — POST /haolb/createbooking?type=X&key=Y  (XML)
    // ---------------------------------------------------------------
    public function createBooking(array $bookingData): array
    {
        if (!$this->advertiserId) {
            return ['success' => false, 'message' => 'Advertiser ID not configured.'];
        }

        try {
            $url      = $this->olbUrl('createbooking');
            $traveler = $bookingData['traveler'] ?? [];

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><bookingRequest/>');
            $xml->addChild('documentVersion', '1.3');

            $details = $xml->addChild('bookingRequestDetails');
            $details->addChild('advertiserAssignedId', htmlspecialchars($this->advertiserId));
            $details->addChild('listingExternalId', htmlspecialchars($bookingData['listingExternalId'] ?? ''));
            $details->addChild('unitExternalId', htmlspecialchars($bookingData['unitExternalId'] ?? $bookingData['listingExternalId'] ?? ''));
            $details->addChild('clientIPAddress', htmlspecialchars(request()->ip() ?? '127.0.0.1'));

            if (!empty($bookingData['message'])) {
                $details->addChild('message', htmlspecialchars($bookingData['message']));
            }

            $inquirer = $details->addChild('inquirer');
            $inquirer->addAttribute('locale', 'en_US');
            $inquirer->addChild('firstName', htmlspecialchars($traveler['firstName'] ?? ''));
            $inquirer->addChild('lastName', htmlspecialchars($traveler['lastName'] ?? ''));
            $inquirer->addChild('emailAddress', htmlspecialchars($traveler['emailAddress'] ?? ''));
            if (!empty($traveler['phoneNumbers'][0])) {
                $inquirer->addChild('phoneNumber', htmlspecialchars($traveler['phoneNumbers'][0]));
            }

            $travelersCount = $bookingData['travelers'] ?? [];
            $reservation    = $details->addChild('reservation');
            $reservation->addChild('numberOfAdults', (int)($travelersCount['adults'] ?? 1));
            $reservation->addChild('numberOfChildren', (int)($travelersCount['children'] ?? 0));
            $reservation->addChild('numberOfPets', (int)($travelersCount['pets'] ?? 0));

            $dates = $reservation->addChild('reservationDates');
            $dates->addChild('beginDate', htmlspecialchars($bookingData['arrivalDate'] ?? ''));
            $dates->addChild('endDate', htmlspecialchars($bookingData['departureDate'] ?? ''));
            $reservation->addChild('reservationOriginationDate', now()->toIso8601String());

            // orderItemList — required; must include line items from quote response
            $orderItems = $bookingData['orderItems'] ?? [];
            if (!empty($orderItems)) {
                $orderItemList = $details->addChild('orderItemList');
                foreach ($orderItems as $item) {
                    $oi = $orderItemList->addChild('orderItem');
                    if (!empty($item['externalId'])) {
                        $oi->addChild('externalId', htmlspecialchars($item['externalId']));
                    }
                    $oi->addChild('feeType', htmlspecialchars($item['feeType'] ?? 'RENTAL'));
                    $oi->addChild('name', htmlspecialchars($item['name'] ?? ''));
                    $preTax = $oi->addChild('preTaxAmount', htmlspecialchars($item['preTaxAmount'] ?? '0.00'));
                    $preTax->addAttribute('currency', $item['currency'] ?? 'USD');
                    $oi->addChild('status', 'PENDING');
                    $oi->addChild('taxRate', $item['taxRate'] ?? '0.000000');
                    $total = $oi->addChild('totalAmount', htmlspecialchars($item['totalAmount'] ?? $item['preTaxAmount'] ?? '0.00'));
                    $total->addAttribute('currency', $item['currency'] ?? 'USD');
                }
            }

            // paymentScheduleItemList — required; from quote paymentSchedule
            $scheduleItems = $bookingData['paymentScheduleItems'] ?? [];
            if (!empty($scheduleItems)) {
                $scheduleList = $details->addChild('paymentScheduleItemList');
                foreach ($scheduleItems as $sched) {
                    $si  = $scheduleList->addChild('paymentScheduleItem');
                    $amt = $si->addChild('amount', htmlspecialchars($sched['amount'] ?? '0.00'));
                    $amt->addAttribute('currency', $sched['currency'] ?? 'USD');
                    $si->addChild('dueDate', htmlspecialchars($sched['dueDate'] ?? date('Y-m-d')));
                }
            }

            // MoR (Merchant of Record): paymentChannelMoR with paymentFormType + projectedDepositDate
            $paymentForm = $details->addChild('paymentForm');
            $mor = $paymentForm->addChild('paymentChannelMoR');
            $mor->addChild('paymentFormType', 'CHANNELMOR');
            $depositDate = date('Y-m-d', strtotime(($bookingData['arrivalDate'] ?? 'now') . ' +30 days'));
            $mor->addChild('projectedDepositDate', $bookingData['projectedDepositDate'] ?? $depositDate);

            $xmlPayload = $xml->asXML();

            $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode("{$this->username}:{$this->password}"),
                    'Content-Type'  => 'application/xml',
                    'Accept'        => 'application/xml',
                    'User-Agent'    => 'PikPakGo/1.0',
                ])
                ->timeout(30)
                ->send('POST', $url, ['body' => $xmlPayload]);

            if ($response->successful()) {
                $res = simplexml_load_string($response->body());

                // Check for XML-level errors returned with HTTP 200
                if (isset($res->errorList->error)) {
                    $errors = [];
                    foreach ($res->errorList->error as $err) {
                        $errors[] = (string)$err->message;
                    }
                    return [
                        'success' => false,
                        'message' => 'Booking error: ' . implode('; ', $errors),
                        'errors'  => $errors,
                        'raw_response' => $response->body(),
                    ];
                }

                return [
                    'success' => true,
                    'data'    => [
                        'reservationStatus' => (string)($res->bookingResponseDetails->reservationStatus ?? 'UNKNOWN'),
                        'externalId'        => (string)($res->bookingResponseDetails->externalId ?? ''),
                        'raw_response'      => $response->body(),
                    ],
                ];
            }

            Log::error('OwnerRez createBooking error', [
                'status' => $response->status(), 'body' => $response->body(),
            ]);
            return [
                'success' => false,
                'message' => 'Failed to create booking',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('OwnerRez createBooking exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------
    // Get Booking — GET /haolb/bookingdetails?type=X&key=Y (XML)
    // ---------------------------------------------------------------
    public function getBooking(string $bookingId): array
    {
        try {
            $url = $this->olbUrl('bookingdetails') . '&bookingId=' . urlencode($bookingId);

            $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode("{$this->username}:{$this->password}"),
                    'Accept'        => 'application/xml',
                    'User-Agent'    => 'PikPakGo/1.0',
                ])
                ->timeout(30)
                ->get($url);

            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());
                if ($xml === false) {
                    return ['success' => false, 'message' => 'Failed to parse booking XML'];
                }
                return [
                    'success' => true,
                    'data'    => [
                        'reservationStatus' => (string)($xml->bookingUpdateDetails->reservationStatus ?? 'UNKNOWN'),
                        'paymentStatus'     => (string)($xml->bookingUpdateDetails->reservationPaymentStatus ?? 'UNKNOWN'),
                        'raw_xml'           => $response->body(),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get booking',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('OwnerRez getBooking exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ---------------------------------------------------------------
    // Update Booking — POST /haolb/modifybooking?type=X&key=Y (XML)
    // ---------------------------------------------------------------
    public function updateBooking(string $bookingId, array $updateData): array
    {
        try {
            $url = $this->olbUrl('modifybooking') . '&bookingId=' . urlencode($bookingId);

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><bookingRequest/>');
            $xml->addChild('documentVersion', '1.3');

            $details = $xml->addChild('bookingRequestDetails');
            $details->addChild('advertiserAssignedId', htmlspecialchars($this->advertiserId));
            $details->addChild('clientIPAddress', htmlspecialchars(request()->ip() ?? '127.0.0.1'));

            if (isset($updateData['listingExternalId'])) {
                $details->addChild('listingExternalId', htmlspecialchars($updateData['listingExternalId']));
            }

            if (isset($updateData['traveler'])) {
                $traveler = $updateData['traveler'];
                $inquirer = $details->addChild('inquirer');
                $inquirer->addAttribute('locale', 'en_US');
                if (isset($traveler['firstName'])) $inquirer->addChild('firstName', htmlspecialchars($traveler['firstName']));
                if (isset($traveler['lastName']))  $inquirer->addChild('lastName',  htmlspecialchars($traveler['lastName']));
                if (isset($traveler['emailAddress'])) $inquirer->addChild('emailAddress', htmlspecialchars($traveler['emailAddress']));
            }

            $reservation    = $details->addChild('reservation');
            $travelersCount = $updateData['travelers'] ?? [];
            if (isset($travelersCount['adults']))   $reservation->addChild('numberOfAdults',   (int)$travelersCount['adults']);
            if (isset($travelersCount['children'])) $reservation->addChild('numberOfChildren', (int)$travelersCount['children']);

            if (isset($updateData['arrivalDate'], $updateData['departureDate'])) {
                $dates = $reservation->addChild('reservationDates');
                $dates->addChild('beginDate', htmlspecialchars($updateData['arrivalDate']));
                $dates->addChild('endDate',   htmlspecialchars($updateData['departureDate']));
            }

            $xmlPayload = $xml->asXML();

            $response = Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode("{$this->username}:{$this->password}"),
                    'Content-Type'  => 'application/xml',
                    'Accept'        => 'application/xml',
                    'User-Agent'    => 'PikPakGo/1.0',
                ])
                ->timeout(30)
                ->send('POST', $url, ['body' => $xmlPayload]);

            if ($response->successful()) {
                $res = simplexml_load_string($response->body());
                return [
                    'success' => true,
                    'data'    => [
                        'reservationStatus' => (string)($res->bookingResponseDetails->reservationStatus ?? 'UNKNOWN'),
                        'externalId'        => $bookingId,
                        'raw_response'      => $response->body(),
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to update booking',
                'status'  => $response->status(),
                'error'   => $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error('OwnerRez updateBooking exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function cancelBooking(string $bookingId): array
    {
        return ['success' => false, 'message' => 'Cancel not supported via Channel API. Use OwnerRez dashboard.'];
    }

    public function getReviews(string $propertyId): array
    {
        return ['success' => false, 'message' => 'Reviews not exposed via Channel API.'];
    }

    public function checkChannelAvailability($params): array
    {
        if (!isset($params['property_id'])) {
            return ['success' => false, 'message' => 'Missing property_id'];
        }
        return $this->checkAvailability($params['property_id'], $params);
    }

    // ------------------------------------------------------------------
    // Mock helpers
    // ------------------------------------------------------------------

    private function getMockPropertyDetails(string $propertyId): array
    {
        $mock  = $this->getMockProperties();
        $items = $mock['data']['properties'] ?? [];
        foreach ($items as $property) {
            if ($property['property_id'] === $propertyId) {
                return ['success' => true, 'data' => $property];
            }
        }
        return ['success' => false, 'message' => 'Mock property not found'];
    }

    private function getMockProperties(): array
    {
        return [
            'success' => true,
            'data'    => [
                'properties' => [
                    [
                        'id'            => 101,
                        'property_id'   => 'mock_101',
                        'name'          => 'Luxury Ocean View Apartment',
                        'description'   => 'Beautiful apartment with direct ocean views.',
                        'property_type' => 'apartment',
                        'city'          => 'Miami',
                        'country'       => 'United States',
                        'bedrooms'      => 2,
                        'bathrooms'     => 2,
                        'guests'        => 4,
                        'rate'          => 250.00,
                        'currency'      => 'USD',
                        'images'        => ['https://placehold.co/600x400?text=Ocean+View'],
                        'amenities'     => ['Wifi', 'Pool', 'Gym', 'Kitchen'],
                        'star_rating'   => 5,
                    ],
                    [
                        'id'            => 102,
                        'property_id'   => 'mock_102',
                        'name'          => 'Downtown Modern Loft',
                        'description'   => 'Stylish loft in the heart of the city.',
                        'property_type' => 'loft',
                        'city'          => 'Miami',
                        'country'       => 'United States',
                        'bedrooms'      => 1,
                        'bathrooms'     => 1,
                        'guests'        => 2,
                        'rate'          => 175.00,
                        'currency'      => 'USD',
                        'images'        => ['https://placehold.co/600x400?text=Modern+Loft'],
                        'amenities'     => ['Wifi', 'Elevator', 'Air Conditioning'],
                        'star_rating'   => 4,
                    ],
                ],
            ],
        ];
    }
}
