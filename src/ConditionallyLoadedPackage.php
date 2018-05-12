<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection;

interface ConditionallyLoadedPackage
{
    public function shouldBeLoaded(): bool;
}
