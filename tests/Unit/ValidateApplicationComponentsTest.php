<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Unit;

use Chimera\DependencyInjection\ValidateApplicationComponents;
use Chimera\Routing\Application;
use PHPUnit\Framework\Attributes as PHPUnit;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[PHPUnit\CoversClass(ValidateApplicationComponents::class)]
final class ValidateApplicationComponentsTest extends TestCase
{
    #[PHPUnit\Test]
    public function exceptionShouldBeRaisedWhenDefinitionIsNotPublic(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(Application::class, new Definition());
        $builder->setAlias('sample-app.http', Application::class)->setPublic(true);

        $pass = new ValidateApplicationComponents('sample-app');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The HTTP interface for "sample-app" is not a public service');
        $pass->process($builder);
    }

    #[PHPUnit\Test]
    public function exceptionShouldBeRaisedWhenLegacyAliasIsNotPublic(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(Application::class, (new Definition())->setPublic(true));
        $builder->setAlias('sample-app.http', Application::class);

        $pass = new ValidateApplicationComponents('sample-app');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The HTTP interface for "sample-app" is not a public service');
        $pass->process($builder);
    }

    #[PHPUnit\Test]
    public function noExceptionShouldBeRaisedWhenExpectedServicesArePublic(): void
    {
        $builder = new ContainerBuilder();
        $builder->setDefinition(Application::class, (new Definition())->setPublic(true));
        $builder->setAlias('sample-app.http', Application::class)->setPublic(true);

        $pass = new ValidateApplicationComponents('sample-app');
        $pass->process($builder);

        $this->addToAssertionCount(1);
    }
}
