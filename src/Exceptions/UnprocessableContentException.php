<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class UnprocessableContentException extends HttpException
{
    public function __construct(string $message = 'Unprocessable Content', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::UNPROCESSABLE_CONTENT, $message, $code, $previous);
    }
}
