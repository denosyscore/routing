<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class PreconditionRequiredException extends HttpException
{
    public function __construct(string $message = 'Precondition Required', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::PRECONDITION_REQUIRED, $message, $code, $previous);
    }
}
