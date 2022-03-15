<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use JMS\Serializer\Annotation\Type;
use Ramsey\Uuid\UuidInterface;

final class FetchThing
{
    #[Type(UuidInterface::class)]
    public UuidInterface $id;
}
