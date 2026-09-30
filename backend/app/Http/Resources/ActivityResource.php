<?php

namespace App\Http\Resources;

use App\Enums\ActivityStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'completed_at' => $this->completed_at,
            // Derived, never stored: "overdue" is status=pending with a
            // scheduled_at already in the past — computed here, once,
            // server-side, so no frontend list ever redoes this comparison
            // with its own clock/timezone.
            'is_overdue' => $this->status === ActivityStatus::Pending
                && $this->scheduled_at !== null
                && $this->scheduled_at->isPast(),
            'lead' => $this->lead ? [
                'id' => $this->lead->id,
                'name' => $this->lead->name,
            ] : null,
            'client' => $this->client ? [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ] : null,
            'opportunity' => $this->opportunity ? [
                'id' => $this->opportunity->id,
                'title' => $this->opportunity->title,
            ] : null,
            // Unlike Lead/Client/Opportunity, user_id is required on
            // Activity — always resolved, never null.
            'assigned_to' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
