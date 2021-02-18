<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use JMS\Serializer\Annotation as Serializer;
use Ramsey\Uuid\UuidInterface;

final class Thing
{
    /** @Serializer\Type(UuidInterface::class) */
    public UuidInterface $id;

    /** @Serializer\Type("string") */
    public string $name;

    public function __construct(UuidInterface $id, string $name)
    {
        $this->id   = $id;
        $this->name = $name;
    }
}
