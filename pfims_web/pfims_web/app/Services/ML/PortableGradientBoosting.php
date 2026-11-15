<?php
namespace App\Services\ML;
use Phpml\Regression\Regression;
use RuntimeException;
use InvalidArgumentException;
/** Frozen sklearn regression trees, evaluated without a Python runtime. */
class PortableGradientBoosting implements Regression
{
    public function __construct(private array $data) {}
    public function train(array $samples, array $targets): void { throw new RuntimeException('Import a trained candidate.'); }
    public function predict(array $samples) {
        if (isset($samples[0]) && is_array($samples[0])) return array_map(fn($row) => $this->predict($row), $samples);
        if (count($samples) !== count($this->data['features'])) throw new InvalidArgumentException('Feature count mismatch.');
        foreach ($samples as $value) if (!is_numeric($value) || !is_finite((float)$value)) throw new InvalidArgumentException('Invalid feature.');
        // sklearn trees compare float32 inputs, including when serving float64 arrays.
        $samples = array_map(fn($value) => unpack('f', pack('f', (float)$value))[1], $samples);
        $result = $this->data['initial'];
        foreach ($this->data['trees'] as $tree) {
            $i=0;
            while ($tree['left'][$i] !== -1) $i = $samples[$tree['feature'][$i]] <= $tree['threshold'][$i] ? $tree['left'][$i] : $tree['right'][$i];
            $result += $this->data['rate'] * $tree['value'][$i];
        }
        return $result;
    }
}
