<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use Chimera\Mapping\Routing;
use Chimera\Mapping\ServiceBus;
use Ramsey\Uuid\Uuid;

/**
 * @Routing\FetchEndpoint("/things", query=ListThings::class, name="things.list")
 * @ServiceBus\QueryHandler(ListThings::class)
 */
final class ListThingsHandler
{
    /** @return list<Thing> */
    public function handle(): array
    {
        return [
            new Thing(Uuid::uuid4(), 'one'),
            new Thing(Uuid::uuid4(), 'two'),
            new Thing(Uuid::uuid4(), 'three'),
        ];
    }
}
