<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class ExpectationFailedException extends HttpException
{
    public function __construct(string $message = 'Expectation Failed', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::EXPECTATION_FAILED, $message, $code, $previous);
    }
}
