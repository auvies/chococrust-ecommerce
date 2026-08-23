<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\InvalidTokenException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'], // hashed via the 'hashed' cast
            'type' => 'human',
        ]);

        $customerRole = Role::where('slug', 'customer')->firstOrFail();
        $user->roles()->attach($customerRole);
        Customer::create(['user_id' => $user->id]);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.register',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->issueSession($user, $request, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        // AI agent identities never authenticate via password login - they
        // are provisioned and audited through a separate, out-of-band
        // mechanism (CLAUDE.md §9/§23), never given a human-style login.
        if ($user?->isAiAgent()) {
            $user = null;
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            AuditLog::create([
                'action' => 'auth.login_failed',
                'auditable_type' => 'user',
                'auditable_id' => $user?->id,
                'after' => ['email' => $data['email']],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(401, 'Invalid credentials.');
        }

        if (! $user->is_active) {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'auth.login_rejected_inactive',
                'auditable_type' => 'user',
                'auditable_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            abort(403, 'This account is disabled.');
        }

        // MFA architecture hook (prepared, not built - ADR 0003): once a
        // future phase adds TOTP enrollment, a user with two_factor
        // confirmed would get a short-lived challenge response here
        // instead of full tokens, and a new /auth/mfa/verify endpoint
        // would exchange {challenge, code} for the real token pair. No
        // user can reach this branch today since nothing sets
        // two_factor_confirmed_at yet.
        if ($user->hasTwoFactorEnabled()) {
            abort(501, 'MFA verification is not yet available.');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.login_succeeded',
            'auditable_type' => 'user',
            'auditable_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $this->issueSession($user, $request, 200);
    }

    public function refresh(Request $request): JsonResponse
    {
        $raw = $request->cookie(config('security.cookie.refresh_name'));

        if (! is_string($raw) || $raw === '') {
            $this->tokens->clearCookies();
            abort(401, 'No refresh token present.');
        }

        try {
            $pair = $this->tokens->rotate($raw, $request);
        } catch (InvalidTokenException) {
            $this->tokens->clearCookies();
            abort(401, 'Refresh token is invalid or expired.');
        }

        $this->tokens->attachCookies($pair);

        return response()->json(['status' => 'refreshed']);
    }

    public function logout(Request $request): JsonResponse
    {
        $raw = $request->cookie(config('security.cookie.refresh_name'));

        if (is_string($raw) && $raw !== '') {
            $this->tokens->revoke($raw);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'auth.logout',
            'auditable_type' => 'user',
            'auditable_id' => $request->user()->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->tokens->clearCookies();

        return response()->json(null, 204);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $this->tokens->revokeAllForUser($request->user(), 'logout_all', $request);
        $this->tokens->clearCookies();

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'type' => $user->type,
            'roles' => $user->roles->pluck('slug'),
            'permissions' => $user->roles->flatMap(fn ($role) => $role->permissions)->pluck('slug')->unique()->values(),
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
        ]);
    }

    private function issueSession(User $user, Request $request, int $status): JsonResponse
    {
        $pair = $this->tokens->issueForUser($user, $request);
        $this->tokens->attachCookies($pair);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ], $status);
    }
}
