<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssignableUserResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function assignable(TenantContext $tenantContext): AnonymousResourceCollection
    {
        $this->authorize('listAssignableUsers');

        $users = User::query()
            ->where('company_id', $tenantContext->id())
            ->where('status', UserStatus::Active)
            ->orderBy('name')
            ->get();

        return AssignableUserResource::collection($users);
    }
}
