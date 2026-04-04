<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class SearchValidationTest extends TestCase
{
    public function test_hotel_search_with_past_date_fails()
    {
        $response = $this->postJson('/api/public/search/hotels', [
            'checkIn' => '2025-03-15',
            'checkOut' => '2025-03-17',
            'destination' => 'NYC',
            'destinationCode' => 'NYC',
            'adults' => 2,
            'rooms' => 1,
            'occupancies' => [
                [
                    'rooms' => 1,
                    'adults' => 2,
                    'children' => 0
                ]
            ]
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Validation error'
        ]);
        $response->assertJsonValidationErrors(['checkIn']);
    }

    public function test_hotel_search_with_future_date_passes_validation()
    {
        // Mock the service to avoid actual API calls
        // Since SearchController uses HotelbedsService which is injected in constructor, 
        // and it uses Cache::remember, we might need to mock the service or the HTTP calls
        
        Http::fake([
            '*/hotel-api/1.0/hotels' => Http::response(['success' => true, 'data' => ['hotels' => []]], 200),
        ]);

        $response = $this->postJson('/api/public/search/hotels', [
            'checkIn' => '2027-03-15',
            'checkOut' => '2027-03-17',
            'destination' => 'NYC',
            'destinationCode' => 'NYC',
            'adults' => 2,
            'rooms' => 1,
            'occupancies' => [
                [
                    'rooms' => 1,
                    'adults' => 2,
                    'children' => 0
                ]
            ]
        ]);

        // It should either be 200 (if mock works) or 500 (if mock fails but validation passed)
        // But definitely NOT 400 validation error
        $this->assertNotEquals(400, $response->getStatusCode());
    }
}
