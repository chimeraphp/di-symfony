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
    public function handle(CreateThing $command): void
    {
        if ($command->name !== 'Testing') {
            return;
        }

        throw new NameNotAllowed('"Testing" is a forbidden name in this application');
    }
}
