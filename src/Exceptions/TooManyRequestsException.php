<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class TooManyRequestsException extends HttpException
{
    public function __construct(string $message = 'Too Many Requests', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::TOO_MANY_REQUESTS, $message, $code, $previous);
    }
}
