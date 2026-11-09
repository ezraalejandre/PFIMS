<?php

namespace App\Services\ML;

use InvalidArgumentException;
use Phpml\Regression\Regression;
use RuntimeException;

/** Make predictions in PHP using a saved model. No other program needs to run. */
class PortableRbfSvr implements Regression
{
    // Set up the values this class needs.
    private function __construct(private int $featureCount, private float $gamma, private float $rho, private array $vectors) {}

    // Read the saved model so PHP can use it for predictions.
    public static function fromLibsvm(string $text, int $featureCount): self
    {
        // Stop if the feature count is below 1.
        if ($featureCount < 1) {
            throw new InvalidArgumentException('SVR requires at least one feature.');
        }
        $headers = $vectors = [];
        $inVectors = false;
        // Process each line.
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = trim($line);
            // Skip this item if the line is empty.
            if ($line === '') {
                continue;
            }
            // Start reading the saved model rows after the SV marker.
            if ($line === 'SV') {
                $inVectors = true;

                continue;
            }
            $parts = preg_split('/\s+/', $line);
            // Read the model settings before reading its rows.
            if (! $inVectors) {
                $headers[array_shift($parts)] = implode(' ', $parts);

                continue;
            }
            $coefficient = array_shift($parts);
            // Reject a model weight that is not a valid number.
            if (! is_numeric($coefficient) || ! is_finite((float) $coefficient)) {
                throw new InvalidArgumentException('Invalid SVR coefficient.');
            }
            $values = array_fill(0, $featureCount, 0.0);
            $lastIndex = 0;
            // Process each part.
            foreach ($parts as $part) {
                // Reject a model row with invalid input positions or numbers.
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
        // Reject a file with the wrong model type or row count.
        if (($headers['svm_type'] ?? null) !== 'epsilon_svr' || ($headers['kernel_type'] ?? null) !== 'rbf'
            || ($headers['nr_class'] ?? null) !== '2' || ! $inVectors
            || ! isset($headers['total_sv']) || ! ctype_digit($headers['total_sv'])
            || (int) $headers['total_sv'] !== count($vectors)) {
            throw new InvalidArgumentException('Only valid epsilon-SVR/RBF model files are supported.');
        }
        // Check both required model settings.
        foreach (['gamma', 'rho'] as $name) {
            // Reject a model setting that is not a valid number.
            if (! is_numeric($headers[$name] ?? null) || ! is_finite((float) $headers[$name])) {
                throw new InvalidArgumentException('Invalid SVR kernel parameters.');
            }
        }
        // Require a positive gamma value for the model math.
        if ((float) $headers['gamma'] <= 0) {
            throw new InvalidArgumentException('SVR gamma must be positive.');
        }

        return new self($featureCount, (float) $headers['gamma'], (float) $headers['rho'], $vectors);
    }

    // Stop training here; this class only reads saved models.
    public function train(array $samples, array $targets): void
    {
        throw new RuntimeException('Train SVR through the candidate evaluator, then import its verified model for serving.');
    }

    // Calculate a prediction from the supplied values.
    public function predict(array $samples)
    {
        // Predict each row separately when several rows are supplied.
        if (isset($samples[0]) && is_array($samples[0])) {
            return array_map(fn ($sample) => $this->predict($sample), $samples);
        }
        // Require the same number of inputs used during training.
        if (count($samples) !== $this->featureCount) {
            throw new InvalidArgumentException('SVR features do not match the trained model.');
        }
        // Process each value.
        foreach ($samples as $value) {
            // Reject an input that is not a valid number.
            if (! is_numeric($value) || ! is_finite((float) $value)) {
                throw new InvalidArgumentException('SVR prediction features must be finite numbers.');
            }
        }
        $result = -$this->rho;
        // Add the effect of each saved model row.
        foreach ($this->vectors as $vector) {
            $distance = 0.0;
            // Process each value.
            foreach ($samples as $index => $value) {
                $distance += ((float) $value - $vector['values'][$index]) ** 2;
            }
            $result += $vector['coefficient'] * exp(-$this->gamma * $distance);
        }

        return $result;
    }
}
