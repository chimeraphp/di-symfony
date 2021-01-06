<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Mapping;

use Chimera\DependencyInjection\ConditionallyLoadedPackage;
use Chimera\Mapping\Annotation;
use Generator;
use Lcobucci\DependencyInjection\CompilerPassListProvider;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

use function interface_exists;

final class Package implements CompilerPassListProvider, ConditionallyLoadedPackage
{
    public function getCompilerPasses(): Generator
    {
        yield [new ExpandTags(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 50];
    }

    public function shouldBeLoaded(): bool
    {
        return interface_exists(Annotation::class);
    }
}
