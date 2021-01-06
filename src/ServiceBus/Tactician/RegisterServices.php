<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\ServiceBus\Tactician;

use Chimera\DependencyInjection\Tags;
use Chimera\ServiceBus\Tactician\ReadModelConversionMiddleware;
use Chimera\ServiceBus\Tactician\ServiceBus;
use Exception;
use League\Tactician\CommandBus;
use League\Tactician\Container\ContainerLocator;
use League\Tactician\Handler\CommandHandlerMiddleware;
use League\Tactician\Handler\CommandNameExtractor\CommandNameExtractor;
use League\Tactician\Handler\MethodNameInflector\MethodNameInflector;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

use function array_combine;
use function array_map;
use function array_values;
use function assert;
use function is_array;
use function is_string;
use function krsort;
use function sprintf;

final class RegisterServices implements CompilerPassInterface
{
    private const INVALID_HANDLER     = 'You must specify the "bus" and "handles" arguments in "%s" (tag "%s").';
    private const INVALID_BUS_HANDLER = 'You must specify the "handles" argument in "%s" (tag "%s").';

    private string $commandBusId;
    private string $queryBusId;

    public function __construct(string $commandBusId, string $queryBusId)
    {
        $this->commandBusId = $commandBusId;
        $this->queryBusId   = $queryBusId;
    }

    /** @throws Exception */
    public function process(ContainerBuilder $container): void
    {
        $handlerList    = $this->extractHandlers($container);
        $middlewareList = $this->extractMiddleware($container);

        $this->registerBus(
            $this->commandBusId,
            $container,
            $handlerList[$this->commandBusId] ?? [],
            $this->prioritiseMiddlewareList($middlewareList[$this->commandBusId] ?? [])
        );

        $queryMiddleware   = $this->prioritiseMiddlewareList($middlewareList[$this->queryBusId] ?? []);
        $queryMiddleware[] = new Reference(ReadModelConversionMiddleware::class);

        $this->registerBus(
            $this->queryBusId,
            $container,
            $handlerList[$this->queryBusId] ?? [],
            $queryMiddleware
        );
    }

    /**
     * @return string[][]
     *
     * @throws Exception
     */
    private function extractHandlers(ContainerBuilder $container): array
    {
        $list = [];

        foreach ($container->findTaggedServiceIds(Tags::BUS_HANDLER) as $serviceId => $tags) {
            assert(is_array($tags));
            assert(is_string($serviceId));

            foreach ($tags as $tag) {
                if (! isset($tag['bus'], $tag['handles'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::INVALID_HANDLER, $serviceId, Tags::BUS_HANDLER)
                    );
                }

                $list = $this->appendHandler($list, $tag['bus'], $tag['handles'], $serviceId);
            }
        }

        foreach ($container->findTaggedServiceIds(Tags::BUS_COMMAND_HANDLER) as $serviceId => $tags) {
            assert(is_array($tags));
            assert(is_string($serviceId));

            foreach ($tags as $tag) {
                if (! isset($tag['handles'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::INVALID_BUS_HANDLER, $serviceId, Tags::BUS_COMMAND_HANDLER)
                    );
                }

                $list = $this->appendHandler($list, $this->commandBusId, $tag['handles'], $serviceId);
            }
        }

        foreach ($container->findTaggedServiceIds(Tags::BUS_QUERY_HANDLER) as $serviceId => $tags) {
            assert(is_array($tags));
            assert(is_string($serviceId));

            foreach ($tags as $tag) {
                if (! isset($tag['handles'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::INVALID_BUS_HANDLER, $serviceId, Tags::BUS_QUERY_HANDLER)
                    );
                }

                $list = $this->appendHandler($list, $this->queryBusId, $tag['handles'], $serviceId);
            }
        }

        return $list;
    }

    /**
     * @param string[][] $list
     *
     * @return string[][]
     */
    private function appendHandler(array $list, string $busId, string $message, string $serviceId): array
    {
        $list[$busId]         ??= [];
        $list[$busId][$message] = $serviceId;

        return $list;
    }

