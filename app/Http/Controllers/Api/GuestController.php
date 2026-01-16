<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuestSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    /**
     * Create a new guest session
     */
    public function createSession(Request $request)
    {
        try {
            // Generate unique session ID
            $sessionId = 'guest_' . Str::random(32);
            
            // Create guest session
            $session = GuestSession::create([
                'session_id' => $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_info' => [
                    'platform' => $request->header('sec-ch-ua-platform'),
                    'mobile' => $request->header('sec-ch-ua-mobile'),
                ],
                'first_activity_at' => now(),
                'last_activity_at' => now(),
                'expires_at' => now()->addDays(30), // 30 days expiry
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'expires_at' => $session->expires_at,
                ]
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create session'
            ], 500);
        }
    }
    
    /**
     * Update guest session information
     */
    public function updateSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|string',
            'email' => 'nullable|email',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            $session = GuestSession::where('session_id', $request->session_id)->first();
            
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404);
            }
            
            // Update session with provided information
            $session->update([
                'email' => $request->email ?? $session->email,
                'first_name' => $request->first_name ?? $session->first_name,
                'last_name' => $request->last_name ?? $session->last_name,
                'phone' => $request->phone ?? $session->phone,
                'country' => $request->country ?? $session->country,
                'last_activity_at' => now(),
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $session
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update session'
            ], 500);
        }
    }
    
    /**
     * Get guest session details
     */
    public function getSession($sessionId)
    {
        try {
            $session = GuestSession::where('session_id', $sessionId)->first();
            
            if (!$session) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found'
                ], 404);
            }
            
            // Update last activity
            $session->updateActivity();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'session_id' => $session->session_id,
                    'email' => $session->email,
                    'first_name' => $session->first_name,
                    'last_name' => $session->last_name,
                    'search_count' => $session->search_count,
                    'booking_count' => $session->booking_count,
                    'expires_at' => $session->expires_at,
                    'is_active' => $session->isActive(),
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session'
            ], 500);
        }
    }
}
