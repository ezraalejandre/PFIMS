<?php

namespace App\Exceptions;

use RuntimeException;

class ImportValidationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $rowErrors = [],
    ) {
        parent::__construct($message);
    }
}
