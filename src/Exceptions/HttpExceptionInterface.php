<?php

declare(strict_types=1);

namespace Denosys\Routing\Exceptions;

interface HttpExceptionInterface
{
    public function getStatusCode(): int;
    public function getReasonPhrase(): string;
}
