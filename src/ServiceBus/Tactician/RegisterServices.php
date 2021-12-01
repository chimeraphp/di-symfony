<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\ServiceBus\Tactician;

use Chimera\DependencyInjection\Tags;
use Chimera\ServiceBus\Tactician\CommandHandler;
use Chimera\ServiceBus\Tactician\ReadModelConversionMiddleware;
use Chimera\ServiceBus\Tactician\ServiceBus;
use Exception;
use League\Tactician\CommandBus;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

use function array_column;
use function array_combine;
use function array_map;
use function krsort;
use function sprintf;

final class RegisterServices implements CompilerPassInterface
{
    private const INVALID_HANDLER     = 'You must specify the "bus" and "handles" arguments in "%s" (tag "%s").';
    private const INVALID_BUS_HANDLER = 'You must specify the "handles" argument in "%s" (tag "%s").';

    public function __construct(private string $commandBusId, private string $queryBusId)
    {
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
            $this->prioritiseMiddlewareList($middlewareList[$this->commandBusId] ?? []),
        );

        $queryMiddleware   = $this->prioritiseMiddlewareList($middlewareList[$this->queryBusId] ?? []);
        $queryMiddleware[] = new Reference(ReadModelConversionMiddleware::class);

        $this->registerBus(
            $this->queryBusId,
            $container,
            $handlerList[$this->queryBusId] ?? [],
            $queryMiddleware,
        );
    }

    /**
     * @return array<string, array<string, array{service: string, method: string}>>
     *
     * @throws Exception
     */
    private function extractHandlers(ContainerBuilder $container): array
    {
        $list = [];

        foreach ($container->findTaggedServiceIds(Tags::BUS_HANDLER) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (! isset($tag['bus'], $tag['handles'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::INVALID_HANDLER, $serviceId, Tags::BUS_HANDLER),
                    );
                }

                $list = $this->appendHandler($list, $tag['bus'], $tag['handles'], $serviceId, 'handle');
            }
        }

        foreach ($container->findTaggedServiceIds(Tags::BUS_COMMAND_HANDLER) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (! isset($tag['handles'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::INVALID_BUS_HANDLER, $serviceId, Tags::BUS_COMMAND_HANDLER),
                    );
                }

                $list = $this->appendHandler($list, $this->commandBusId, $tag['handles'], $serviceId, $tag['method']);
            }
        }

        foreach ($container->findTaggedServiceIds(Tags::BUS_QUERY_HANDLER) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (! isset($tag['handles'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::INVALID_BUS_HANDLER, $serviceId, Tags::BUS_QUERY_HANDLER),
                    );
                }

                $list = $this->appendHandler($list, $this->queryBusId, $tag['handles'], $serviceId, $tag['method']);
            }
        }

        return $list;
    }

    /**
     * @param array<string, array<string, array{service: string, method: string}>> $list
     *
     * @return array<string, array<string, array{service: string, method: string}>>
     */
    private function appendHandler(
        array $list,
        string $busId,
        string $message,
        string $serviceId,
        string $method,
    ): array {
        $list[$busId]         ??= [];
        $list[$busId][$message] = ['service' => $serviceId, 'method' => $method];

        return $list;
    }

    /** @return Reference[][][] */
    private function extractMiddleware(ContainerBuilder $container): array
    {
        $list = [];

        foreach ($container->findTaggedServiceIds(Tags::BUS_MIDDLEWARE) as $serviceId => $tags) {
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
     * @param array<string, array{service: string, method: string}> $handlers
     * @param Reference[]                                           $middlewareList
     *
     * @throws BadMethodCallException
     */
    private function registerBus(
        string $id,
        ContainerBuilder $container,
        array $handlers,
        array $middlewareList,
    ): void {
        $tacticianBus = $this->registerTacticianBus(
            $id . '.decorated_bus',
            $container,
            $handlers,
            $middlewareList,
        );

        $container->setDefinition(
            $id,
            $this->createService(ServiceBus::class, [$tacticianBus]),
        );
    }

    /**
     * @param array<string, array{service: string, method: string}> $handlers
     * @param Reference[]                                           $middlewareList
     *
     * @throws BadMethodCallException
     */
    private function registerTacticianBus(
        string $id,
        ContainerBuilder $container,
        array $handlers,
        array $middlewareList,
    ): Reference {
        $middlewareList[] = $this->registerTacticianHandler($container, $id, $handlers);

        $container->setDefinition($id, $this->createService(CommandBus::class, [$middlewareList]));

        return new Reference($id);
    }

    /**
     * @param array<string, array{service: string, method: string}> $handlers
     *
     * @throws BadMethodCallException
     */
    private function registerTacticianHandler(ContainerBuilder $container, string $busId, array $handlers): Reference
    {
        $id = $busId . '.handler';

        $arguments = [
            $this->registerServiceLocator($container, array_column($handlers, 'service')),
            $handlers,
        ];

        $container->setDefinition($id, $this->createService(CommandHandler::class, $arguments));
        $container->setAlias($id . '.locator', $id);

        return new Reference($id);
    }

    /** @param list<string> $serviceIds */
    private function registerServiceLocator(ContainerBuilder $container, array $serviceIds): Reference
    {
        return ServiceLocatorTagPass::register(
            $container,
            array_map(
                static fn (string $id): Reference => new Reference($id),
                array_combine($serviceIds, $serviceIds),
            ),
        );
    }

    /** @param mixed[] $arguments */
    private function createService(string $class, array $arguments = []): Definition
    {
        return (new Definition($class, $arguments))->setPublic(false);
    }
}
