<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerificationMail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Models\HostProfile;
use App\Models\AgencyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and profile management"
 * )
 * 
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     title="User",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="first_name", type="string", example="John"),
 *     @OA\Property(property="last_name", type="string", example="Doe"),
 *     @OA\Property(property="full_name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", example="john@example.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="user_type", type="string", example="customer"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="email_verified", type="boolean", example=true),
 *     @OA\Property(property="country", type="string", example="USA"),
 *     @OA\Property(property="preferred_currency", type="string", example="USD"),
 *     @OA\Property(property="preferred_language", type="string", example="en")
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/register",
     *     tags={"Authentication"},
     *     summary="Register new user",
     *     description="Register a new user (customer, host, or agency)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name","last_name","email","password","password_confirmation","user_type"},
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123"),
     *             @OA\Property(property="phone", type="string", example="+1234567890"),
     *             @OA\Property(property="user_type", type="string", enum={"customer", "host", "agency"}, example="customer"),
     *             @OA\Property(property="country", type="string", example="USA"),
     *             @OA\Property(
     *                 property="host_profile",
     *                 type="object",
     *                 @OA\Property(property="business_name", type="string"),
     *                 @OA\Property(property="business_registration_number", type="string"),
     *                 @OA\Property(property="bio", type="string")
     *             ),
     *             @OA\Property(
     *                 property="agency_profile",
     *                 type="object",
     *                 @OA\Property(property="agency_name", type="string"),
     *                 @OA\Property(property="tax_id", type="string"),
     *                 @OA\Property(property="website", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Registration successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'user_type' => 'required|in:customer,host,agency',
        ];

        if ($request->user_type === 'host') {
            $rules['host_profile.business_name'] = 'required|string|max:255';
        }

        if ($request->user_type === 'agency') {
            $rules['agency_profile.agency_name'] = 'required|string|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'user_type' => $request->user_type,
                'status' => 'pending',
                'country' => $request->country,
                'preferred_currency' => $request->preferred_currency ?? 'USD',
                'preferred_language' => $request->preferred_language ?? 'en',
                'verification_token' => Str::random(64),
            ]);

            if ($request->user_type === 'host' && $request->has('host_profile')) {
                HostProfile::create(array_merge(
                    ['user_id' => $user->id],
                    $request->host_profile
                ));
            }

            if ($request->user_type === 'agency' && $request->has('agency_profile')) {
                AgencyProfile::create(array_merge(
                    ['user_id' => $user->id],
                    $request->agency_profile
                ));
            }

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Authentication"},
     *     summary="User login",
     *     description="Authenticate user and return JWT token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="remember_me", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="bearer"),
     *                 @OA\Property(property="expires_in", type="integer", example=3600)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials"),
     *     @OA\Response(response=403, description="Account suspended")
     * )
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $credentials = $request->only('email', 'password');

            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ], 401);
            }

            $user = auth()->user();

            if ($user->status !== 'active' && $user->status !== 'pending') {
                JWTAuth::invalidate($token);
                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been ' . $user->status
                ], 403);
            }

            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Authentication"},
     *     summary="User logout",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout successful"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json([
                'success' => true,
                'message' => 'Logout successful'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/auth/refresh",
     *     tags={"Authentication"},
     *     summary="Refresh token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Token refreshed"),
     *     @OA\Response(response=401, description="Token invalid")
     * )
     */
    public function refresh()
    {
        try {
            $token = JWTAuth::refresh(JWTAuth::getToken());
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed'
            ], 401);
        }
    }

    /**
     * @OA\Get(
     *     path="/auth/me",
     *     tags={"Authentication"},
     *     summary="Get current user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me()
    {
        try {
            $user = auth()->user();
            return response()->json([
                'success' => true,
                'data' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/auth/profile",
     *     tags={"Authentication"},
     *     summary="Update user profile",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="state", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="date_of_birth", type="string", format="date"),
     *             @OA\Property(property="gender", type="string", enum={"male","female","other","prefer_not_to_say"}),
     *             @OA\Property(property="preferred_currency", type="string", example="USD"),
     *             @OA\Property(property="preferred_language", type="string", example="en")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Profile updated"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name'         => 'sometimes|string|max:255',
            'last_name'          => 'sometimes|string|max:255',
            'phone'              => 'sometimes|string|max:20',
            'country'            => 'sometimes|string|max:100',
            'city'               => 'sometimes|string|max:100',
            'state'              => 'sometimes|string|max:100',
            'zip_code'           => 'sometimes|string|max:20',
            'address'            => 'sometimes|string|max:500',
            'date_of_birth'      => 'sometimes|date|before:today',
            'gender'             => 'sometimes|in:male,female,other,prefer_not_to_say',
            'preferred_currency' => 'sometimes|string|size:3',
            'preferred_language' => 'sometimes|string|max:5',
        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data'    => $user->fresh(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/change-password",
     *     tags={"Authentication"},
     *     summary="Change current user password",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password","password","password_confirmation"},
     *             @OA\Property(property="current_password", type="string"),
     *             @OA\Property(property="password", type="string", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Password changed"),
     *     @OA\Response(response=422, description="Current password incorrect")
     * )
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return response()->json(['success' => true, 'message' => 'Password changed successfully']);
    }

    /**
     * @OA\Post(
     *     path="/auth/forgot-password",
     *     tags={"Authentication"},
     *     summary="Request a password reset link",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reset link sent (if email exists)"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Always return success to avoid user enumeration
        if ($user) {
            $token = Str::random(64);
            $user->update([
                'reset_token'            => $token,
                'reset_token_expires_at' => now()->addHour(),
            ]);
            Mail::to($user->email)->queue(new PasswordResetMail($user, $token));
        }

        return response()->json([
            'success' => true,
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/reset-password",
     *     tags={"Authentication"},
     *     summary="Reset password using token",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", minLength=8),
     *             @OA\Property(property="password_confirmation", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Password reset successful"),
     *     @OA\Response(response=422, description="Invalid or expired token")
     * )
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)
            ->where('reset_token', $request->token)
            ->where('reset_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 422);
        }

        $user->update([
            'password'               => Hash::make($request->password),
            'reset_token'            => null,
            'reset_token_expires_at' => null,
            'status'                 => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
    }

    /**
     * @OA\Post(
     *     path="/auth/verify-email/{token}",
     *     tags={"Authentication"},
     *     summary="Verify email address using token",
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Email verified"),
     *     @OA\Response(response=422, description="Invalid token")
     * )
     */
    public function verifyEmail($token)
    {
        $user = User::where('verification_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token.',
            ], 422);
        }

        if ($user->email_verified_at) {
            return response()->json(['success' => true, 'message' => 'Email already verified.']);
        }

        $user->update([
            'email_verified_at'  => now(),
            'verification_token' => null,
            'status'             => 'active',
        ]);

        return response()->json(['success' => true, 'message' => 'Email verified successfully.']);
    }

    /**
     * @OA\Post(
     *     path="/auth/resend-verification",
     *     tags={"Authentication"},
     *     summary="Resend email verification link",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Verification email sent"),
     *     @OA\Response(response=422, description="Already verified or not found")
     * )
     */
    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['success' => true, 'message' => 'If that email exists, a verification link has been sent.']);
        }

        if ($user->email_verified_at) {
            return response()->json(['success' => false, 'message' => 'Email is already verified.'], 422);
        }

        $token = Str::random(64);
        $user->update(['verification_token' => $token]);
        Mail::to($user->email)->queue(new EmailVerificationMail($user, $token));

        return response()->json(['success' => true, 'message' => 'Verification email sent.']);
    }
}