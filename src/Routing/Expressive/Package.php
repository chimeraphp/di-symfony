<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Routing\Expressive;

use Chimera\DependencyInjection\ConditionallyLoadedPackage;
use Chimera\Routing\Expressive\RouteParamsExtractor;
use Generator;
use Lcobucci\DependencyInjection\CompilerPassListProvider;
use Lcobucci\DependencyInjection\FileListProvider;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use function class_exists;
use function dirname;

final class Package implements FileListProvider, CompilerPassListProvider, ConditionallyLoadedPackage
{
    /**
     * @var string
     */
    private $applicationName;

    /**
     * @var string
     */
    private $commandBusId;

    /**
     * @var string
     */
    private $queryBusId;

    public function __construct(
        string $applicationName,
        string $commandBusId,
        string $queryBusId
    ) {
        $this->applicationName = $applicationName;
        $this->commandBusId    = $commandBusId;
        $this->queryBusId      = $queryBusId;
    }

    public function getFiles(): Generator
    {
        yield dirname(__DIR__, 3) . '/config/routing-expressive.xml';
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
