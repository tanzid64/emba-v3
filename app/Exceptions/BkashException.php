<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class BkashException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
