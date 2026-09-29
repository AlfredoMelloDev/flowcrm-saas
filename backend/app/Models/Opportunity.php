<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use App\Models\Concerns\BelongsToCompany;
use Database\Factories\OpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['title', 'client_id', 'stage', 'value', 'expected_close_date', 'closed_at', 'lost_reason', 'notes', 'user_id'])]
class Opportunity extends Model
{
    /** @use HasFactory<OpportunityFactory> */
    use BelongsToCompany, HasFactory, HasUlids, SoftDeletes;

    protected $attributes = [
        'stage' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
