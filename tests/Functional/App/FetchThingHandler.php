<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use Chimera\Mapping\Routing;
use Chimera\Mapping\ServiceBus;

/**
 * @Routing\FetchEndpoint("/things/{id}", query=FetchThing::class, name="things.fetch")
 * @ServiceBus\QueryHandler(FetchThing::class)
 */
final class FetchThingHandler
{
    public function handle(FetchThing $query): Thing
    {
        return new Thing($query->id, 'a random name');
    }
}
