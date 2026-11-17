<?php

namespace App\Services\ML;

use InvalidArgumentException;

/** Evaluate the exported forest in PHP using the same inputs as its Python fit. */
class PortableRandomForestClassifier extends BudgetOverrunClassifier
{
    private array $forest;

    public static function fromModelData(array $data): self
    {
        if (($data['algorithm'] ?? '') !== 'random_forest_classifier' || count($data['ranges'] ?? []) !== 16
            || !in_array($data['view'] ?? '', ['pipeline16', 'corrected16'], true)
            || empty($data['trees']) || count($data['trees']) > 1000) throw new InvalidArgumentException('Invalid forest dimensions.');
        foreach ($data['trees'] as $tree) {
            $count = count($tree);
            if ($count < 1 || $count > 100000) throw new InvalidArgumentException('Invalid forest tree.');
            foreach ($tree as $i => $node) {
                if (count($node) !== 5) throw new InvalidArgumentException('Invalid forest node.');
                [$feature, $threshold, $left, $right, $probability] = $node;
                if (!is_numeric($probability) || !is_finite((float) $probability) || $probability < 0 || $probability > 1) throw new InvalidArgumentException('Invalid forest probability.');
                if ($left === -1 && $right === -1) continue;
                if (!is_int($feature) || $feature < 0 || $feature >= 16 || !is_numeric($threshold) || !is_finite((float) $threshold)
                    || !is_int($left) || !is_int($right) || $left <= $i || $right <= $i || $left >= $count || $right >= $count) throw new InvalidArgumentException('Invalid forest branches.');
            }
        }
        $model = new self;
        $model->forest = $data;
        return $model;
    }

    public function score(array $features): float
    {
        if (count($features) !== 16) throw new InvalidArgumentException('Forest requires sixteen inputs.');
        foreach ($features as $v) if (!is_numeric($v) || !is_finite((float) $v)) throw new InvalidArgumentException('Invalid forest input.');
        $features[1] = max(1, ($features[3] + $features[4]) / 30);
        if ($this->forest['view'] === 'pipeline16') $features[3] = $features[4] = 0.0;
        // sklearn trees compare float32 inputs to their stored split thresholds.
        $features = array_map(fn ($v) => unpack('f', pack('f', (float) $v))[1], $features);
        $total = 0.0;
        foreach ($this->forest['trees'] as $tree) {
            $index = 0;
            while ($tree[$index][2] !== -1) {
                [$feature, $threshold, $left, $right] = $tree[$index];
                $index = $features[$feature] <= $threshold ? $left : $right;
            }
            $total += $tree[$index][4];
        }
        return $total / count($this->forest['trees']);
    }

    public function modelData(): array { return $this->forest; }
}
