<?php

namespace App\Core\Support;

use RuntimeException;

/** A business-rule failure shown to the user as-is. */
class DomainException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422, public array $extra = [])
    {
        parent::__construct($message);
    }
}
