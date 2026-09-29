<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientOptionResource;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    public function index(IndexClientRequest $request): AnonymousResourceCollection
    {
        $query = Client::query()->with('user');

        if ($request->user()->isSeller()) {
            $query->where('user_id', $request->user()->id);
        }

        $query->when($request->validated('search'), function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%");
            });
        });

        $query->when($request->validated('status'), fn ($query, $status) => $query->where('status', $status));
        $query->when($request->validated('type'), fn ($query, $type) => $query->where('type', $type));
        $query->when($request->validated('user_id'), fn ($query, $userId) => $query->where('user_id', $userId));

        $sort = $request->validated('sort') ?? 'created_at';
        $order = $request->validated('order') ?? 'desc';
        $query->orderBy($sort, $order);

        $perPage = $request->validated('per_page') ?? 15;

        return ClientResource::collection($query->paginate($perPage));
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->user()->isSeller()) {
            $data['user_id'] = $request->user()->id;
        }

        $client = Client::create($data);

        return (new ClientResource($client->load('user')))
            ->additional(['message' => 'Client created successfully.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        $this->authorize('view', $client);

        return new ClientResource($client->load('user'));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $client->update($request->validated());

        return new ClientResource($client->load('user'));
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->authorize('delete', $client);

        // Opportunity::client_id is required (not nullable) — deleting a
        // client that still has non-deleted opportunities would leave them
        // pointing at a soft-deleted record (the relation resolves to null
        // for those, per the SoftDeletingScope). exists() already excludes
        // soft-deleted opportunities on its own, so a client whose only
        // opportunities were already removed can still be deleted.
        if ($client->opportunities()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a client that still has opportunities.',
            ], 409);
        }

        $client->delete();

        return response()->json(['message' => 'Client deleted successfully.']);
    }

    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Client::class);

        $query = Client::query()->where('status', ClientStatus::Active);

        if ($request->user()->isSeller()) {
            $query->where('user_id', $request->user()->id);
        }

        return ClientOptionResource::collection($query->orderBy('name')->get());
    }
}
