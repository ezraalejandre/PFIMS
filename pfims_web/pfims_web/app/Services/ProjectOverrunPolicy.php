<?php

namespace App\Services;

class ProjectOverrunPolicy
{
    public const MATERIAL_TOLERANCE = 0.05;

    public function classify(float $cost, ?float $budget): array
    {
        if ($budget === null || $budget <= 0 || ! is_finite($budget) || ! is_finite($cost) || $cost < 0) {
            return ['any_overrun' => null, 'material_overrun' => null, 'variance' => null, 'variance_percentage' => null];
        }
        $cost = round($cost, 2);
        $budget = round($budget, 2);
        if ($budget <= 0) {
            return ['any_overrun' => null, 'material_overrun' => null, 'variance' => null, 'variance_percentage' => null];
        }

        return [
            'any_overrun' => $cost > $budget,
            'material_overrun' => $cost > round($budget * (1 + self::MATERIAL_TOLERANCE), 2),
            'variance' => round($cost - $budget, 2),
            'variance_percentage' => round(($cost - $budget) / $budget * 100, 4),
        ];
    }

    public function outcomes(float $cost, array $context): array
    {
        return [
            'current_budget' => $this->classify($cost, $context['current_budget_amount'] ?? null),
            'original_budget' => $this->classify($cost, $context['original_budget_amount'] ?? null),
            'material_overrun_threshold_percent' => self::MATERIAL_TOLERANCE * 100,
        ];
    }
}
