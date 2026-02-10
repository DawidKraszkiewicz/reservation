<?php

declare(strict_types=1);

namespace App\Exception;

class SeatsNotAvailableException extends \RuntimeException
{
    public function __construct(string $message = 'Selected seats are not available', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
