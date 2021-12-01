<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection;

use Generator;
use Lcobucci\DependencyInjection\CompilerPassListProvider;
use Lcobucci\DependencyInjection\Config\Package;
use Lcobucci\DependencyInjection\FileListProvider;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

use function dirname;

final class RegisterApplication implements FileListProvider, CompilerPassListProvider
{
    /** @var list<ConditionallyLoadedPackage> */
    private array $relatedPackages;

    public function __construct(private string $name)
    {
        $commandBusId = $name . '.command_bus';
        $queryBusId   = $name . '.query_bus';

        $this->relatedPackages = [
            new MessageCreator\JmsSerializer\Package(),
            new Mapping\Package(),
            new ServiceBus\Tactician\Package($commandBusId, $queryBusId),
            new Routing\Mezzio\Package($name, $commandBusId, $queryBusId),
            new Routing\ErrorHandling\Package(),
        ];
    }

    public function getFiles(): Generator
    {
        yield dirname(__DIR__) . '/config/foundation.xml';
        yield dirname(__DIR__) . '/config/routing.xml';

        foreach ($this->filterPackages(FileListProvider::class) as $package) {
            yield from $package->getFiles();
        }
    }

    public function getCompilerPasses(): Generator
    {
        yield [new RegisterDefaultComponents(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -30];
        yield [new ValidateApplicationComponents($this->name), PassConfig::TYPE_OPTIMIZE, -30];

        foreach ($this->filterPackages(CompilerPassListProvider::class) as $package) {
            yield from $package->getCompilerPasses();
        }
    }

    /**
     * @template T of Package
     *
     * @param class-string<T> $type
     *
     * @return Generator<T>
     */
    private function filterPackages(string $type): Generator
    {
        foreach ($this->relatedPackages as $package) {
            if (! $package instanceof $type || ! $package->shouldBeLoaded()) {
                continue;
            }

            yield $package;
        }
    }
}
