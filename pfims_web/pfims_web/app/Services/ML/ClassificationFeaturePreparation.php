<?php

namespace App\Services\ML;

use InvalidArgumentException;

/** Versioned candidate preparation. Does not change existing model inputs. */
class ClassificationFeaturePreparation
{
    public const DERIVED = ['spending_pace_ratio', 'remaining_budget_ratio', 'recent_burn_budget_ratio'];

    public static function values(array $row, array $names): array
    {
        $budget = (float) ($row['budget'] ?? 0);
        $elapsed = (float) ($row['elapsed_time_fraction'] ?? 0);
        if ($budget <= 0 || $elapsed <= 0) throw new InvalidArgumentException('Positive budget and elapsed planned time are required.');
        $spent = (float) $row['fin_total_expense'];
        $derived = ['spending_pace_ratio' => ($spent / $budget) / $elapsed,
            'remaining_budget_ratio' => ($budget - $spent) / $budget,
            'recent_burn_budget_ratio' => (float) ($row['cost_burn_rate_30d'] ?? 0) * max(1, (float) ($row['elapsed_days'] ?? 0) + (float) ($row['remaining_planned_days'] ?? 0)) / $budget];
        return array_map(function ($name) use ($row, $derived) {
            $value = $derived[$name] ?? $row[$name] ?? null;
            if (! is_numeric($value) || ! is_finite((float) $value)) throw new InvalidArgumentException('Missing/non-finite classifier input: '.$name);
            return (float) $value;
        }, $names);
    }
}
