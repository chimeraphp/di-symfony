<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App\ServiceBus;

use League\Tactician\Middleware;

/** @\Chimera\Mapping\ServiceBus\Middleware(bus="sample-app.command_bus") */
final class CommandOnlyMiddleware implements Middleware
{
    /** @inheritdoc */
    public function execute($command, callable $next)
    {
        return $next($command);
    }
}
