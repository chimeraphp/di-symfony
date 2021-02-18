<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App\ServiceBus;

use League\Tactician\Middleware;

/** @\Chimera\Mapping\ServiceBus\Middleware(bus="sample-app.query_bus") */
final class QueryOnlyMiddleware implements Middleware
{
    /** @inheritdoc */
    public function execute($command, callable $next)
    {
        return $next($command);
    }
}
