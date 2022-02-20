<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional;

use Chimera\DependencyInjection\Tests\Functional\App\Http\AnotherSampleMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\Http\SampleMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\ServiceBus\CommandAndQueryMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\ServiceBus\CommandOnlyMiddleware;
use Chimera\DependencyInjection\Tests\Functional\App\ServiceBus\QueryOnlyMiddleware;
use Chimera\Routing\MissingRouteDispatching;
use Chimera\Routing\RouteParamsExtraction;
use Chimera\ServiceBus\Tactician\ReadModelConversionMiddleware;
use Lcobucci\DependencyInjection\ContainerBuilder;
use Lcobucci\ErrorHandling\ErrorConversionMiddleware;
use Mezzio\Helper\BodyParams\BodyParamsMiddleware;
use Mezzio\Router\Middleware\DispatchMiddleware;
use Mezzio\Router\Middleware\ImplicitOptionsMiddleware;
use Mezzio\Router\Middleware\MethodNotAllowedMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

use function assert;
use function exec;

abstract class ApplicationTestCase extends TestCase
{
    private const SERVICES_TO_MAKE_PUBLIC = [
        'sample-app.command_bus',
        'sample-app.command_bus.decorated_bus',
        'sample-app.command_bus.decorated_bus.handler',
        'sample-app.query_bus',
        'sample-app.query_bus.decorated_bus',
        'sample-app.query_bus.decorated_bus.handler',
        BodyParamsMiddleware::class,
        ImplicitOptionsMiddleware::class,
        MethodNotAllowedMiddleware::class,
        RouteParamsExtraction::class,
        DispatchMiddleware::class,
        MissingRouteDispatching::class,
        SampleMiddleware::class,
        AnotherSampleMiddleware::class,
        ReadModelConversionMiddleware::class,
        CommandOnlyMiddleware::class,
        QueryOnlyMiddleware::class,
        CommandAndQueryMiddleware::class,
        ErrorConversionMiddleware::class,
    ];

    private const ALIASES_TO_MAKE_PUBLIC = [
        'sample-app.http.route_collector',
        'sample-app.http.middleware_pipeline',
        'sample-app.http.middleware.content_negotiation',
        'sample-app.http.middleware.route',
        'sample-app.http.middleware.implicit_head',
        'sample-app.command_bus.decorated_bus.handler.locator',
        'sample-app.query_bus.decorated_bus.handler.locator',
    ];

    /**
     * @beforeClass
     * @afterClass
     */
    final public static function cleanUpContainer(): void
    {
        exec('rm -rf ' . __DIR__ . '/App/dump');
    }

    final protected function createContainer(): ContainerInterface
    {
        $builder = require __DIR__ . '/App/config/container_builder.php';
        assert($builder instanceof ContainerBuilder);

        $builder->addPass(
            $this->makeServicesPublic(),
            PassConfig::TYPE_BEFORE_REMOVING,
        );

        return $builder->getContainer();
    }

    private function makeServicesPublic(): CompilerPassInterface
    {
        return new class (self::SERVICES_TO_MAKE_PUBLIC, self::ALIASES_TO_MAKE_PUBLIC) implements CompilerPassInterface
        {
            /**
             * @param list<string> $services
             * @param list<string> $aliases
             */
            public function __construct(private array $services, private array $aliases)
            {
            }

            public function process(\Symfony\Component\DependencyInjection\ContainerBuilder $container): void
            {
                foreach ($this->services as $service) {
                    $container->getDefinition($service)->setPublic(true);
                }

                foreach ($this->aliases as $service) {
                    $container->getAlias($service)->setPublic(true);
                }
            }
        };
    }
}
