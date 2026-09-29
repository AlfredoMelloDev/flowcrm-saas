<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Opportunities\ApplyStageTransitionEffects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Opportunity\IndexOpportunityRequest;
use App\Http\Requests\Opportunity\PipelineOpportunityRequest;
use App\Http\Requests\Opportunity\StoreOpportunityRequest;
use App\Http\Requests\Opportunity\UpdateOpportunityRequest;
use App\Http\Resources\OpportunityResource;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OpportunityController extends Controller
{
    public function index(IndexOpportunityRequest $request): AnonymousResourceCollection
    {
        $query = Opportunity::query()->with(['client', 'user']);

        $this->scopeToOwnership($query, $request->user());
        $this->applySearch($query, $request->validated('search'));

        $query->when($request->validated('stage'), fn ($query, $stage) => $query->where('stage', $stage));
        $query->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId));

        $sort = $request->validated('sort') ?? 'created_at';
        $order = $request->validated('order') ?? 'desc';
        $query->orderBy($sort, $order);

        $perPage = $request->validated('per_page') ?? 15;

        return OpportunityResource::collection($query->paginate($perPage));
    }

    public function pipeline(PipelineOpportunityRequest $request): AnonymousResourceCollection
    {
        $query = Opportunity::query()->with(['client', 'user']);

        $this->scopeToOwnership($query, $request->user());
        $this->applySearch($query, $request->validated('search'));

        $query->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId));

        // Deterministic order, no manual reordering/position in this phase.
        $query->orderBy('created_at', 'desc');

        return OpportunityResource::collection($query->get());
    }

    public function store(StoreOpportunityRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->user()->isSeller()) {
            $data['user_id'] = $request->user()->id;
        }

        $opportunity = Opportunity::create($data);

        return (new OpportunityResource($opportunity->load(['client', 'user'])))
            ->additional(['message' => 'Opportunity created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Opportunity $opportunity): OpportunityResource
    {
        $this->authorize('view', $opportunity);

        return new OpportunityResource($opportunity->load(['client', 'user']));
    }

    public function update(
        UpdateOpportunityRequest $request,
        Opportunity $opportunity,
        ApplyStageTransitionEffects $applyStageTransitionEffects,
    ): OpportunityResource {
        $data = $applyStageTransitionEffects->handle($request->validated());

        $opportunity->update($data);

        return new OpportunityResource($opportunity->load(['client', 'user']));
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $this->authorize('delete', $opportunity);

        $opportunity->delete();

        return response()->json(['message' => 'Opportunity deleted successfully.']);
    }

    private function scopeToOwnership(Builder $query, User $user): void
    {
        if ($user->isSeller()) {
            $query->where('user_id', $user->id);
        }
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        $query->when($search, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($query) => $query->where('name', 'like', "%{$search}%"));
            });
        });
    }
}
