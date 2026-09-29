<?php

namespace App\Actions\Opportunities;

use App\Enums\OpportunityStage;
use Illuminate\Support\Carbon;

class ApplyStageTransitionEffects
{
    /**
     * Derives closed_at/lost_reason from the target stage — never from the
     * client. Only runs when "stage" is actually present in the payload, so
     * a partial PATCH that doesn't touch stage (e.g. only "title") leaves an
     * already-closed opportunity's closed_at/lost_reason untouched.
     *
     * No state machine: the new stage alone determines the result,
     * regardless of what the previous stage was — e.g. lost -> won still
     * clears lost_reason and sets a fresh closed_at, and new -> won behaves
     * identically to lost -> won.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        if (! array_key_exists('stage', $data)) {
            return $data;
        }

        $stage = $data['stage'] instanceof OpportunityStage
            ? $data['stage']
            : OpportunityStage::from($data['stage']);

        if ($stage === OpportunityStage::Lost) {
            $data['closed_at'] = Carbon::now();
        } elseif ($stage === OpportunityStage::Won) {
            $data['closed_at'] = Carbon::now();
            $data['lost_reason'] = null;
        } else {
            $data['closed_at'] = null;
            $data['lost_reason'] = null;
        }

        return $data;
    }
}
