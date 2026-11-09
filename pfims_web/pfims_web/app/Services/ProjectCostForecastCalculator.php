<?php

namespace App\Services;

use RuntimeException;

// Group the project cost forecast calculator actions.
class ProjectCostForecastCalculator
{
    // Get the calculate.
    public function calculate(float $estimate, float $recordedSpend, bool $remainingCost): array
    {
        // Stop if the estimate and recorded spend checks match.
        if (! is_finite($estimate) || ! is_finite($recordedSpend) || $recordedSpend < 0) {
            throw new RuntimeException('Forecast inputs must be finite and recorded spending non-negative.');
        }
        $rawFinalCost = $remainingCost ? $estimate + $recordedSpend : $estimate;
        // Stop if the raw final cost is not a finite number.
        if (! is_finite($rawFinalCost)) {
            throw new RuntimeException('Final-cost conversion exceeded the supported numeric range.');
        }

        return [
            'prediction_target' => $remainingCost ? 'remaining_cost' : 'final_cost',
            'raw_model_estimate' => $estimate,
            'recorded_spend' => $recordedSpend,
            'raw_final_cost' => $rawFinalCost,
            'final_cost' => max($recordedSpend, $rawFinalCost),
            'spend_floor_applied' => $rawFinalCost < $recordedSpend,
            'formula' => $remainingCost
                ? 'Final cost = recorded spending + estimated remaining cost; minimum is recorded spending.'
                : 'Final cost = estimated total cost; minimum is recorded spending.',
        ];
    }
}
