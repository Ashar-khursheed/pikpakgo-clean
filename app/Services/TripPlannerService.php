<?php

namespace App\Services;

use App\Models\PropertyListing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TripPlannerService
{
    private $geminiApiKey;

    public function __construct()
    {
        $this->geminiApiKey = env('GEMINI_API_KEY') ?? env('OPENAI_API_KEY', '');
    }

    /**
     * Generate a day-by-day travel plan using Gemini/OpenAI if available, or fall back to mock AI.
     */
    public function generateItinerary(string $destination, ?string $startDate, ?string $endDate, array $interests): array
    {
        $days = 3;
        if ($startDate && $endDate) {
            $days = max(1, min(14, \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate)) + 1));
        }

        // Attempt live AI generation if key is present
        if (!empty($this->geminiApiKey) && !env('MOCK_SERVICES', true)) {
            try {
                $prompt = "Generate a detailed, day-by-day travel itinerary for a {$days}-day trip to {$destination}. The interests are: " . implode(', ', $interests) . ". Provide it in JSON format containing a list of 'days', each with 'day_number', 'theme', and a list of 'activities' (each with 'time', 'title', 'description', and 'estimated_cost_usd').";
                
                $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$this->geminiApiKey}", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ]);

                if ($response->successful()) {
                    $jsonText = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    // Clean up markdown block format if LLM wrapped JSON in ```json ... ```
                    $jsonText = preg_replace('/^```json\s*/i', '', $jsonText);
                    $jsonText = preg_replace('/\s*```$/', '', $jsonText);
                    
                    $aiData = json_decode($jsonText, true);
                    if (is_array($aiData)) {
                        return $aiData;
                    }
                }
                Log::warning('AI Generation call was not successful or did not return valid JSON. Falling back to mock generator.');
            } catch (\Exception $e) {
                Log::error('AI Generation exception: ' . $e->getMessage());
            }
        }

        // Mock AI generator fallback (extremely detailed and clean)
        return $this->generateMockItinerary($destination, $days, $interests);
    }

    /**
     * Get similarity matching recommendation listings from the database.
     * Simulated embeddings/vector matching by keyword scoring.
     */
    public function getRecommendedListings(string $destination, array $interests): array
    {
        $query = PropertyListing::query()
            ->where('city', 'LIKE', "%{$destination}%")
            ->orWhere('state', 'LIKE', "%{$destination}%")
            ->orWhere('country', 'LIKE', "%{$destination}%");

        $listings = $query->limit(10)->get();

        if ($listings->isEmpty()) {
            // Fallback: get any listings
            $listings = PropertyListing::limit(5)->get();
        }

        // Rank listings based on keyword match density to simulate embedding similarity
        $ranked = $listings->map(function ($listing) use ($interests) {
            $score = 0;
            $text = strtolower($listing->name . ' ' . $listing->description . ' ' . implode(' ', $listing->amenities ?? []));
            
            foreach ($interests as $interest) {
                $interest = strtolower($interest);
                if (str_contains($text, $interest)) {
                    $score += 5;
                }
            }

            // High rating boost
            if ($listing->star_rating) {
                $score += $listing->star_rating * 1.5;
            }

            return [
                'listing' => $listing,
                'match_score' => $score,
            ];
        })->sortByDesc('match_score')->values();

        return $ranked->map(function ($item) {
            $listing = $item['listing'];
            return [
                'id' => $listing->id,
                'name' => $listing->name,
                'property_type' => $listing->property_type,
                'city' => $listing->city,
                'star_rating' => $listing->star_rating,
                'price' => $listing->price,
                'currency' => $listing->currency ?? 'USD',
                'images' => $listing->images,
                'match_score' => $item['match_score'],
            ];
        })->toArray();
    }

    /**
     * Create mock day-by-day itineraries.
     */
    private function generateMockItinerary(string $destination, int $days, array $interests): array
    {
        $interestStr = implode(', ', $interests);
        $daysArray = [];

        $morningThemes = ['Morning Exploration', 'Cultural Discovery', 'Scenic Adventure', 'Historical Walking Tour'];
        $afternoonThemes = ['Local Flavors & Leisure', 'Adventure & Fun', 'Museums & Highlights', 'Scenic Sightseeing'];
        $eveningThemes = ['Sunset & Dining Experience', 'Nightlife & Culinary Tour', 'Relaxing Cozy Dinner', 'Entertainment Show'];

        for ($day = 1; $day <= $days; $day++) {
            $daysArray[] = [
                'day_number' => $day,
                'theme' => "Day {$day} - Exploring " . ucfirst($destination) . " with " . ($interests[($day - 1) % count($interests)] ?? 'sightseeing'),
                'activities' => [
                    [
                        'time' => '09:00 AM',
                        'title' => $morningThemes[($day - 1) % count($morningThemes)] . " at " . ucfirst($destination),
                        'description' => "Kickstart your day with a guided visit matching your interest in {$interestStr}. Immerse yourself in the local atmosphere.",
                        'estimated_cost_usd' => 15 + ($day * 5),
                    ],
                    [
                        'time' => '01:00 PM',
                        'title' => $afternoonThemes[($day - 1) % count($afternoonThemes)],
                        'description' => "Taste authentic local cuisine and explore nearby landmarks. Great photo opportunities and souvenir hunting.",
                        'estimated_cost_usd' => 25 + ($day * 4),
                    ],
                    [
                        'time' => '06:30 PM',
                        'title' => $eveningThemes[($day - 1) % count($eveningThemes)],
                        'description' => "Unwind and enjoy a memorable dinner with stunning views of the local landscape, followed by a casual walk around the main square.",
                        'estimated_cost_usd' => 45 + ($day * 8),
                    ]
                ]
            ];
        }

        return [
            'destination' => $destination,
            'days_count' => $days,
            'interests' => $interests,
            'days' => $daysArray,
        ];
    }
}
