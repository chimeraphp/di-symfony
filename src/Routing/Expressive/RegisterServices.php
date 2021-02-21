<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Routing\Expressive;

use Chimera\DependencyInjection\Routing\Priorities;
use Chimera\DependencyInjection\Tags;
use Chimera\ExecuteCommand;
use Chimera\ExecuteQuery;
use Chimera\IdentifierGenerator;
use Chimera\MessageCreator;
use Chimera\Routing\Expressive\Application;
use Chimera\Routing\Expressive\UriGenerator;
use Chimera\Routing\Handler\CreateAndFetch;
use Chimera\Routing\Handler\CreateOnly;
use Chimera\Routing\Handler\ExecuteAndFetch;
use Chimera\Routing\Handler\ExecuteOnly;
use Chimera\Routing\Handler\FetchOnly;
use Chimera\Routing\MissingRouteDispatching;
use Chimera\Routing\RouteParamsExtraction;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use Lcobucci\ContentNegotiation\ContentTypeMiddleware;
use Lcobucci\ContentNegotiation\Formatter\Json;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Zend\Diactoros\ServerRequestFactory;
use Zend\Expressive\Application as Expressive;
use Zend\Expressive\Helper\BodyParams\BodyParamsMiddleware;
use Zend\Expressive\Middleware\LazyLoadingMiddleware;
use Zend\Expressive\MiddlewareContainer;
use Zend\Expressive\MiddlewareFactory;
use Zend\Expressive\Response\ServerRequestErrorResponseGenerator;
use Zend\Expressive\Router\FastRouteRouter;
use Zend\Expressive\Router\Middleware\DispatchMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitHeadMiddleware;
use Zend\Expressive\Router\Middleware\ImplicitOptionsMiddleware;
use Zend\Expressive\Router\Middleware\MethodNotAllowedMiddleware;
use Zend\Expressive\Router\Middleware\RouteMiddleware;
use Zend\Expressive\Router\RouteCollector;
use Zend\HttpHandlerRunner\Emitter\EmitterInterface;
use Zend\HttpHandlerRunner\RequestHandlerRunner;
use Zend\Stratigility\Middleware\PathMiddlewareDecorator;
use Zend\Stratigility\MiddlewarePipe;

use function array_combine;
use function array_map;
use function assert;
use function explode;
use function is_string;
use function krsort;
use function sprintf;

final class RegisterServices implements CompilerPassInterface
{
    private const MESSAGE_INVALID_ROUTE = 'You must specify the "route_name", "path", and "behavior" arguments in '
    . '"%s" (tag "%s").';

    private const BEHAVIORS = [
        'fetch'         => ['methods' => ['GET'], 'callback' => 'fetchOnly'],
        'create'        => ['methods' => ['POST'], 'callback' => 'createOnly'],
        'create_fetch'  => ['methods' => ['POST'], 'callback' => 'createAndFetch'],
        'execute'       => ['methods' => ['PATCH', 'PUT', 'DELETE'], 'callback' => 'executeOnly'],
        'execute_fetch' => ['methods' => ['PATCH', 'PUT'], 'callback' => 'executeAndFetch'],
        'none'          => ['methods' => ['GET'], 'callback' => 'noBehavior'],
    ];

    private string $applicationName;
    private string $commandBusId;
    private string $queryBusId;

    public function __construct(
        string $applicationName,
        string $commandBusId,
        string $queryBusId
    ) {
        $this->applicationName = $applicationName;
        $this->commandBusId    = $commandBusId;
        $this->queryBusId      = $queryBusId;
    }

    public function process(ContainerBuilder $container): void
    {
        $routes         = $this->extractRoutes($container);
        $middlewareList = $this->extractMiddlewareList($container);

        $this->registerApplication(
            $container,
            $routes[$this->applicationName] ?? [],
            $this->prioritiseMiddleware($middlewareList[$this->applicationName] ?? [])
        );
    }

    /**
     * @return string[][][]
     *
     * @throws InvalidArgumentException
     */
    private function extractRoutes(ContainerBuilder $container): array
    {
        $routes = [];

        foreach ($container->findTaggedServiceIds(Tags::HTTP_ROUTE) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (! isset($tag['route_name'], $tag['path'], $tag['behavior'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::MESSAGE_INVALID_ROUTE, $serviceId, Tags::HTTP_ROUTE)
                    );
                }

                if (isset($tag['methods'])) {
                    $tag['methods'] = explode(',', $tag['methods']);
                }

                $tag['app']     ??= $this->applicationName;
                $tag['async']     = (bool) ($tag['async'] ?? false);
                $tag['serviceId'] = $serviceId;

                $routes[$tag['app']] ??= [];
                $routes[$tag['app']][] = $tag;
            }
        }

