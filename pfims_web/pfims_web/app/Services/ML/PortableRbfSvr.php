<?php

namespace App\Services\ML;

use InvalidArgumentException;
use Phpml\Regression\Regression;
use RuntimeException;

/** Portable inference from an epsilon-SVR/RBF libsvm model; no subprocess at prediction time. */
class PortableRbfSvr implements Regression
{
    private function __construct(private int $featureCount, private float $gamma, private float $rho, private array $vectors) {}

    public static function fromLibsvm(string $text, int $featureCount): self
    {
        if ($featureCount < 1) {
            throw new InvalidArgumentException('SVR requires at least one feature.');
        }
        $headers = $vectors = [];
        $inVectors = false;
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($line === 'SV') {
                $inVectors = true;

                continue;
            }
            $parts = preg_split('/\s+/', $line);
            if (! $inVectors) {
                $headers[array_shift($parts)] = implode(' ', $parts);

                continue;
            }
            $coefficient = array_shift($parts);
            if (! is_numeric($coefficient) || ! is_finite((float) $coefficient)) {
                throw new InvalidArgumentException('Invalid SVR coefficient.');
            }
            $values = array_fill(0, $featureCount, 0.0);
            $lastIndex = 0;
            foreach ($parts as $part) {
                if (! preg_match('/^(\d+):(.+)$/', $part, $match)
                    || (int) $match[1] <= $lastIndex || (int) $match[1] > $featureCount
                    || ! is_numeric($match[2]) || ! is_finite((float) $match[2])) {
                    throw new InvalidArgumentException('Invalid SVR support vector.');
                }
                $lastIndex = (int) $match[1];
                $values[$lastIndex - 1] = (float) $match[2];
            }
            $vectors[] = ['coefficient' => (float) $coefficient, 'values' => $values];
        }
        if (($headers['svm_type'] ?? null) !== 'epsilon_svr' || ($headers['kernel_type'] ?? null) !== 'rbf'
            || ($headers['nr_class'] ?? null) !== '2' || ! $inVectors
            || ! isset($headers['total_sv']) || ! ctype_digit($headers['total_sv'])
            || (int) $headers['total_sv'] !== count($vectors)) {
            throw new InvalidArgumentException('Only valid epsilon-SVR/RBF model files are supported.');
        }
        foreach (['gamma', 'rho'] as $name) {
            if (! is_numeric($headers[$name] ?? null) || ! is_finite((float) $headers[$name])) {
                throw new InvalidArgumentException('Invalid SVR kernel parameters.');
            }
        }
        if ((float) $headers['gamma'] <= 0) {
            throw new InvalidArgumentException('SVR gamma must be positive.');
        }

        return new self($featureCount, (float) $headers['gamma'], (float) $headers['rho'], $vectors);
    }

    public function train(array $samples, array $targets): void
    {
        throw new RuntimeException('Train SVR through the candidate evaluator, then import its verified model for serving.');
    }

    public function predict(array $samples)
    {
        if (isset($samples[0]) && is_array($samples[0])) {
            return array_map(fn ($sample) => $this->predict($sample), $samples);
        }
        if (count($samples) !== $this->featureCount) {
            throw new InvalidArgumentException('SVR features do not match the trained model.');
        }
        foreach ($samples as $value) {
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('SVR prediction features must be finite numbers.');
            }
        }
        $result = -$this->rho;
        foreach ($this->vectors as $vector) {
            $distance = 0.0;
            foreach ($samples as $index => $value) {
                $distance += ((float) $value - $vector['values'][$index]) ** 2;
            }
            $result += $vector['coefficient'] * exp(-$this->gamma * $distance);
        }

        return $result;
    }
}
