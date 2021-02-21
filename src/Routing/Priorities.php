<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Routing;

interface Priorities
{
    public const CONTENT_NEGOTIATION = 110;
    public const BEFORE_CUSTOM       = 100;
    public const AFTER_CUSTOM        = -100;
}
