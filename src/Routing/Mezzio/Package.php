<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Routing\Mezzio;

use Chimera\DependencyInjection\ConditionallyLoadedPackage;
use Chimera\Routing\Mezzio\RouteParamsExtractor;
use Generator;
use Lcobucci\DependencyInjection\CompilerPassListProvider;
use Lcobucci\DependencyInjection\FileListProvider;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

use function class_exists;
use function dirname;

final class Package implements FileListProvider, CompilerPassListProvider, ConditionallyLoadedPackage
{
    public function __construct(
        private readonly string $applicationName,
        private readonly string $commandBusId,
        private readonly string $queryBusId,
    ) {
    }

    public function getFiles(): Generator
    {
        yield dirname(__DIR__, 3) . '/config/routing-mezzio.xml';
    }

    public function getCompilerPasses(): Generator
    {
        yield [
            new RegisterServices($this->applicationName, $this->commandBusId, $this->queryBusId),
            PassConfig::TYPE_BEFORE_OPTIMIZATION,
        ];
    }

    public function shouldBeLoaded(): bool
    {
        return class_exists(RouteParamsExtractor::class);
    }
}