    /** @return Reference[][][] */
    private function extractMiddleware(ContainerBuilder $container): array
    {
        $list = [];

        foreach ($container->findTaggedServiceIds(Tags::BUS_MIDDLEWARE) as $serviceId => $tags) {
            assert(is_array($tags));
            assert(is_string($serviceId));

            foreach ($tags as $tag) {
                $priority = $tag['priority'] ?? 0;

                if (! isset($tag['bus'])) {
                    $list = $this->appendMiddleware($list, $this->commandBusId, $priority, $serviceId);
                    $list = $this->appendMiddleware($list, $this->queryBusId, $priority, $serviceId);

                    continue;
                }

                $list = $this->appendMiddleware($list, $tag['bus'], $priority, $serviceId);
            }
        }

        return $list;
    }

    /**
     * @param Reference[][][] $list
     *
     * @return Reference[][][]
     */
    private function appendMiddleware(array $list, string $busId, int $priority, string $serviceId): array
    {
        $list[$busId]            ??= [];
        $list[$busId][$priority] ??= [];
        $list[$busId][$priority][] = new Reference($serviceId);

        return $list;
    }

    /**
     * @param Reference[][] $middlewareList
     *
     * @return Reference[]
     */
    private function prioritiseMiddlewareList(array $middlewareList): array
    {
        krsort($middlewareList);

        $prioritised = [];

        foreach ($middlewareList as $list) {
            foreach ($list as $reference) {
                $prioritised[] = $reference;
            }
        }

        return $prioritised;
    }

    /**
     * @param string[]    $handlers
     * @param Reference[] $middlewareList
     *
     * @throws BadMethodCallException
     */
    private function registerBus(
        string $id,
        ContainerBuilder $container,
        array $handlers,
        array $middlewareList
    ): void {
        $tacticianBus = $this->registerTacticianBus(
            $id . '.decorated_bus',
            $container,
            $handlers,
            $middlewareList
        );

        $container->setDefinition(
            $id,
            $this->createService(ServiceBus::class, [$tacticianBus])
        );
    }

    /**
     * @param string[]    $handlers
     * @param Reference[] $middlewareList
     *
     * @throws BadMethodCallException
     */
    private function registerTacticianBus(
        string $id,
        ContainerBuilder $container,
        array $handlers,
        array $middlewareList
    ): Reference {
        $middlewareList[] = $this->registerTacticianHandler($container, $id, $handlers);

        $container->setDefinition($id, $this->createService(CommandBus::class, [$middlewareList]));

        return new Reference($id);
    }

    /**
     * @param string[] $handlers
     *
     * @throws BadMethodCallException
     */
    private function registerTacticianHandler(ContainerBuilder $container, string $busId, array $handlers): Reference
    {
        $id = $busId . '.handler';

        $arguments = [
            new Reference(CommandNameExtractor::class),
            $this->registerTacticianLocator($container, $id, $handlers),
            new Reference(MethodNameInflector::class),
        ];

        $container->setDefinition($id, $this->createService(CommandHandlerMiddleware::class, $arguments));

        return new Reference($id);
    }

    /**
     * @param string[] $handlers
     *
     * @throws BadMethodCallException
     */
    private function registerTacticianLocator(
        ContainerBuilder $container,
        string $handlerId,
        array $handlers
    ): Reference {
        $id = $handlerId . '.locator';

        $container->setDefinition(
            $id,
            $this->createService(
                ContainerLocator::class,
                [$this->registerServiceLocator($container, $handlers), $handlers]
            )
        );

        return new Reference($id);
    }

    /** @param string[] $handlers */
    private function registerServiceLocator(ContainerBuilder $container, array $handlers): Reference
    {
        $serviceIds = array_values($handlers);

        return ServiceLocatorTagPass::register(
            $container,
            array_map(
                static function (string $id): Reference {
                    return new Reference($id);
                },
                (array) array_combine($serviceIds, $serviceIds)
            )
        );
    }

    /** @param mixed[] $arguments */
    private function createService(string $class, array $arguments = []): Definition
    {
        return (new Definition($class, $arguments))->setPublic(false);
    }
}
