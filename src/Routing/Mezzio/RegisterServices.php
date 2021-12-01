<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Routing\Mezzio;

use Chimera\DependencyInjection\Routing\Priorities;
use Chimera\DependencyInjection\Tags;
use Chimera\ExecuteCommand;
use Chimera\ExecuteQuery;
use Chimera\IdentifierGenerator;
use Chimera\MessageCreator;
use Chimera\Routing\Application as ApplicationInterface;
use Chimera\Routing\Handler\CreateAndFetch;
use Chimera\Routing\Handler\CreateOnly;
use Chimera\Routing\Handler\ExecuteAndFetch;
use Chimera\Routing\Handler\ExecuteOnly;
use Chimera\Routing\Handler\FetchOnly;
use Chimera\Routing\Mezzio\Application;
use Chimera\Routing\MissingRouteDispatching;
use Chimera\Routing\RouteParamsExtraction;
use Chimera\Routing\UriGenerator as UriGeneratorInterface;
use Fig\Http\Message\StatusCodeInterface as StatusCode;
use InvalidArgumentException;
use Laminas\HttpHandlerRunner\RequestHandlerRunner;
use Laminas\Stratigility\Middleware\PathMiddlewareDecorator;
use Laminas\Stratigility\MiddlewarePipe;
use Lcobucci\ContentNegotiation\ContentTypeMiddleware;
use Lcobucci\ContentNegotiation\Formatter\Json;
use Mezzio\Application as Mezzio;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\Middleware\LazyLoadingMiddleware;
use Mezzio\MiddlewareContainer;
use Mezzio\MiddlewareFactory;
use Mezzio\Router\FastRouteRouter;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitHeadMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Middleware\RouteMiddleware;
use Mezzio\Router\RouteCollector;
use Mezzio\Router\RouterInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_combine;
use function array_key_exists;
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

    private const MESSAGE_DUPLICATED_ROUTE = 'The service "%s" is trying to declare a route with name "%s" which has '
                                           . 'already been defined by the service "%s".';

    private const BEHAVIORS = [
        'fetch'         => ['methods' => ['GET'], 'callback' => 'fetchOnly'],
        'create'        => ['methods' => ['POST'], 'callback' => 'createOnly'],
        'create_fetch'  => ['methods' => ['POST'], 'callback' => 'createAndFetch'],
        'execute'       => ['methods' => ['PATCH', 'PUT', 'DELETE'], 'callback' => 'executeOnly'],
        'execute_fetch' => ['methods' => ['PATCH', 'PUT'], 'callback' => 'executeAndFetch'],
        'none'          => ['methods' => ['GET'], 'callback' => 'noBehavior'],
    ];

    public function __construct(
        private string $applicationName,
        private string $commandBusId,
        private string $queryBusId,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $routes         = $this->extractRoutes($container);
        $middlewareList = $this->extractMiddlewareList($container);

        $this->registerApplication(
            $container,
            $routes ?? [],
            $this->prioritiseMiddleware($middlewareList ?? []),
        );
    }

    /**
     * @return string[][]
     *
     * @throws InvalidArgumentException
     */
    private function extractRoutes(ContainerBuilder $container): array
    {
        $routes = [];
        $names  = [];

        foreach ($container->findTaggedServiceIds(Tags::HTTP_ROUTE) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                if (! isset($tag['route_name'], $tag['path'], $tag['behavior'])) {
                    throw new InvalidArgumentException(
                        sprintf(self::MESSAGE_INVALID_ROUTE, $serviceId, Tags::HTTP_ROUTE),
                    );
                }

                assert(is_string($tag['route_name']));

                if (array_key_exists($tag['route_name'], $names)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            self::MESSAGE_DUPLICATED_ROUTE,
                            $serviceId,
                            $tag['route_name'],
                            $names[$tag['route_name']],
                        ),
                    );
                }

                if (isset($tag['methods'])) {
                    $tag['methods'] = explode(',', $tag['methods']);
                }

                $tag['async']     = (bool) ($tag['async'] ?? false);
                $tag['serviceId'] = $serviceId;

                $routes[] = $tag;

                $names[$tag['route_name']] = $serviceId;
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

                $list[$priority][$path] ??= [];
                $list[$priority][$path][] = $serviceId;
            }
        }

        $list[Priorities::CONTENT_NEGOTIATION]['/'] ??= [];
        $list[Priorities::BEFORE_CUSTOM]['/']       ??= [];
        $list[Priorities::AFTER_CUSTOM]['/']        ??= [];

        $list[Priorities::CONTENT_NEGOTIATION]['/'][] = ContentTypeMiddleware::class;

        $list[Priorities::BEFORE_CUSTOM]['/'][] = RouteMiddleware::class;
        $list[Priorities::BEFORE_CUSTOM]['/'][] = BodyParamsMiddleware::class;

        $list[Priorities::AFTER_CUSTOM]['/'][] = ImplicitHeadMiddleware::class;
        $list[Priorities::AFTER_CUSTOM]['/'][] = ImplicitOptionsMiddleware::class;
        $list[Priorities::AFTER_CUSTOM]['/'][] = MethodNotAllowedMiddleware::class;
        $list[Priorities::AFTER_CUSTOM]['/'][] = RouteParamsExtraction::class;
        $list[Priorities::AFTER_CUSTOM]['/'][] = DispatchMiddleware::class;
        $list[Priorities::AFTER_CUSTOM]['/'][] = MissingRouteDispatching::class;

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
                array_combine($services, $services),
            ),
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
        array $middlewareList,
    ): void {
        if ($container->hasDefinition(ApplicationInterface::class)) {
            throw new InvalidArgumentException('Registering multiple applications is deprecated.');
        }

        $services = [];
        $aliases  = []; // for BC

        foreach ($routes as $route) {
            // @phpstan-ignore-next-line
            $services[] = $this->{self::BEHAVIORS[$route['behavior']]['callback']}(
                'http.route.' . $route['route_name'],
                $route,
                $container
            );

            $aliases['.http.route.' . $route['route_name']] = 'http.route.' . $route['route_name'];
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
                    [$path, new Reference($service . '.decorator.inner')],
                );
                $decorator->setDecoratedService($service);

                $container->setDefinition($service . '.decorator', $decorator);
            }
        }

        $locator = $this->registerServiceLocator($container, $services);

        // -- middleware container

        $middlewareContainer = $this->createService(MiddlewareContainer::class, [$locator]);
        $container->setDefinition(MiddlewareContainer::class, $middlewareContainer);
        $aliases['.http.middleware_container'] = MiddlewareContainer::class;

        // -- middleware factory

        $aliases['.http.middleware_factory'] = MiddlewareFactory::class;

        // -- middleware pipeline

        $middlewarePipeline = $this->createService(MiddlewarePipe::class);

        foreach ($middleware as $service) {
            $middlewarePipeline->addMethodCall('pipe', [new Reference($service)]);
        }

        $container->setDefinition(MiddlewarePipe::class, $middlewarePipeline);
        $aliases['.http.middleware_pipeline'] = MiddlewarePipe::class;

        // -- routing

        $router = $this->createService(
            FastRouteRouter::class,
            [
                null,
                null,
                $this->readBCParameter($container, $this->applicationName . '.router_config', 'router_config', []),
            ],
        );

        $container->setDefinition(FastRouteRouter::class, $router);
        $container->setAlias(RouterInterface::class, FastRouteRouter::class);
        $aliases['.http.router']        = FastRouteRouter::class;
        $aliases['.http.uri_generator'] = UriGeneratorInterface::class;

        $routeCollector = $this->createService(
            RouteCollector::class,
            [new Reference(FastRouteRouter::class)],
        );

        foreach ($routes as $route) {
            $routeCollector->addMethodCall(
                'route',
                [
                    $route['path'],
                    new Reference('http.route.' . $route['route_name']),
                    $route['methods'] ?? self::BEHAVIORS[$route['behavior']]['methods'],
                    $route['route_name'],
                ],
            );
        }

        $container->setDefinition(RouteCollector::class, $routeCollector);
        $aliases['.http.route_collector']          = RouteCollector::class;
        $aliases['.http.middleware.route']         = RouteMiddleware::class;
        $aliases['.http.middleware.implicit_head'] = ImplicitHeadMiddleware::class;

        // -- content negotiation

        $formatters = [];

        foreach ($container->findTaggedServiceIds(Tags::CONTENT_FORMATTER) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $formatters[$tag['format']] = new Reference($serviceId);
            }
        }

        if ($formatters === []) {
            $formatters['application/json']         = new Reference(Json::class);
            $formatters['application/problem+json'] = new Reference(Json::class);
        }

        $negotiator = $this->createService(
            ContentTypeMiddleware::class,
            [
                $this->readBCParameter(
                    $container,
                    $this->applicationName . '.allowed_formats',
                    'allowed_formats',
                    '%chimera.default_allowed_formats%',
                ),
                $formatters,
                new Reference(StreamFactoryInterface::class),
            ],
        );

        $negotiator->setFactory([ContentTypeMiddleware::class, 'fromRecommendedSettings']);

        $container->setDefinition(ContentTypeMiddleware::class, $negotiator);
        $aliases['.http.middleware.content_negotiation'] = ContentTypeMiddleware::class;
        $aliases['.http.request_handler_runner']         = RequestHandlerRunner::class;

        $app = new Definition(Application::class, [new Reference(Mezzio::class)]);
        $app->setPublic(true);

        $container->setDefinition(ApplicationInterface::class, $app);
        $aliases['.http'] = ApplicationInterface::class;

        foreach ($aliases as $alias => $service) {
            $container->setAlias($this->applicationName . $alias, $service)->setDeprecated('chimera/di-symfony', '0.5.0', null);
        }

        $container->getAlias($this->applicationName . '.http')
            ->setDeprecated('chimera/di-symfony', '0.5.0', null)
            ->setPublic(true);
    }

    private function generateReadAction(string $name, string $query, ContainerBuilder $container): Reference
    {
        $action = $this->createService(
            ExecuteQuery::class,
            [
                new Reference($this->queryBusId),
                new Reference(MessageCreator::class),
                $query,
            ],
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
            ],
        );

        $container->setDefinition($name, $action);

        return new Reference($name);
    }

    private function wrapHandler(string $name, ContainerBuilder $container): string
    {
        $middleware = $this->createService(
            LazyLoadingMiddleware::class,
            [
                new Reference(MiddlewareContainer::class),
                $name . '.handler',
            ],
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
            ],
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
                new Reference(UriGeneratorInterface::class),
                new Reference(IdentifierGenerator::class),
                $route['async'] === true ? StatusCode::STATUS_ACCEPTED : StatusCode::STATUS_CREATED,
            ],
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
                new Reference(UriGeneratorInterface::class),
                new Reference(IdentifierGenerator::class),
            ],
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
            ],
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
            ],
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

    /**
     * @param string|mixed[] $default
     *
     * @return mixed[]|string
     */
    private function readBCParameter(
        ContainerBuilder $container,
        string $legacyName,
        string $parameterName,
        string|array $default,
    ): array|string {
        if ($container->hasParameter($legacyName)) {
            return '%' . $legacyName . '%';
        }

        if ($container->hasParameter($parameterName)) {
            return '%' . $parameterName . '%';
        }

        return $default;
    }
}
