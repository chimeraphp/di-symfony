<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App;

use Chimera\DependencyInjection\RegisterApplication;
use Lcobucci\DependencyInjection\ContainerBuilder;

use function dirname;

$builder = ContainerBuilder::default(__FILE__, __NAMESPACE__);
$root    = dirname(__DIR__);

return $builder->setDumpDir($root . '/dump')
    ->setParameter('app.basedir', $root . '/')
    ->addFile(__DIR__ . '/container.xml')
    ->addPackage(RegisterApplication::class, ['sample-app']);
