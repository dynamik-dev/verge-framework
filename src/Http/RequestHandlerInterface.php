<?php

declare(strict_types=1);

namespace Verge\Http;

use Psr\Http\Server\RequestHandlerInterface as PsrRequestHandlerInterface;

/**
 * Verge Request Handler Interface.
 *
 * Extends PSR-15's RequestHandlerInterface for full compatibility.
 */
interface RequestHandlerInterface extends PsrRequestHandlerInterface
{
}
