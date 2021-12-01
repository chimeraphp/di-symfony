<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional;

use Chimera\DependencyInjection\Tests\Functional\App\CreateThing;
use Chimera\DependencyInjection\Tests\Functional\App\CreateThingHandler;
use Chimera\DependencyInjection\Tests\Functional\App\FetchThing;
use Chimera\DependencyInjection\Tests\Functional\App\FetchThingHandler;
use Chimera\DependencyInjection\Tests\Functional\App\Http\AnotherSampleMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\Http\SampleMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\ListThings;
use Chimera\DependencyInjection\Tests\Functional\App\ListThingsHandler;
use Chimera\DependencyInjection\Tests\Functional\App\RemoveThing;
use Chimera\DependencyInjection\Tests\Functional\App\RemoveThingHandler;
use Chimera\DependencyInjection\Tests\Functional\App\ServiceBus\CommandAndQueryMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\ServiceBus\CommandOnlyMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\ServiceBus\QueryOnlyMiddleware;
use Chimera\Routing\Application;
use Chimera\Routing\MissingRouteDispatching;
use Chimera\Routing\RouteParamsExtraction;
use Chimera\ServiceBus;
use Chimera\ServiceBus\Tactician\ReadModelConversionMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Lcobucci\ErrorHandling\ErrorConversionMiddleware;
use League\Tactician\CommandBus;
use League\Tactician\Handler\Locator\HandlerLocator;
use League\Tactician\Middleware;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use Mezzio\Router\Route;
use Mezzio\Router\RouteCollector;
use Psr\Http\Server\MiddlewareInterface;
use ReflectionProperty;
use SplQueue;

use function array_filter;
use function array_values;
use function assert;
use function iterator_to_array;

/**
 * @covers \Chimera\DependencyInjection\Mapping\ExpandTags
 * @covers \Chimera\DependencyInjection\Mapping\Package
 * @covers \Chimera\DependencyInjection\MessageCreator\JmsSerializer\Package
 * @covers \Chimera\DependencyInjection\Routing\ErrorHandling\Package
 * @covers \Chimera\DependencyInjection\Routing\ErrorHandling\RegisterDefaultComponents
 * @covers \Chimera\DependencyInjection\Routing\Mezzio\Package
 * @covers \Chimera\DependencyInjection\Routing\Mezzio\RegisterServices
 * @covers \Chimera\DependencyInjection\ServiceBus\Tactician\Package
 * @covers \Chimera\DependencyInjection\ServiceBus\Tactician\RegisterServices
 * @covers \Chimera\DependencyInjection\RegisterApplication
 * @covers \Chimera\DependencyInjection\RegisterDefaultComponents
 * @covers \Chimera\DependencyInjection\ValidateApplicationComponents
 */
final class ApplicationRegistrationTest extends ApplicationTestCase
{
    /** @test */
    public function applicationMustBeCorrectlyRegistered(): void
    {
        $container = $this->createContainer();

        self::assertTrue($container->has('sample-app.http'));
        self::assertInstanceOf(Application::class, $container->get('sample-app.http'));
    }

    /** @test */
    public function routesMustBeProperlyDefined(): void
    {
        $container = $this->createContainer();

        $collector = $container->get('sample-app.http.route_collector');
        assert($collector instanceof RouteCollector);

        $routes = $collector->getRoutes();

        self::assertCount(5, $routes);
        self::assertRouteRegistered($routes, '/things', ['GET'], 'things.list');
        self::assertRouteRegistered($routes, '/things', ['POST'], 'things.create');
        self::assertRouteRegistered($routes, '/things/{id}', ['GET'], 'things.fetch');
        self::assertRouteRegistered($routes, '/things/{id}', ['DELETE'], 'things.remove');
        self::assertRouteRegistered($routes, '/some-resource', ['GET'], 'simple-route');
    }

    /**
     * @param list<Route>  $routes
     * @param list<string> $methods
     */
    private static function assertRouteRegistered(
        array $routes,
        string $path,
        array $methods,
        string $expectedName,
    ): void {
        $match = array_values(
            array_filter(
                $routes,
                static fn (Route $route): bool => $route->getPath() === $path
                    && $route->getAllowedMethods() === $methods,
            ),
        );

        self::assertCount(1, $match);
        self::assertSame($expectedName, $match[0]->getName());
    }

