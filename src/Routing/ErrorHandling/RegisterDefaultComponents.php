<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Routing\ErrorHandling;

use Lcobucci\ErrorHandling\DebugInfoStrategy;
use Lcobucci\ErrorHandling\StatusCodeExtractionStrategy;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class RegisterDefaultComponents implements CompilerPassInterface
{
    /** @throws InvalidArgumentException */
    public function process(ContainerBuilder $container): void
    {
        if (! $this->hasService($container, DebugInfoStrategy::class)) {
            $container->setAlias(DebugInfoStrategy::class, new Alias(DebugInfoStrategy\NoDebugInfo::class));
        }

        if ($this->hasService($container, StatusCodeExtractionStrategy::class)) {
            return;
        }

        $container->setAlias(
            StatusCodeExtractionStrategy::class,
            new Alias(StatusCodeExtractionStrategy\ClassMap::class)
        );
    }

    private function hasService(ContainerBuilder $container, string $service): bool
    {
        return $container->hasAlias($service) || $container->hasDefinition($service);
    }
}
