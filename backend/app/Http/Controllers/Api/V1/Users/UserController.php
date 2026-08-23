<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff account management. Role/permission ASSIGNMENT is deliberately
 * absent here - that stays a CLI-only action (`php artisan role:assign`,
 * see App\Console\Commands\AssignRole and ADR 0003) so privilege
 * escalation can never happen over HTTP, even to a super_admin caller.
 * This controller only lists staff and toggles account activation.
 */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['staff.manage']), 403);

        $users = ApiQuery::for($request, User::query()->where('type', 'human')->with('roles'))
            ->filters(['is_active'])
            ->sorts(['name', 'created_at'])
            ->searchable(['name', 'email'])
            ->paginate();

        return UserResource::collection($users)->response();
    }

    public function show(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['staff.manage']) || $request->user()->id === $user->id, 403);

        return UserResource::make($user->load('roles'))->response();
    }

    public function activate(Request $request, User $user): JsonResponse
    {
        return $this->setActive($request, $user, true);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        return $this->setActive($request, $user, false);
    }

    private function setActive(Request $request, User $user, bool $active): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['staff.manage']), 403);

        $before = ['is_active' => $user->is_active];
        // forceFill: is_active is deliberately NOT mass-assignable (it must
        // never be settable from a user-facing payload like registration -
        // see RegistrationTest::test_registration_rejects_unknown_fields...),
        // but this is trusted, permission-gated application code, not
        // client input.
        $user->forceFill(['is_active' => $active])->save();

        if (! $active) {
            // A deactivated account's outstanding sessions die immediately,
            // not just at their next natural token expiry.
            $user->bumpTokenVersion();
        }

        AuditLogger::log('user.'.($active ? 'activated' : 'deactivated'), $user, before: $before, after: ['is_active' => $active]);

        return UserResource::make($user)->response();
    }
}
