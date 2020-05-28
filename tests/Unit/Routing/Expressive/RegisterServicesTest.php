<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Unit\Routing\Expressive;

use Chimera\DependencyInjection\Routing\Expressive\RegisterServices;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

final class RegisterServicesTest extends TestCase
{
    /**
     * @test
     */
    public function registeringServicesDoesNotAllowMultipleApplications(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $container = new ContainerBuilder();

        $this->createRegisterServices('testing1')->process($container);
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
