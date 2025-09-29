<?php

declare(strict_types=1);

namespace Denosys\Routing\ResponseConverters;

use Denosys\Routing\Priority;
use Psr\Http\Message\ResponseInterface;

class PsrResponseConverter implements ResponseConverterInterface
{
    public function canConvert(mixed $value): bool
    {
        return $value instanceof ResponseInterface;
    }

    public function convert(mixed $value): ResponseInterface
    {
        return $value;
    }

    public function getPriority(): int
    {
        return Priority::HIGHEST->value;
    }
}
