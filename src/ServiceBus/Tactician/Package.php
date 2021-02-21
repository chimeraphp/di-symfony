<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\ServiceBus\Tactician;

use Chimera\DependencyInjection\ConditionallyLoadedPackage;
use Chimera\ServiceBus\Tactician\ServiceBus;
use Generator;
use Lcobucci\DependencyInjection\CompilerPassListProvider;
use Lcobucci\DependencyInjection\FileListProvider;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

use function class_exists;
use function dirname;

final class Package implements FileListProvider, CompilerPassListProvider, ConditionallyLoadedPackage
{
    private string $commandBusId;
    private string $queryBusId;

    public function __construct(
        string $commandBusId,
        string $queryBusId
    ) {
        $this->commandBusId = $commandBusId;
        $this->queryBusId   = $queryBusId;
    }

    public function getFiles(): Generator
    {
        yield dirname(__DIR__, 3) . '/config/bus-tactician.xml';
    }

    public function getCompilerPasses(): Generator
    {
        yield [new RegisterServices($this->commandBusId, $this->queryBusId), PassConfig::TYPE_BEFORE_OPTIMIZATION];
    }

    public function shouldBeLoaded(): bool
    {
        return class_exists(ServiceBus::class);
    }
}
