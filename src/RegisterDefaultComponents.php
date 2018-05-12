<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection;

use Chimera\IdentifierGenerator;
use Chimera\MessageCreator;
use Chimera\ServiceBus\ReadModelConverter;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class RegisterDefaultComponents implements CompilerPassInterface
{
    /**
     * @throws InvalidArgumentException
     */
    public function process(ContainerBuilder $container): void
    {
        if (! $this->hasService($container, IdentifierGenerator::class)) {
            $container->setAlias(IdentifierGenerator::class, new Alias(IdentifierGenerator\RamseyUuid::class, false));
        }

        if (! $this->hasService($container, MessageCreator::class)) {
            $container->setAlias(MessageCreator::class, new Alias(MessageCreator\NamedConstructor::class, false));
        }

        if ($this->hasService($container, ReadModelConverter::class)) {
            return;
        }

        $container->setAlias(ReadModelConverter::class, new Alias(ReadModelConverter\Callback::class, false));
    }

    private function hasService(ContainerBuilder $container, string $service): bool
    {
        return $container->hasAlias($service) || $container->hasDefinition($service);
    }
}
