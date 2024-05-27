<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class PreconditionFailedException extends HttpException
{
    public function __construct(string $message = 'Precondition Failed', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::PRECONDITION_FAILED, $message, $code, $previous);
    }
}
