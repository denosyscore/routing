<?php

declare(strict_types=1);

namespace Denosys\Routing;

use Denosys\Routing\Contracts\RouteInfoInterface;
use Denosys\Routing\Contracts\RouteMatcherInterface;
use Denosys\Routing\Contracts\RouteMiddlewareInterface;
use Denosys\Routing\Contracts\RouteNamingInterface;
use Denosys\Routing\Contracts\RouteConstraintInterface;
use Denosys\Routing\Contracts\HostMatchingInterface;
use Denosys\Routing\Contracts\PortMatchingInterface;
use Denosys\Routing\Contracts\SchemeMatchingInterface;

/**
 * Complete route interface extending all segregated interfaces.
 * Provides full route functionality while allowing clients to depend
 * only on specific aspects through the segregated interfaces.
 */
interface RouteInterface extends
    RouteInfoInterface,
    RouteMatcherInterface,
    RouteMiddlewareInterface,
    RouteNamingInterface,
    RouteConstraintInterface,
    HostMatchingInterface,
    PortMatchingInterface,
    SchemeMatchingInterface
{
    // This interface now provides all methods through its parent interfaces
    // Clients can depend on specific capabilities by using the segregated interfaces
}
