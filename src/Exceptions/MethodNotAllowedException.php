<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

use Throwable;

class MethodNotAllowedException extends HttpException
{
    public function __construct(
        protected array $allowedMethods,
        string $message = 'Method Not Allowed',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(HttpStatus::METHOD_NOT_ALLOWED, $message, $code, $previous);
    }

    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }
}