        return $routes;
    }

    /**
     * @return mixed[]
     *
     * @throws InvalidArgumentException
     */
    private function extractMiddlewareList(ContainerBuilder $container): array
    {
        $list = [];

        foreach ($container->findTaggedServiceIds(Tags::HTTP_MIDDLEWARE) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $priority = $tag['priority'] ?? 0;
                $path     = $tag['path'] ?? '/';

                $tag['app'] ??= $this->applicationName;

                $list[$tag['app']][$priority][$path] ??= [];
                $list[$tag['app']][$priority][$path][] = $serviceId;
            }
        }

        $list[$this->applicationName][Priorities::CONTENT_NEGOTIATION]['/'] ??= [];
        $list[$this->applicationName][Priorities::BEFORE_CUSTOM]['/']       ??= [];
        $list[$this->applicationName][Priorities::AFTER_CUSTOM]['/']        ??= [];

        $list[$this->applicationName][Priorities::CONTENT_NEGOTIATION]['/'][] = $this->applicationName
                                                                              . '.http.middleware.content_negotiation';

        $list[$this->applicationName][Priorities::BEFORE_CUSTOM]['/'][] = $this->applicationName
                                                                        . '.http.middleware.route';
        $list[$this->applicationName][Priorities::BEFORE_CUSTOM]['/'][] = BodyParamsMiddleware::class;

        $list[$this->applicationName][Priorities::AFTER_CUSTOM]['/'][] = $this->applicationName
                                                                            . '.http.middleware.implicit_head';
        $list[$this->applicationName][Priorities::AFTER_CUSTOM]['/'][] = ImplicitOptionsMiddleware::class;
        $list[$this->applicationName][Priorities::AFTER_CUSTOM]['/'][] = MethodNotAllowedMiddleware::class;
        $list[$this->applicationName][Priorities::AFTER_CUSTOM]['/'][] = RouteParamsExtraction::class;
        $list[$this->applicationName][Priorities::AFTER_CUSTOM]['/'][] = DispatchMiddleware::class;
        $list[$this->applicationName][Priorities::AFTER_CUSTOM]['/'][] = MissingRouteDispatching::class;

        return $list;
    }

    /**
     * @param mixed[] $middlewareList
     *
     * @return string[][]
     */
    private function prioritiseMiddleware(array $middlewareList): array
    {
        krsort($middlewareList);

        $prioritised = [];

        foreach ($middlewareList as $list) {
            foreach ($list as $path => $references) {
                $prioritised[$path] ??= [];

                foreach ($references as $reference) {
                    $prioritised[$path][] = $reference;
                }
            }
        }

        return $prioritised;
    }

    /** @param string[] $services */
    private function registerServiceLocator(ContainerBuilder $container, array $services): Reference
    {
        return ServiceLocatorTagPass::register(
            $container,
            array_map(
                static function (string $id): Reference {
                    return new Reference($id);
                },
                array_combine($services, $services)
            )
        );
    }

    /** @param mixed[] $arguments */
    private function createService(string $class, array $arguments = []): Definition
    {
        return (new Definition($class, $arguments))->setPublic(false);
    }

    /**
     * @param string[][] $routes
     * @param string[][] $middlewareList
     */
    private function registerApplication(
        ContainerBuilder $container,
        array $routes,
        array $middlewareList
    ): void {
        $services = [];

        foreach ($routes as $route) {
            // @phpstan-ignore-next-line
            $services[] = $this->{self::BEHAVIORS[$route['behavior']]['callback']}(
                $this->applicationName . '.http.route.' . $route['route_name'],
                $route,
                $container
            );
        }

        $middleware = [];

        foreach ($middlewareList as $path => $servicesIds) {
            foreach ($servicesIds as $service) {
                $middleware[] = $service;
                $services[]   = $service;

                if ($path === '/') {
                    continue;
                }

                $decorator = $this->createService(
                    PathMiddlewareDecorator::class,
                    [$path, new Reference($service . '.decorator.inner')]
                );
                $decorator->setDecoratedService($service);

                $container->setDefinition($service . '.decorator', $decorator);
            }
        }

        $locator = $this->registerServiceLocator($container, $services);

        // -- middleware container

        $middlewareContainer = $this->createService(MiddlewareContainer::class, [$locator]);
        $container->setDefinition($this->applicationName . '.http.middleware_container', $middlewareContainer);

        // -- middleware factory

        $middlewareFactory = $this->createService(
            MiddlewareFactory::class,
            [new Reference($this->applicationName . '.http.middleware_container')]
        );

        $container->setDefinition($this->applicationName . '.http.middleware_factory', $middlewareFactory);

        // -- middleware pipeline

        $middlewarePipeline = $this->createService(MiddlewarePipe::class);

        foreach ($middleware as $service) {
            $middlewarePipeline->addMethodCall('pipe', [new Reference($service)]);
        }

        $container->setDefinition($this->applicationName . '.http.middleware_pipeline', $middlewarePipeline);

        // -- routing

        $appRouterConfig = $container->hasParameter($this->applicationName . '.router_config')
            ? '%' . $this->applicationName . '.router_config%'
            : [];

        $router = $this->createService(FastRouteRouter::class, [null, null, $appRouterConfig]);

        $container->setDefinition($this->applicationName . '.http.router', $router);

        $uriGenerator = $this->createService(
            UriGenerator::class,
            [new Reference($this->applicationName . '.http.router')]
        );

        $container->setDefinition($this->applicationName . '.http.uri_generator', $uriGenerator);

        $routeCollector = $this->createService(
            RouteCollector::class,
            [new Reference($this->applicationName . '.http.router')]
        );

        foreach ($routes as $route) {
            $routeCollector->addMethodCall(
                'route',
                [
                    $route['path'],
                    new Reference($this->applicationName . '.http.route.' . $route['route_name']),
                    $route['methods'] ?? self::BEHAVIORS[$route['behavior']]['methods'],
                    $route['route_name'],
                ]
            );
        }

        $container->setDefinition($this->applicationName . '.http.route_collector', $routeCollector);

        $routingMiddleware = $this->createService(
            RouteMiddleware::class,
            [new Reference($this->applicationName . '.http.router')]
        );

        $container->setDefinition($this->applicationName . '.http.middleware.route', $routingMiddleware);

        $implicitHeadMiddleware = $this->createService(
            ImplicitHeadMiddleware::class,
            [
                new Reference($this->applicationName . '.http.router'),
                [new Reference(StreamFactoryInterface::class), 'createStream'],
            ]
        );

        $container->setDefinition($this->applicationName . '.http.middleware.implicit_head', $implicitHeadMiddleware);

        // -- content negotiation

        $formatters = [];

        foreach ($container->findTaggedServiceIds(Tags::CONTENT_FORMATTER) as $serviceId => $tags) {
            assert(is_string($serviceId));

            foreach ($tags as $tag) {
                $formatters[$tag['format']] = new Reference($serviceId);
            }
        }

        if ($formatters === []) {
            $formatters['application/json']         = new Reference(Json::class);
            $formatters['application/problem+json'] = new Reference(Json::class);
        }

        $applicationAllowedFormats = $this->applicationName . '.allowed_formats';

        $negotiator = $this->createService(
            ContentTypeMiddleware::class,
            [
                $container->hasParameter($applicationAllowedFormats) ? '%' . $applicationAllowedFormats . '%'
                    : '%chimera.default_allowed_formats%',
                $formatters,
                new Reference(StreamFactoryInterface::class),
            ]
        );

        $negotiator->setFactory([ContentTypeMiddleware::class, 'fromRecommendedSettings']);

        $container->setDefinition($this->applicationName . '.http.middleware.content_negotiation', $negotiator);

        // --- request handler runner

        $requestHandlerRunner = $this->createService(
            RequestHandlerRunner::class,
            [
                new Reference($this->applicationName . '.http.middleware_pipeline'),
                new Reference(EmitterInterface::class),
                [ServerRequestFactory::class, 'fromGlobals'],
                new Reference(ServerRequestErrorResponseGenerator::class),
            ]
        );

        $container->setDefinition($this->applicationName . '.http.request_handler_runner', $requestHandlerRunner);

        $container->setDefinition(
            $this->applicationName . '.http_expressive',
            new Definition(
                Expressive::class,
                [
                    new Reference($this->applicationName . '.http.middleware_factory'),
                    new Reference($this->applicationName . '.http.middleware_pipeline'),
                    new Reference($this->applicationName . '.http.route_collector'),
                    new Reference($this->applicationName . '.http.request_handler_runner'),
                ]
            )
        );

        $app = new Definition(Application::class, [new Reference($this->applicationName . '.http_expressive')]);
        $app->setPublic(true);

        $container->setDefinition($this->applicationName . '.http', $app);
    }

    private function generateReadAction(string $name, string $query, ContainerBuilder $container): Reference
    {
        $action = $this->createService(
            ExecuteQuery::class,
            [
                new Reference($this->queryBusId),
                new Reference(MessageCreator::class),
                $query,
            ]
        );

        $container->setDefinition($name, $action);

        return new Reference($name);
    }

    private function generateWriteAction(string $name, string $command, ContainerBuilder $container): Reference
    {
        $action = $this->createService(
            ExecuteCommand::class,
            [
                new Reference($this->commandBusId),
                new Reference(MessageCreator::class),
                $command,
            ]
        );

        $container->setDefinition($name, $action);

        return new Reference($name);
    }

    private function wrapHandler(string $name, ContainerBuilder $container): string
    {
        $middleware = $this->createService(
            LazyLoadingMiddleware::class,
            [
                new Reference($this->applicationName . '.http.middleware_container'),
                $name . '.handler',
            ]
        );

        $container->setDefinition($name, $middleware);

        return $name . '.handler';
    }

    /** @param mixed[] $route */
    public function fetchOnly(string $routeServiceId, array $route, ContainerBuilder $container): string
    {
        $handler = $this->createService(
            FetchOnly::class,
            [
                $this->generateReadAction($routeServiceId . '.action', $route['query'], $container),
                new Reference(ResponseFactoryInterface::class),
            ]
        );

        $container->setDefinition($routeServiceId . '.handler', $handler);

        return $this->wrapHandler($routeServiceId, $container);
    }

    /** @param mixed[] $route */
    public function createOnly(string $routeServiceId, array $route, ContainerBuilder $container): string
    {
        $handler = $this->createService(
            CreateOnly::class,
            [
                $this->generateWriteAction($routeServiceId . '.action', $route['command'], $container),
                new Reference(ResponseFactoryInterface::class),
                $route['redirect_to'],
                new Reference($this->applicationName . '.http.uri_generator'),
                new Reference(IdentifierGenerator::class),
                $route['async'] === true ? StatusCode::STATUS_ACCEPTED : StatusCode::STATUS_CREATED,
            ]
        );

        $container->setDefinition($routeServiceId . '.handler', $handler);

        return $this->wrapHandler($routeServiceId, $container);
    }

    /** @param mixed[] $route */
    public function createAndFetch(string $routeServiceId, array $route, ContainerBuilder $container): string
    {
        $handler = $this->createService(
            CreateAndFetch::class,
            [
                $this->generateWriteAction($routeServiceId . '.write_action', $route['command'], $container),
                $this->generateReadAction($routeServiceId . '.read_action', $route['query'], $container),
                new Reference(ResponseFactoryInterface::class),
                $route['redirect_to'],
                new Reference($this->applicationName . '.http.uri_generator'),
                new Reference(IdentifierGenerator::class),
            ]
        );

        $container->setDefinition($routeServiceId . '.handler', $handler);

        return $this->wrapHandler($routeServiceId, $container);
    }

    /** @param mixed[] $route */
    public function executeOnly(string $routeServiceId, array $route, ContainerBuilder $container): string
    {
        $handler = $this->createService(
            ExecuteOnly::class,
            [
                $this->generateWriteAction($routeServiceId . '.action', $route['command'], $container),
                new Reference(ResponseFactoryInterface::class),
                $route['async'] === true ? StatusCode::STATUS_ACCEPTED : StatusCode::STATUS_NO_CONTENT,
            ]
        );

        $container->setDefinition($routeServiceId . '.handler', $handler);

        return $this->wrapHandler($routeServiceId, $container);
    }

    /** @param mixed[] $route */
    public function executeAndFetch(string $routeServiceId, array $route, ContainerBuilder $container): string
    {
        $handler = $this->createService(
            ExecuteAndFetch::class,
            [
                $this->generateWriteAction($routeServiceId . '.action', $route['command'], $container),
                $this->generateReadAction($routeServiceId . '.read_action', $route['query'], $container),
                new Reference(ResponseFactoryInterface::class),
            ]
        );

        $container->setDefinition($routeServiceId . '.handler', $handler);

        return $this->wrapHandler($routeServiceId, $container);
    }

    /** @param mixed[] $route */
    public function noBehavior(string $routeServiceId, array $route, ContainerBuilder $container): string
    {
        $container->setAlias($routeServiceId . '.handler', $route['serviceId']);

        return $this->wrapHandler($routeServiceId, $container);
    }
}
