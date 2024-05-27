<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthorized', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::UNAUTHORIZED, $message, $code, $previous);
    }
}
