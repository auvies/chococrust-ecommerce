<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Prepare secure Facebook/Instagram integration" - no Meta developer app
 * or Instagram Business account exists yet (same "prepared, not built"
 * posture as WhatsApp/Easypaisa/JazzCash: CLAUDE.md §6, ADR 0009/0010),
 * so there is no OAuth flow, no posting, and no fetching here. What exists
 * is the thing an admin actually needs today: a status check for whether
 * each platform is configured, gated behind `settings.manage` (the same
 * permission CLAUDE.md's own RBAC table names for "integrations/secrets
 * management" - super_admin-only, not even `manager`).
 *
 * `settings.manage` is deliberately required (not `content.manage`, which
 * a wider set of staff hold) because this endpoint's *purpose* is exposing
 * integration status, and CLAUDE.md §6 treats integration configuration as
 * a super_admin-only concern.
 *
 * Never returns a token, app secret, or any other credential - only a
 * boolean per platform (derived from whether the required env vars are
 * non-empty) plus the handful of identifiers (page id, business account
 * id) that are not secrets on their own. `SocialAccountStatusTest`
 * asserts this directly: even with every credential configured, the
 * response body never contains the actual secret string.
 */
class SocialAccountController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['settings.manage']), 403);

        $facebook = config('services.facebook');
        $instagram = config('services.instagram');

        return response()->json([
            'data' => [
                [
                    'platform' => 'facebook',
                    'connected' => (bool) ($facebook['enabled'] && $facebook['app_id'] && $facebook['page_access_token']),
                    'page_id' => $facebook['page_id'],
                ],
                [
                    'platform' => 'instagram',
                    'connected' => (bool) ($instagram['enabled'] && $instagram['business_account_id'] && $instagram['access_token']),
                    'business_account_id' => $instagram['business_account_id'],
                ],
            ],
        ]);
    }
}
