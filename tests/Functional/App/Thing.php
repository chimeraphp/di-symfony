<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use JMS\Serializer\Annotation as Serializer;
use Ramsey\Uuid\UuidInterface;

final class Thing
{
    public function __construct(
        #[Serializer\Type(UuidInterface::class)]
        public readonly UuidInterface $id,
        #[Serializer\Type('string')]
        public readonly string $name,
    ) {
    }
}