    /** @test */
    public function httpMiddlewaresMustBeProperlyDefined(): void
    {
        $container = $this->createContainer();

        $expectedMiddleware = [
            $container->get('sample-app.http.middleware.content_negotiation'),
            $container->get(ErrorConversionMiddleware::class),
            $container->get('sample-app.http.middleware.route'),
            $container->get(BodyParamsMiddleware::class),
            $container->get(AnotherSampleMiddleware::class),
            $container->get(SampleMiddleware::class),
            $container->get('sample-app.http.middleware.implicit_head'),
            $container->get(ImplicitOptionsMiddleware::class),
            $container->get(MethodNotAllowedMiddleware::class),
            $container->get(RouteParamsExtraction::class),
            $container->get(DispatchMiddleware::class),
            $container->get(MissingRouteDispatching::class),
        ];

        $expectedPipe = new MiddlewarePipe();

        foreach ($expectedMiddleware as $middleware) {
            assert($middleware instanceof MiddlewareInterface);

            $expectedPipe->pipe($middleware);
        }

        $property = new ReflectionProperty(MiddlewarePipe::class, 'pipeline');
        $property->setAccessible(true);

        $result = $container->get('sample-app.http.middleware_pipeline');
        assert($result instanceof MiddlewarePipe);

        $expectedPipeline  = $property->getValue($expectedPipe);
        $resultingPipeline = $property->getValue($result);

        self::assertInstanceOf(SplQueue::class, $expectedPipeline);
        self::assertInstanceOf(SplQueue::class, $resultingPipeline);
        self::assertSame(iterator_to_array($expectedPipeline), iterator_to_array($resultingPipeline));
    }

    /** @test */
    public function serviceBusesMustExist(): void
    {
        $container = $this->createContainer();

        self::assertInstanceOf(ServiceBus::class, $container->get('sample-app.command_bus'));
        self::assertInstanceOf(ServiceBus::class, $container->get('sample-app.query_bus'));
    }

    /** @test */
    public function commandBusHandlersMustBeProperlyDefined(): void
    {
        $container = $this->createContainer();

        $locator = $container->get('sample-app.command_bus.decorated_bus.handler.locator');
        assert($locator instanceof HandlerLocator);

        self::assertInstanceOf(CreateThingHandler::class, $locator->getHandlerForCommand(CreateThing::class));
        self::assertInstanceOf(RemoveThingHandler::class, $locator->getHandlerForCommand(RemoveThing::class));
    }

    /** @test */
    public function commandBusMiddlewareMustBeProperlyDefined(): void
    {
        $container = $this->createContainer();

        $bus = $container->get('sample-app.command_bus.decorated_bus');
        assert($bus instanceof CommandBus);

        $middlewareList = [
            $container->get(CommandAndQueryMiddleware::class),
            $container->get(CommandOnlyMiddleware::class),
            $container->get('sample-app.command_bus.decorated_bus.handler'),
        ];

        self::assertContainsOnlyInstancesOf(Middleware::class, $middlewareList);
        self::assertEquals(new CommandBus($middlewareList), $bus); // @phpstan-ignore-line
    }

    /** @test */
    public function queryBusHandlersMustBeProperlyDefined(): void
    {
        $container = $this->createContainer();

        $locator = $container->get('sample-app.query_bus.decorated_bus.handler.locator');
        assert($locator instanceof HandlerLocator);

        self::assertInstanceOf(FetchThingHandler::class, $locator->getHandlerForCommand(FetchThing::class));
        self::assertInstanceOf(ListThingsHandler::class, $locator->getHandlerForCommand(ListThings::class));
    }

    /** @test */
    public function queryBusMiddlewareMustBeProperlyDefined(): void
    {
        $container = $this->createContainer();

        $bus = $container->get('sample-app.query_bus.decorated_bus');
        assert($bus instanceof CommandBus);

        $middlewareList = [
            $container->get(CommandAndQueryMiddleware::class),
            $container->get(QueryOnlyMiddleware::class),
            $container->get(ReadModelConversionMiddleware::class),
            $container->get('sample-app.query_bus.decorated_bus.handler'),
        ];

        self::assertContainsOnlyInstancesOf(Middleware::class, $middlewareList);
        self::assertEquals(new CommandBus($middlewareList), $bus); // @phpstan-ignore-line
    }
}
