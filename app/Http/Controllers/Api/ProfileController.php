<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HostProfile;
use App\Models\AgencyProfile;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Profile",
 *     description="Host and agency profile management"
 * )
 */
class ProfileController extends Controller
{
    // =========================================================
    // HOST PROFILE
    // =========================================================

    /**
     * @OA\Get(
     *     path="/profile/host",
     *     summary="Get current user's host profile",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Host profile"),
     *     @OA\Response(response=403, description="Not a host account")
     * )
     */
    public function getHostProfile()
    {
        $user = auth()->user();
        if ($user->user_type !== 'host') {
            return response()->json(['success' => false, 'message' => 'Not a host account.'], 403);
        }

        $profile = $user->hostProfile ?? HostProfile::create(['user_id' => $user->id]);

        return response()->json(['success' => true, 'data' => array_merge($user->only([
            'id', 'first_name', 'last_name', 'email', 'phone', 'country', 'city',
            'profile_image', 'email_verified_at', 'last_login_at', 'role_id', 'role_name'
        ]), ['host_profile' => $profile])]);
    }

    /**
     * @OA\Put(
     *     path="/profile/host",
     *     summary="Update host profile",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="business_name", type="string"),
     *         @OA\Property(property="business_registration_number", type="string"),
     *         @OA\Property(property="tax_id", type="string"),
     *         @OA\Property(property="bio", type="string"),
     *         @OA\Property(property="languages_spoken", type="array", @OA\Items(type="string"))
     *     )),
     *     @OA\Response(response=200, description="Profile updated"),
     *     @OA\Response(response=403, description="Not a host account")
     * )
     */
    public function updateHostProfile(Request $request)
    {
        $user = auth()->user();
        if ($user->user_type !== 'host') {
            return response()->json(['success' => false, 'message' => 'Not a host account.'], 403);
        }

        $validated = $request->validate([
            'business_name'                => 'sometimes|string|max:255',
            'business_registration_number' => 'sometimes|nullable|string|max:100',
            'tax_id'                       => 'sometimes|nullable|string|max:100',
            'bio'                          => 'sometimes|nullable|string|max:2000',
            'languages_spoken'             => 'sometimes|array',
            'languages_spoken.*'           => 'string|max:50',
        ]);

        $profile = HostProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json(['success' => true, 'message' => 'Host profile updated.', 'data' => $profile]);
    }

    // =========================================================
    // AGENCY PROFILE
    // =========================================================

    /**
     * @OA\Get(
     *     path="/profile/agency",
     *     summary="Get current user's agency profile",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Agency profile"),
     *     @OA\Response(response=403, description="Not an agency account")
     * )
     */
    public function getAgencyProfile()
    {
        $user = auth()->user();
        if ($user->user_type !== 'agency') {
            return response()->json(['success' => false, 'message' => 'Not an agency account.'], 403);
        }

        $profile = $user->agencyProfile ?? AgencyProfile::create(['user_id' => $user->id]);

        return response()->json(['success' => true, 'data' => array_merge($user->only([
            'id', 'first_name', 'last_name', 'email', 'phone', 'country', 'city',
            'profile_image', 'email_verified_at', 'last_login_at', 'role_id', 'role_name'
        ]), ['agency_profile' => $profile])]);
    }

    /**
     * @OA\Put(
     *     path="/profile/agency",
     *     summary="Update agency profile",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="agency_name", type="string"),
     *         @OA\Property(property="tax_id", type="string"),
     *         @OA\Property(property="website", type="string"),
     *         @OA\Property(property="business_description", type="string"),
     *         @OA\Property(property="years_in_business", type="integer"),
     *         @OA\Property(property="billing_contact_email", type="string", format="email"),
     *         @OA\Property(property="billing_contact_phone", type="string")
     *     )),
     *     @OA\Response(response=200, description="Profile updated"),
     *     @OA\Response(response=403, description="Not an agency account")
     * )
     */
    public function updateAgencyProfile(Request $request)
    {
        $user = auth()->user();
        if ($user->user_type !== 'agency') {
            return response()->json(['success' => false, 'message' => 'Not an agency account.'], 403);
        }

        $validated = $request->validate([
            'agency_name'                  => 'sometimes|string|max:255',
            'agency_registration_number'   => 'sometimes|nullable|string|max:100',
            'tax_id'                       => 'sometimes|nullable|string|max:100',
            'license_number'               => 'sometimes|nullable|string|max:100',
            'business_description'         => 'sometimes|nullable|string|max:2000',
            'website'                      => 'sometimes|nullable|url|max:255',
            'years_in_business'            => 'sometimes|nullable|integer|min:0',
            'number_of_employees'          => 'sometimes|nullable|integer|min:1',
            'billing_contact_email'        => 'sometimes|nullable|email',
            'billing_contact_phone'        => 'sometimes|nullable|string|max:20',
            'payment_terms'                => 'sometimes|nullable|string|max:500',
        ]);

        $profile = AgencyProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

        return response()->json(['success' => true, 'message' => 'Agency profile updated.', 'data' => $profile]);
    }

