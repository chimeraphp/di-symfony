<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App\Serialization;

use JMS\Serializer\GraphNavigatorInterface;
use JMS\Serializer\Handler\SubscribingHandlerInterface;
use JMS\Serializer\JsonDeserializationVisitor;
use JMS\Serializer\Visitor\DeserializationVisitorInterface;
use JMS\Serializer\Visitor\SerializationVisitorInterface;
use Ramsey\Uuid\Guid\Guid;
use Ramsey\Uuid\Lazy\LazyUuidFromString;
use Ramsey\Uuid\Nonstandard\UuidV6;
use Ramsey\Uuid\Rfc4122\UuidV1;
use Ramsey\Uuid\Rfc4122\UuidV2;
use Ramsey\Uuid\Rfc4122\UuidV3;
use Ramsey\Uuid\Rfc4122\UuidV4;
use Ramsey\Uuid\Rfc4122\UuidV5;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function assert;

final class UuidHandler implements SubscribingHandlerInterface
{
    private const TYPES = [
        UuidInterface::class,
        Uuid::class,
        LazyUuidFromString::class,
        Guid::class,
        UuidV1::class,
        UuidV2::class,
        UuidV3::class,
        UuidV4::class,
        UuidV5::class,
        UuidV6::class,
    ];

    /**
     * @inheritdoc
     * @phpstan-ignore-next-line
     */
    public static function getSubscribingMethods(): array
    {
        $methods = [];

        foreach (self::TYPES as $type) {
            $methods[] = [
                'type' => $type,
                'direction' => GraphNavigatorInterface::DIRECTION_SERIALIZATION,
                'format' => 'json',
                'method' => 'serialize',
            ];

            $methods[] = [
                'type' => $type,
                'direction' => GraphNavigatorInterface::DIRECTION_DESERIALIZATION,
                'format' => 'json',
                'method' => 'deserialize',
            ];
        }

        return $methods;
    }

    /**
     * @param mixed[] $type
     *
     * @return mixed
     */
    public function serialize(SerializationVisitorInterface $visitor, UuidInterface $uuid, array $type)
    {
        return $visitor->visitString($uuid->toString(), $type);
    }

    /** @param string|UuidInterface|null $data */
    public function deserialize(DeserializationVisitorInterface $visitor, $data): ?UuidInterface
    {
        assert($visitor instanceof JsonDeserializationVisitor);
        if ($data === null) {
            return null;
        }

        if ($data instanceof UuidInterface) {
            return $data;
        }

        return Uuid::fromString($data);
    }
}
