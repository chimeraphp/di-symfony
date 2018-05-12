<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\ServiceBus\Tactician;

use League\Tactician\Handler\CommandNameExtractor\ClassNameExtractor;
use League\Tactician\Handler\CommandNameExtractor\CommandNameExtractor;
use League\Tactician\Handler\MethodNameInflector\HandleInflector;
use League\Tactician\Handler\MethodNameInflector\MethodNameInflector;
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
        if (! $this->hasService($container, CommandNameExtractor::class)) {
            $container->setAlias(CommandNameExtractor::class, new Alias(ClassNameExtractor::class, false));
        }

        if ($this->hasService($container, MethodNameInflector::class)) {
            return;
        }

        $container->setAlias(MethodNameInflector::class, new Alias(HandleInflector::class, false));
    }

    private function hasService(ContainerBuilder $container, string $service): bool
    {
        return $container->hasAlias($service) || $container->hasDefinition($service);
    }
}
