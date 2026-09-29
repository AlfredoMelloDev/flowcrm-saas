<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'stage' => $this->stage,
            'value' => $this->value,
            'expected_close_date' => $this->expected_close_date,
            'closed_at' => $this->closed_at,
            'lost_reason' => $this->lost_reason,
            'notes' => $this->notes,
            // client_id is required at the DB level, but $this->client can
            // still resolve to null if the client was soft-deleted (the
            // SoftDeletingScope applies to relation loads too) — guarded
            // defensively even though ClientController::destroy() already
            // blocks deleting a client with non-deleted opportunities.
            'client' => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null,
            'assigned_to' => $this->user_id !== null ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
