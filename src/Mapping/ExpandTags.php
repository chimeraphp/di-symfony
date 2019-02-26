<?php
declare(strict_types=1);

namespace Chimera\DependencyInjection\Mapping;

use Chimera\DependencyInjection\Tags;
use Chimera\Mapping;
use Doctrine\Common\Annotations\AnnotationException;
use Generator;
use ReflectionClass;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use function assert;
use function get_class;
use function implode;
use function is_array;

final class ExpandTags implements CompilerPassInterface
{
    private const ROUTE_BEHAVIOR = [
        Mapping\Routing\CreateEndpoint::class          => 'create',
        Mapping\Routing\CreateAndFetchEndpoint::class  => 'create_fetch',
        Mapping\Routing\ExecuteEndpoint::class         => 'execute',
        Mapping\Routing\ExecuteAndFetchEndpoint::class => 'execute_fetch',
        Mapping\Routing\FetchEndpoint::class           => 'fetch',
    ];

    private const SERVICE_TAGS = [
        Mapping\ServiceBus\CommandHandler::class       => Tags::BUS_COMMAND_HANDLER,
        Mapping\ServiceBus\QueryHandler::class         => Tags::BUS_QUERY_HANDLER,
        Mapping\ServiceBus\Middleware::class           => Tags::BUS_MIDDLEWARE,
        Mapping\Routing\CreateEndpoint::class          => Tags::HTTP_ROUTE,
        Mapping\Routing\CreateAndFetchEndpoint::class  => Tags::HTTP_ROUTE,
        Mapping\Routing\ExecuteEndpoint::class         => Tags::HTTP_ROUTE,
        Mapping\Routing\ExecuteAndFetchEndpoint::class => Tags::HTTP_ROUTE,
        Mapping\Routing\FetchEndpoint::class           => Tags::HTTP_ROUTE,
        Mapping\Routing\Middleware::class              => Tags::HTTP_MIDDLEWARE,
    ];

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws AnnotationException
     */
    public function process(ContainerBuilder $container): void
    {
        foreach ($this->relevantServices($container) as $file => $data) {
            assert(isset($data[0], $data[1]));

            [$definition, $annotations] = $data;
            assert($definition instanceof Definition);
            assert(is_array($annotations) && $annotations !== []);

            $this->appendTags($definition, $annotations);
            $container->addResource(new FileResource($file));
        }
    }

    /**
     * @throws AnnotationException
     * @throws \ReflectionException
     */
    private function relevantServices(ContainerBuilder $container): Generator
    {
        $reader = Mapping\Reader::fromDefault();

        foreach ($container->getDefinitions() as $definition) {
            $class       = new ReflectionClass($definition->getClass());
            $annotations = $reader->getClassAnnotations($class);

            if ($annotations === []) {
                continue;
            }

            yield $class->getFileName() => [$definition, $annotations];
        }
    }

    /**
     * @param Mapping\Annotation[] $annotations
     */
    private function appendTags(Definition $definition, array $annotations): void
    {
        foreach ($annotations as $annotation) {
            $tagName = self::SERVICE_TAGS[get_class($annotation)];

            $definition->addTag($tagName, $this->createAttributes($annotation));
        }
    }

    /**
     * @return string[]
     */
    private function createAttributes(Mapping\Annotation $annotation): array
    {
        $attributes = (array) $annotation;

        if (! $annotation instanceof Mapping\Routing\Endpoint) {
            return $attributes;
        }

        $type = self::ROUTE_BEHAVIOR[get_class($annotation)];

        $attributes['behavior'] = $type;
        $attributes['methods']  = implode(',', $attributes['methods'] ?? []);

        if (isset($attributes['name'])) {
            $attributes['route_name'] = $attributes['name'];
            unset($attributes['name']);
        }

        if (isset($attributes['redirectTo'])) {
            $attributes['redirect_to'] = $attributes['redirectTo'];
            unset($attributes['redirectTo']);
        }

        return $attributes;
    }
}
