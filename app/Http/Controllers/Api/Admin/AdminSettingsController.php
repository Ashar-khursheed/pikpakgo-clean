<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Admin - Settings",
 *     description="Admin application settings management"
 * )
 */
class AdminSettingsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/admin/settings",
     *     summary="Get all settings grouped by category",
     *     tags={"Admin - Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="group", in="query", description="Filter by group: general|booking|email|payment|api", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Settings list")
     * )
     */
    public function index(Request $request)
    {
        $query = Setting::query();

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        $settings = $query->orderBy('group')->orderBy('key')->get();

        $grouped = $settings->groupBy('group')->map(function ($items) {
            return $items->map(fn ($s) => [
                'key'         => $s->key,
                'value'       => $s->value,
                'type'        => $s->type,
                'label'       => $s->label,
                'description' => $s->description,
                'is_public'   => $s->is_public,
            ]);
        });

        return response()->json(['success' => true, 'data' => $grouped]);
    }

    /**
     * @OA\Put(
     *     path="/admin/settings",
     *     summary="Update multiple settings at once",
     *     tags={"Admin - Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="settings", type="object", example={"site_name":"PikPakGo","default_currency":"USD"})
     *     )),
     *     @OA\Response(response=200, description="Settings updated")
     * )
     */
    public function update(Request $request)
    {
        $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'nullable|string|max:2000',
        ]);

        foreach ($request->settings as $key => $value) {
            Setting::where('key', $key)->update(['value' => $value]);
        }

        Cache::forget('settings_all');

        return response()->json(['success' => true, 'message' => 'Settings updated successfully']);
    }

    /**
     * @OA\Put(
     *     path="/admin/settings/{key}",
     *     summary="Update a single setting",
     *     tags={"Admin - Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string", example="site_name")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"value"},
     *         @OA\Property(property="value", type="string", example="MyApp")
     *     )),
     *     @OA\Response(response=200, description="Setting updated"),
     *     @OA\Response(response=404, description="Setting not found")
     * )
     */
    public function updateSingle(Request $request, string $key)
    {
        $setting = Setting::where('key', $key)->firstOrFail();

        $request->validate(['value' => 'nullable|string|max:2000']);

        $setting->update(['value' => $request->value]);
        Cache::forget('settings_all');

        return response()->json([
            'success' => true,
            'message' => "Setting '{$key}' updated.",
            'data'    => $setting->fresh(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/settings/public",
     *     summary="Get all public settings (also accessible without auth)",
     *     tags={"Admin - Settings"},
     *     @OA\Response(response=200, description="Public settings")
     * )
     */
    public function publicSettings()
    {
        $settings = Setting::where('is_public', true)
            ->get()
            ->pluck('value', 'key');

        return response()->json(['success' => true, 'data' => $settings]);
    }
}
