<?php

namespace App\Http\Controllers\Api\V1\Users;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Read-only - see UserController's docblock for why assignment isn't here. */
class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['staff.manage', 'roles.manage']), 403);

        return RoleResource::collection(Role::with('permissions')->get())->response();
    }
}
