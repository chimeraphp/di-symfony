<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use Chimera\Mapping\Routing;
use Chimera\Mapping\ServiceBus;
use Ramsey\Uuid\Uuid;

use function assert;

/** @Routing\ExecuteEndpoint("/things/{id}", command=RemoveThing::class, name="things.remove", methods={"DELETE"}) */
final class RemoveThingHandler
{
    /** @ServiceBus\CommandHandler */
    public function removeIt(RemoveThing $command): void
    {
        // do something smart to remove the thing

        assert(! $command->id->equals(Uuid::uuid4()));
    }
}
