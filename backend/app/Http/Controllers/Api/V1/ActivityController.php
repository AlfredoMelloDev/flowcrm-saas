<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\IndexActivityRequest;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Http\Requests\Activity\UpdateActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ActivityController extends Controller
{
    public function index(IndexActivityRequest $request): AnonymousResourceCollection
    {
        $query = Activity::query()->with(['user', 'lead', 'client', 'opportunity']);

        $this->scopeToOwnership($query, $request->user());
        $this->applySearch($query, $request->validated('search'));
        $this->applyWindow($query, $request->validated('window'));

        $query->when($request->validated('type'), fn ($query, $type) => $query->where('type', $type));
        $query->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status));
        $query->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId));
        $query->when($request->validated('lead_id'), fn ($query, $leadId) => $query->where('lead_id', $leadId));
        $query->when($request->validated('client_id'), fn ($query, $clientId) => $query->where('client_id', $clientId));
        $query->when(
            $request->validated('opportunity_id'),
            fn ($query, $opportunityId) => $query->where('opportunity_id', $opportunityId)
        );

        $sort = $request->validated('sort') ?? 'scheduled_at';
        $order = $request->validated('order') ?? 'asc';
        $query->orderBy($sort, $order);

        $perPage = $request->validated('per_page') ?? 15;

        return ActivityResource::collection($query->paginate($perPage));
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->user()->isSeller()) {
            $data['user_id'] = $request->user()->id;
        }

        $activity = Activity::create($data);

        return (new ActivityResource($activity->load(['user', 'lead', 'client', 'opportunity'])))
            ->additional(['message' => 'Activity created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);

        return new ActivityResource($activity->load(['user', 'lead', 'client', 'opportunity']));
    }

    public function update(UpdateActivityRequest $request, Activity $activity): ActivityResource
    {
        $activity->update($request->validated());

        return new ActivityResource($activity->load(['user', 'lead', 'client', 'opportunity']));
    }

    public function destroy(Activity $activity): JsonResponse
    {
        $this->authorize('delete', $activity);

        $activity->delete();

        return response()->json(['message' => 'Activity deleted successfully.']);
    }

    public function complete(Activity $activity): ActivityResource
    {
        $this->authorize('complete', $activity);

        $activity->update([
            'status' => ActivityStatus::Completed,
            'completed_at' => now(),
        ]);

        return new ActivityResource($activity->load(['user', 'lead', 'client', 'opportunity']));
    }

    public function reopen(Activity $activity): ActivityResource
    {
        $this->authorize('reopen', $activity);

        $activity->update([
            'status' => ActivityStatus::Pending,
            'completed_at' => null,
        ]);

        return new ActivityResource($activity->load(['user', 'lead', 'client', 'opportunity']));
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
                    ->orWhere('description', 'like', "%{$search}%");
            });
        });
    }

    /**
     * "today"/"upcoming"/"overdue" are computed here with the server's own
     * clock (Carbon::now()) — the client only ever sends the literal window
     * name, never a computed date, so no client timezone is involved.
     */
    private function applyWindow(Builder $query, ?string $window): void
    {
        match ($window) {
            'today' => $query->whereBetween('scheduled_at', [
                Carbon::now()->startOfDay(),
                Carbon::now()->endOfDay(),
            ]),
            'upcoming' => $query->where('status', ActivityStatus::Pending)
                ->where('scheduled_at', '>', Carbon::now()),
            'overdue' => $query->where('status', ActivityStatus::Pending)
                ->where('scheduled_at', '<', Carbon::now()),
            default => null,
        };
    }
}