    // =========================================================
    // ADMIN: verify / list host and agency accounts
    // =========================================================

    /**
     * @OA\Get(
     *     path="/admin/hosts",
     *     summary="List all host profiles (Admin)",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="verification_status", in="query", @OA\Schema(type="string", enum={"pending","approved","rejected"})),
     *     @OA\Response(response=200, description="Host list")
     * )
     */
    public function adminListHosts(Request $request)
    {
        $query = HostProfile::with('user:id,first_name,last_name,email,status,created_at');

        if ($request->has('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    /**
     * @OA\Put(
     *     path="/admin/hosts/{id}/verify",
     *     summary="Approve or reject a host verification (Admin)",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status"},
     *         @OA\Property(property="status", type="string", enum={"approved","rejected"}),
     *         @OA\Property(property="rejection_reason", type="string")
     *     )),
     *     @OA\Response(response=200, description="Verification updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function adminVerifyHost(Request $request, $id)
    {
        $profile = HostProfile::findOrFail($id);

        $request->validate([
            'status'           => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);

        $updates = ['verification_status' => $request->status];
        if ($request->status === 'approved') {
            $updates['is_verified'] = true;
            $updates['verified_at'] = now();
        } else {
            $updates['rejection_reason'] = $request->rejection_reason;
        }

        $profile->update($updates);

        if ($profile->user_id) {
            $msg = $request->status === 'approved'
                ? 'Your host profile has been verified!'
                : 'Your host verification was rejected: ' . $request->rejection_reason;
            \App\Models\UserNotification::notify(
                $profile->user_id,
                'host_verification_' . $request->status,
                'Host Verification ' . ucfirst($request->status),
                $msg
            );
        }

        return response()->json(['success' => true, 'message' => 'Host verification updated.', 'data' => $profile]);
    }

    /**
     * @OA\Get(
     *     path="/admin/agencies",
     *     summary="List all agency profiles (Admin)",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="verification_status", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Agency list")
     * )
     */
    public function adminListAgencies(Request $request)
    {
        $query = AgencyProfile::with('user:id,first_name,last_name,email,status,created_at');

        if ($request->has('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        return response()->json(['success' => true, 'data' => $query->paginate(20)]);
    }

    /**
     * @OA\Put(
     *     path="/admin/agencies/{id}/verify",
     *     summary="Approve or reject an agency verification (Admin)",
     *     tags={"Admin - Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status"},
     *         @OA\Property(property="status",           type="string", enum={"approved","rejected"}),
     *         @OA\Property(property="rejection_reason", type="string", description="Required when status=rejected")
     *     )),
     *     @OA\Response(response=200, description="Verification updated"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function adminVerifyAgency(Request $request, $id)
    {
        $profile = AgencyProfile::findOrFail($id);

        $request->validate([
            'status'           => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);

        $updates = ['verification_status' => $request->status];
        if ($request->status === 'approved') {
            $updates['is_verified'] = true;
            $updates['verified_at'] = now();
        } else {
            $updates['rejection_reason'] = $request->rejection_reason;
        }

        $profile->update($updates);

        if ($profile->user_id) {
            $msg = $request->status === 'approved'
                ? 'Your agency profile has been verified!'
                : 'Your agency verification was rejected: ' . $request->rejection_reason;
            \App\Models\UserNotification::notify(
                $profile->user_id,
                'agency_verification_' . $request->status,
                'Agency Verification ' . ucfirst($request->status),
                $msg
            );
        }

        return response()->json(['success' => true, 'message' => 'Agency verification updated.', 'data' => $profile]);
    }

    /**
     * @OA\Get(
     *     path="/profile/rewards",
     *     summary="Get current user's reward points balance and tier history",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Rewards status details")
     * )
     */
    public function getRewardsStatus(\App\Services\RewardService $rewardService)
    {
        try {
            $userId = auth()->id();
            $balance = $rewardService->getUserPointsBalance($userId);
            $tier = $rewardService->determineUserTier($userId);

            return response()->json([
                'success' => true,
                'data' => [
                    'points_balance' => $balance,
                    'current_tier' => $tier,
                    'ledger' => \App\Models\RewardLedger::where('user_id', $userId)
                        ->orderBy('created_at', 'desc')
                        ->limit(50)
                        ->get()
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Rewards status error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rewards status: ' . $e->getMessage()
            ], 200);
        }
    }
}
