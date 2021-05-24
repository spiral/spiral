<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Filters;

use Generator;
use ReflectionException;
use Spiral\Core\Container;
use Spiral\Core\FactoryInterface;
use Spiral\Filters\Exception\SchemaException;
use Spiral\Models\Reflection\ReflectionEntity;
use Spiral\Validation\ValidationInterface;
use Spiral\Validation\ValidatorInterface;

/**
 * Create filters based on their schema definition.
 */
final class FilterProvider implements FilterProviderInterface
{
    // Filter specific schema segments
    public const MAPPING   = 'mapping';
    public const VALIDATES = 'validates';

    // Packed schema definitions
    public const SOURCE         = 'source';
    public const ORIGIN         = 'origin';
    public const FILTER         = 'filter';
    public const ARRAY          = 'array';
    public const ITERATE_SOURCE = 'iterate_source';
    public const ITERATE_ORIGIN = 'iterate_origin';

    /** @var array */
    private $cache;

    /** @var ErrorMapper[] */
    private $errorMappers = [];

    /** @var ValidatorInterface[] */
    private $validators = [];

    /** @var ValidationInterface */
    private $validation;

    /** @var FactoryInterface */
    private $factory;

    /**
     * @param ValidationInterface   $validation
     * @param FactoryInterface|null $factory
     */
    public function __construct(ValidationInterface $validation, FactoryInterface $factory = null)
    {
        $this->validation = $validation;
        $this->factory = $factory ?? new Container();
        $this->cache = [];
    }

    /**
     * @inheritDoc
     */
    public function createFilter(string $name, InputInterface $input): FilterInterface
    {
        $schema = $this->getSchema($name);

        /** @var Filter $instance */
        $instance = $this->factory->make($name, [
            'data'        => [],
            'schema'      => $schema,
            'validator'   => $this->getValidator($name),
            'errorMapper' => $this->getErrorMapper($name),
        ]);

        $instance->setValue($this->initValues($schema[self::MAPPING], $input));

        return $instance;
    }

    /**
     * @param array          $mappingSchema
     * @param InputInterface $input
     * @return array
     */
    public function initValues(array $mappingSchema, InputInterface $input): array
    {
        $result = [];
        foreach ($mappingSchema as $field => $map) {
            if (empty($map[self::FILTER])) {
                $value = $input->getValue($map[self::SOURCE], $map[self::ORIGIN]);

                if ($value !== null) {
                    $result[$field] = $value;
                }
                continue;
            }

            $nested = $map[self::FILTER];
            if (empty($map[self::ARRAY])) {
                // slicing down
                $result[$field] = $this->createFilter($nested, $input->withPrefix($map[self::ORIGIN]));
                continue;
            }

            $values = [];

            // List of "key" => "location in request"
            foreach ($this->iterate($map, $input) as $index => $origin) {
                $values[$index] = $this->createFilter($nested, $input->withPrefix($origin));
            }

            $result[$field] = $values;
        }

        return $result;
    }

    /**
     * Create set of origins and prefixed for a nested array of models.
     *
     * @param array          $schema
     * @param InputInterface $input
     * @return Generator
     */
    private function iterate(array $schema, InputInterface $input): Generator
    {
        $values = $input->getValue($schema[self::ITERATE_SOURCE], $schema[self::ITERATE_ORIGIN]);
        if (empty($values) || !is_array($values)) {
            return [];
        }

        foreach (array_keys($values) as $key) {
            yield $key => $schema[self::ORIGIN] . '.' . $key;
        }
    }

    /**
     * @param string $filter
     * @return ErrorMapper
     */
    private function getErrorMapper(string $filter): ErrorMapper
    {
        if (isset($this->errorMappers[$filter])) {
            return $this->errorMappers[$filter];
        }

        $errorMapper = new ErrorMapper($this->getSchema($filter)[self::MAPPING]);
        $this->errorMappers[$filter] = $errorMapper;

        return $errorMapper;
    }

    /**
     * @param string $filter
     * @return ValidatorInterface
     */
    private function getValidator(string $filter): ValidatorInterface
    {
        if (isset($this->validators[$filter])) {
            return $this->validators[$filter];
        }

        $validator = $this->validation->validate([], $this->getSchema($filter)[self::VALIDATES]);
        $this->validators[$filter] = $validator;

        return $validator;
    }

    /**
     * @param string $filter
     * @return array
     *
     * @throws SchemaException
     */
    private function getSchema(string $filter): array
    {
        if (isset($this->cache[$filter])) {
            return $this->cache[$filter];
        }

        try {
            $reflection = new ReflectionEntity($filter);
            if (!$reflection->getReflection()->isSubclassOf(Filter::class)) {
                throw new SchemaException('Invalid filter class, must be subclass of Filter');
            }

            $builder = new SchemaBuilder(new ReflectionEntity($filter));
        } catch (ReflectionException $e) {
            throw new SchemaException('Invalid filter schema', $e->getCode(), $e);
        }

        $this->cache[$filter] = $builder->makeSchema();

        return $this->cache[$filter];
    }
}
