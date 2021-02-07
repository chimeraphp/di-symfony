<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App\ServiceBus;

use League\Tactician\Middleware;

/** @\Chimera\Mapping\ServiceBus\Middleware(priority=10) */
final class CommandAndQueryMiddleware implements Middleware
{
    /** @inheritdoc */
    public function execute($command, callable $next)
    {
        return $next($command);
    }
}
