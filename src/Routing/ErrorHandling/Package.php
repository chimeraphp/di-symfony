<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Routing\ErrorHandling;

use Chimera\DependencyInjection\ConditionallyLoadedPackage;
use Generator;
use Lcobucci\DependencyInjection\CompilerPassListProvider;
use Lcobucci\DependencyInjection\FileListProvider;
use Lcobucci\ErrorHandling\ErrorConversionMiddleware;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

use function class_exists;
use function dirname;

final class Package implements FileListProvider, CompilerPassListProvider, ConditionallyLoadedPackage
{
    public function getFiles(): Generator
    {
        yield dirname(__DIR__, 3) . '/config/routing-error-handling.xml';
    }

    public function shouldBeLoaded(): bool
    {
        return class_exists(ErrorConversionMiddleware::class);
    }

    public function getCompilerPasses(): Generator
    {
        yield [new RegisterDefaultComponents(), PassConfig::TYPE_BEFORE_OPTIMIZATION];
    }
}
