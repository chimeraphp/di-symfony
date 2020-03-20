<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection;

interface Tags
{
    public const BUS_MIDDLEWARE      = 'chimera.bus_middleware';
    public const BUS_HANDLER         = 'chimera.bus_handler';
    public const BUS_COMMAND_HANDLER = 'chimera.command_handler';
    public const BUS_QUERY_HANDLER   = 'chimera.query_handler';

    public const HTTP_ERROR_HANDLING = 'chimera.http_error_handling';
    public const HTTP_MIDDLEWARE     = 'chimera.http_middleware';
    public const HTTP_ROUTE          = 'chimera.http_route';

    public const CONTENT_FORMATTER = 'chimera.content_negotiation';
}
