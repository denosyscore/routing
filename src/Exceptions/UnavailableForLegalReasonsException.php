<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class UnavailableForLegalReasonsException extends HttpException
{
    public function __construct(string $message = 'Unavailable For Legal Reasons', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(HttpStatus::UNAVAILABLE_FOR_LEGAL_REASONS, $message, $code, $previous);
    }
}
