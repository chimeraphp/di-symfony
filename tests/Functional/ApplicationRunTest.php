<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional;

use Chimera\Routing\Application;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

use function assert;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * @covers \Chimera\DependencyInjection\Mapping\ExpandTags
 * @covers \Chimera\DependencyInjection\Mapping\Package
 * @covers \Chimera\DependencyInjection\MessageCreator\JmsSerializer\Package
 * @covers \Chimera\DependencyInjection\Routing\Expressive\Package
 * @covers \Chimera\DependencyInjection\Routing\Mezzio\Package
 * @covers \Chimera\DependencyInjection\Routing\Mezzio\RegisterServices
 * @covers \Chimera\DependencyInjection\ServiceBus\Tactician\Package
 * @covers \Chimera\DependencyInjection\ServiceBus\Tactician\RegisterServices
 * @covers \Chimera\DependencyInjection\RegisterApplication
 * @covers \Chimera\DependencyInjection\RegisterDefaultComponents
 * @covers \Chimera\DependencyInjection\ValidateApplicationComponents
 */
final class ApplicationRunTest extends ApplicationTestCase
{
    private const UUID_PATTERN = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5]{1}[0-9A-Fa-f]{3}-'
                               . '[ABab89]{1}[0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';

    private const ITEM_ENDPOINT = '/^\/things\/' . self::UUID_PATTERN . '$/';

    /**
     * @test
     * @dataProvider possibleRequests
     */
    public function correctResponseShouldBeProvided(ServerRequestInterface $request, callable $verifyResponse): void
    {
        $container = $this->createContainer();

        $app = $container->get('sample-app.http');
        assert($app instanceof Application);

        $verifyResponse($app->handle($request));
    }

    /** @return iterable<string, array{0: ServerRequestInterface, 1: callable}> */
    public function possibleRequests(): iterable
    {
        $factory       = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        yield 'create-thing' => [
            $factory->createServerRequest('POST', '/things')
                ->withBody($streamFactory->createStream('{"name": "Testing}')),
            static function (ResponseInterface $response): void {
                self::assertSame(201, $response->getStatusCode());
                self::assertMatchesRegularExpression(self::ITEM_ENDPOINT, $response->getHeaderLine('Location'));
                self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertSame('', (string) $response->getBody());
            },
        ];

        $id = Uuid::uuid4()->toString();

        yield 'fetch-thing' => [
            $factory->createServerRequest('GET', '/things/' . $id),
            static function (ResponseInterface $response) use ($id): void {
                self::assertSame(200, $response->getStatusCode());
                self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertSame(
                    [
                        'id' => $id,
                        'name' => 'a random name',
                    ],
                    json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR)
                );
            },
        ];

        yield 'list-things' => [
            $factory->createServerRequest('GET', '/things'),
            static function (ResponseInterface $response): void {
                self::assertSame(200, $response->getStatusCode());
                self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));

                $items = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
                $names = ['one', 'two', 'three'];

                self::assertCount(3, $items);

                foreach ($items as $i => $item) {
                    self::assertCount(2, $item);
                    self::assertArrayHasKey('id', $item);
                    self::assertArrayHasKey('name', $item);
                    self::assertSame($names[$i], $item['name']);
                }
            },
        ];

        yield 'delete-thing' => [
            $factory->createServerRequest('DELETE', '/things/' . $id),
            static function (ResponseInterface $response): void {
                self::assertSame(204, $response->getStatusCode());
                self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertSame('', (string) $response->getBody());
            },
        ];

        yield 'some-resource' => [
            $factory->createServerRequest('GET', '/some-resource'),
            static function (ResponseInterface $response): void {
                self::assertSame(200, $response->getStatusCode());
                self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertSame('', (string) $response->getBody());
            },
        ];
    }
}
