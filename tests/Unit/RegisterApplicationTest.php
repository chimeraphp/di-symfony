<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Unit;

use Chimera\DependencyInjection as Services;
use Chimera\DependencyInjection\RegisterApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

use function dirname;
use function iterator_to_array;

/**
 * @covers \Chimera\DependencyInjection\Mapping\Package
 * @covers \Chimera\DependencyInjection\MessageCreator\JmsSerializer\Package
 * @covers \Chimera\DependencyInjection\Routing\ErrorHandling\Package
 * @covers \Chimera\DependencyInjection\Routing\ErrorHandling\RegisterDefaultComponents
 * @covers \Chimera\DependencyInjection\Routing\Mezzio\Package
 * @covers \Chimera\DependencyInjection\Routing\Mezzio\RegisterServices
 * @covers \Chimera\DependencyInjection\ServiceBus\Tactician\Package
 * @covers \Chimera\DependencyInjection\ServiceBus\Tactician\RegisterServices
 * @covers \Chimera\DependencyInjection\ValidateApplicationComponents
 * @coversDefaultClass \Chimera\DependencyInjection\RegisterApplication
 */
final class RegisterApplicationTest extends TestCase
{
    /**
     * @test
     *
     * @covers ::__construct()
     * @covers ::getFiles()
     * @covers ::filterPackages()
     */
    public function getFilesShouldYieldFilesFromAllRelatedAndInstalledPackages(): void
    {
        $package = new RegisterApplication('testing');
        $files   = iterator_to_array($package->getFiles(), false);

        self::assertCount(6, $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/bus-tactician.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/foundation.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/routing.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/routing-mezzio.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/routing-error-handling.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/serialization-jms.xml', $files);
    }

    /**
     * @test
     *
     * @covers ::__construct()
     * @covers ::getCompilerPasses()
     * @covers ::filterPackages()
     */
    public function getCompilerPassesShouldYieldPassesFromAllRelatedAndInstalledPackages(): void
    {
        $package = new RegisterApplication('testing');
        $passes  = iterator_to_array($package->getCompilerPasses(), false);

        self::assertEquals(
            [
                [new Services\RegisterDefaultComponents(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -30],
                [new Services\ValidateApplicationComponents('testing'), PassConfig::TYPE_OPTIMIZE, -30],
                [new Services\Mapping\ExpandTags(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 50],
                [
                    new Services\ServiceBus\Tactician\RegisterServices('testing.command_bus', 'testing.query_bus'),
                    PassConfig::TYPE_BEFORE_OPTIMIZATION,
                ],
                [
                    new Services\Routing\Mezzio\RegisterServices(
                        'testing',
                        'testing.command_bus',
                        'testing.query_bus'
                    ),
                    PassConfig::TYPE_BEFORE_OPTIMIZATION,
                ],
                [new Services\Routing\ErrorHandling\RegisterDefaultComponents(), PassConfig::TYPE_BEFORE_OPTIMIZATION],
            ],
            $passes
        );
    }
}
