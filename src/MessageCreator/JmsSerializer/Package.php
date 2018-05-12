<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\MessageCreator\JmsSerializer;

use Chimera\DependencyInjection\ConditionallyLoadedPackage;
use Chimera\MessageCreator\JmsSerializer\ArrayTransformer;
use Generator;
use Lcobucci\DependencyInjection\FileListProvider;
use function class_exists;
use function dirname;

final class Package implements FileListProvider, ConditionallyLoadedPackage
{
    public function getFiles(): Generator
    {
        yield dirname(__DIR__, 3) . '/config/serialization-jms.xml';
    }

    public function shouldBeLoaded(): bool
    {
        return class_exists(ArrayTransformer::class);
    }
}
