<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional\App\Http;

use Chimera\Mapping\Routing\SimpleEndpoint;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** @SimpleEndpoint("/some-resource", name="simple-route") */
final class ARouteWithNoServiceBus implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $request->getAttribute('test');

        return new Response();
    }
}
