<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Unit\Routing\Expressive;

use Chimera\DependencyInjection\Routing\Expressive\RegisterServices;
use Chimera\DependencyInjection\Tags;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/** @coversDefaultClass \Chimera\DependencyInjection\Routing\Expressive\RegisterServices */
final class RegisterServicesTest extends TestCase
{
    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::process
     * @covers ::extractRoutes
     */
    public function exceptionShouldBeRaisedWhenTryingToRegisterDuplicatedRoutes(): void
    {
        $service1 = new Definition();
        $service1->addTag(Tags::HTTP_ROUTE, ['route_name' => 'test', 'path' => '/one', 'behavior' => 'fetch']);

        $service2 = new Definition();
        $service2->addTag(Tags::HTTP_ROUTE, ['route_name' => 'test', 'path' => '/two', 'behavior' => 'fetch']);

        $builder = new ContainerBuilder();
        $builder->addDefinitions(['service1' => $service1, 'service2' => $service2]);

        $registerServices = new RegisterServices('app', 'app.command-bus', 'app.query-bus');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The service "service2" is trying to declare a route with name "test" '
            . 'which has already been defined by the service "service1".'
        );
        $registerServices->process($builder);
    }

    /**
     * @test
     *
     * @covers ::__construct
     * @covers ::process
     * @covers ::extractRoutes
     */
    public function registeringServicesDoesNotAllowMultipleApplications(): void
    {
        $container = new ContainerBuilder();

        $this->createRegisterServices('testing1')->process($container);

        $this->expectException(InvalidArgumentException::class);
        $this->createRegisterServices('testing2')->process($container);
    }

    private function createRegisterServices(string $applicationName): RegisterServices
    {
        return new RegisterServices(
            $applicationName,
            $applicationName . '.command_bus',
            $applicationName . '.query_bus'
        );
    }
}
