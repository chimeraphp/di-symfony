<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Tests\Functional;

use Chimera\DependencyInjection as Services;
use Chimera\Routing\Application;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes as PHPUnit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;

use function assert;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[PHPUnit\CoversClass(Services\Mapping\ExpandTags::class)]
#[PHPUnit\CoversClass(Services\Mapping\Package::class)]
#[PHPUnit\CoversClass(Services\MessageCreator\JmsSerializer\Package::class)]
#[PHPUnit\CoversClass(Services\Routing\ErrorHandling\Package::class)]
#[PHPUnit\CoversClass(Services\Routing\ErrorHandling\RegisterDefaultComponents::class)]
#[PHPUnit\CoversClass(Services\Routing\Mezzio\Package::class)]
#[PHPUnit\CoversClass(Services\Routing\Mezzio\RegisterServices::class)]
#[PHPUnit\CoversClass(Services\ServiceBus\Tactician\Package::class)]
#[PHPUnit\CoversClass(Services\ServiceBus\Tactician\RegisterServices::class)]
#[PHPUnit\CoversClass(Services\RegisterApplication::class)]
#[PHPUnit\CoversClass(Services\RegisterDefaultComponents::class)]
#[PHPUnit\CoversClass(Services\ValidateApplicationComponents::class)]
final class ApplicationRunTest extends ApplicationTestCase
{
    private const UUID_PATTERN = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5]{1}[0-9A-Fa-f]{3}-'
                               . '[ABab89]{1}[0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';

    private const ITEM_ENDPOINT = '/^\/things\/' . self::UUID_PATTERN . '$/';

    #[PHPUnit\Test]
    #[PHPUnit\DataProvider('possibleRequests')]
    public function correctResponseShouldBeProvided(ServerRequestInterface $request, callable $verifyResponse): void
    {
        $container = $this->createContainer();

        $app = $container->get('sample-app.http');
        assert($app instanceof Application);

        $verifyResponse($app->handle($request));
    }

    /** @return iterable<string, array{0: ServerRequestInterface, 1: callable}> */
    public static function possibleRequests(): iterable
    {
        $factory       = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        yield 'create-thing' => [
            $factory->createServerRequest('POST', '/things')
                ->withBody($streamFactory->createStream('{"name": "John"}'))
                ->withAddedHeader('Content-Type', 'application/json'),
            static function (ResponseInterface $response): void {
                self::assertSame(201, $response->getStatusCode());
                self::assertMatchesRegularExpression(self::ITEM_ENDPOINT, $response->getHeaderLine('Location'));
                self::assertSame('application/json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertSame('', (string) $response->getBody());
            },
        ];

        yield 'create-thing-with-invalid-name' => [
            $factory->createServerRequest('POST', '/things')
                ->withBody($streamFactory->createStream('{"name": "Testing"}'))
                ->withAddedHeader('Content-Type', 'application/json'),
            static function (ResponseInterface $response): void {
                self::assertSame(422, $response->getStatusCode());
                self::assertSame('application/problem+json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertJsonStringEqualsJsonString(
                    json_encode(
                        [
                            'type' => 'https://httpstatuses.com/422',
                            'title' => 'Name not allowed',
                            'details' => '"Testing" is a forbidden name in this application',
                        ],
                        JSON_THROW_ON_ERROR,
                    ),
                    (string) $response->getBody(),
                );
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
                    json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
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

                self::assertIsArray($items);
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

        yield 'invalid-endpoint' => [
            $factory->createServerRequest('GET', '/something-that-does-not-exist'),
            static function (ResponseInterface $response): void {
                self::assertSame(404, $response->getStatusCode());
                self::assertSame('application/problem+json; charset=UTF-8', $response->getHeaderLine('Content-Type'));
                self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
                self::assertJsonStringEqualsJsonString(
                    json_encode(
                        [
                            'type' => 'https://httpstatuses.com/404',
                            'title' => 'Not Found',
                            'details' => 'Cannot GET /something-that-does-not-exist',
                        ],
                        JSON_THROW_ON_ERROR,
                    ),
                    (string) $response->getBody(),
                );
            },
        ];
    }
}
