<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Unit;

use Chimera\DependencyInjection as Services;
use Chimera\DependencyInjection\RegisterApplication;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

use function dirname;
use function iterator_to_array;

/** @coversDefaultClass \Chimera\DependencyInjection\RegisterApplication */
final class RegisterApplicationTest extends TestCase
{
    /**
     * @test
     *
     * @covers ::__construct()
     * @covers ::getFiles()
     * @covers ::filterPackages()
     *
     * @uses \Chimera\DependencyInjection\MessageCreator\JmsSerializer\Package
     * @uses \Chimera\DependencyInjection\Routing\Expressive\Package
     * @uses \Chimera\DependencyInjection\Routing\Mezzio\Package
     * @uses \Chimera\DependencyInjection\ServiceBus\Tactician\Package
     */
    public function getFilesShouldYieldFilesFromAllRelatedAndInstalledPackages(): void
    {
        $package = new RegisterApplication('testing');
        $files   = iterator_to_array($package->getFiles(), false);

        self::assertCount(5, $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/bus-tactician.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/foundation.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/routing.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/routing-mezzio.xml', $files);
        self::assertContains(dirname(__DIR__, 2) . '/config/serialization-jms.xml', $files);
    }

    /**
     * @test
     *
     * @covers ::__construct()
     * @covers ::getCompilerPasses()
     * @covers ::filterPackages()
     *
     * @uses \Chimera\DependencyInjection\Mapping\Package
     * @uses \Chimera\DependencyInjection\MessageCreator\JmsSerializer\Package
     * @uses \Chimera\DependencyInjection\Routing\Expressive\Package
     * @uses \Chimera\DependencyInjection\Routing\Expressive\RegisterServices
     * @uses \Chimera\DependencyInjection\Routing\Mezzio\Package
     * @uses \Chimera\DependencyInjection\Routing\Mezzio\RegisterServices
     * @uses \Chimera\DependencyInjection\ServiceBus\Tactician\Package
     * @uses \Chimera\DependencyInjection\ServiceBus\Tactician\RegisterServices
     * @uses \Chimera\DependencyInjection\ValidateApplicationComponents
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
            ],
            $passes
        );
    }
}
