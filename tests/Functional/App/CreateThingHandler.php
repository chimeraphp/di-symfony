<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use Chimera\Mapping\Routing;
use Chimera\Mapping\ServiceBus;

/**
 * @Routing\CreateEndpoint("/things", command=CreateThing::class, name="things.create", redirectTo="things.fetch")
 * @ServiceBus\CommandHandler(CreateThing::class)
 */
final class CreateThingHandler
{
    public function handle(): void
    {
        // do something smart somewhere
    }
}
