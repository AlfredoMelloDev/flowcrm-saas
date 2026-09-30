<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Leads\ConvertLead;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\ConvertLeadRequest;
use App\Http\Requests\Lead\IndexLeadRequest;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\LeadOptionResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\OpportunityResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    public function index(IndexLeadRequest $request): AnonymousResourceCollection
    {
        $query = Lead::query()->with('user');

        if ($request->user()->isSeller()) {
            $query->where('user_id', $request->user()->id);
        }

        $query->when($request->validated('search'), function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        });

        $query->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status));
        $query->when($request->validated('source'), fn ($query, $source) => $query->where('source', $source));
        $query->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId));

        $sort = $request->validated('sort') ?? 'created_at';
        $order = $request->validated('order') ?? 'desc';
        $query->orderBy($sort, $order);

        $perPage = $request->validated('per_page') ?? 15;

        return LeadResource::collection($query->paginate($perPage));
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->user()->isSeller()) {
            $data['user_id'] = $request->user()->id;
        }

        $lead = Lead::create($data);

        return (new LeadResource($lead->load('user')))
            ->additional(['message' => 'Lead created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Lead $lead): LeadResource
    {
        $this->authorize('view', $lead);

        return new LeadResource($lead->load('user'));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->validated());

        return new LeadResource($lead->load('user'));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        if ($lead->status === LeadStatus::Converted) {
            return response()->json([
                'message' => 'Cannot delete a lead that has already been converted.',
            ], 409);
        }

        $lead->delete();

        return response()->json(['message' => 'Lead deleted successfully.']);
    }

    public function convert(ConvertLeadRequest $request, Lead $lead, ConvertLead $convertLead): JsonResponse
    {
        $result = $convertLead->handle($lead, $request->validated(), $request->user());

        return response()->json([
            'data' => [
                'lead' => new LeadResource($result['lead']->load('user')),
                'client' => new ClientResource($result['client']->load('user')),
                'opportunity' => new OpportunityResource($result['opportunity']->load(['client', 'user'])),
            ],
            'message' => 'Lead converted successfully.',
        ], 201);
    }

    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Lead::class);

        $query = Lead::query();

        if ($request->user()->isSeller()) {
            $query->where('user_id', $request->user()->id);
        }

        return LeadOptionResource::collection($query->orderBy('name')->get());
    }
}
