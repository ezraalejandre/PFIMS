<?php

namespace App\Exceptions;

use RuntimeException;

// Group the import validation exception actions.
class ImportValidationException extends RuntimeException
{
    // Set up the values this class needs.
    public function __construct(
        string $message,
        public readonly array $rowErrors = [],
    ) {
        parent::__construct($message);
    }
}
