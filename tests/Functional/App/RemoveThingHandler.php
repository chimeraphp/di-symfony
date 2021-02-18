<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use Chimera\Mapping\Routing;
use Chimera\Mapping\ServiceBus;

/**
 * @Routing\ExecuteEndpoint("/things/{id}", command=RemoveThing::class, name="things.remove", methods={"DELETE"})
 * @ServiceBus\CommandHandler(RemoveThing::class)
 */
final class RemoveThingHandler
{
    public function handle(): void
    {
        // do something smart to remove the thing
    }
}
