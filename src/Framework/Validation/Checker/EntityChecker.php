<?php

declare(strict_types=1);

namespace Spiral\Validation\Checker;

use Cycle\ORM\ORMInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Database\Injection\Parameter;
use Spiral\Validation\AbstractChecker;

class EntityChecker extends AbstractChecker implements SingletonInterface
{
    /**
     * {@inheritdoc}
     */
    public const MESSAGES = [
        'exists' => '[[Entity not exists.]]',
        'unique' => '[[Value should be unique.]]',
    ];

    /** @var ORMInterface */
    private $orm;

    /**
     * @param ORMInterface $orm
     */
    public function __construct(ORMInterface $orm)
    {
        $this->orm = $orm;
    }

    /**
     * @param string|int|array  $value
     * @param string      $role
     * @param string|null $field
     * @return bool
     */
    public function exists($value, string $role, ?string $field = null): bool
    {
        $repository = $this->orm->getRepository($role);

        if (!empty($value)) {
            if (is_scalar($value)) {
                if ($field === null) {
                    return $repository->findByPK($value) !== null;
                }

                return $repository->findOne([$field => $value]) !== null;
            }

            if (is_array($value)) {
                $select = $repository->select();

                if ($field !== null) {
                    return $select
                            ->where($field, 'IN', new Parameter($value))
                            ->count() === \count($value);
                }

                return $select
                        ->where('id', 'IN', new Parameter($value))
                        ->count() === \count($value);
            }
        }

        return false;
    }

    /**
     * @param mixed    $value
     * @param string   $role
     * @param string   $field
     * @param string[] $withFields
     * @return bool
     */
    public function unique($value, string $role, string $field, array $withFields = []): bool
    {
        $values = $this->withValues($withFields);
        $values[$field] = $value;

        if ($this->isProvidedByContext($role, $values)) {
            return true;
        }

        return $this->orm->getRepository($role)->findOne($values) === null;
    }

    /**
     * @param string[] $fields
     * @return array
     */
    private function withValues(array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            if ($this->getValidator()->hasValue($field)) {
                $values[$field] = $this->getValidator()->getValue($field);
            }
        }

        return $values;
    }

    /**
     * @param string $role
     * @param array  $values
     * @return bool
     */
    private function isProvidedByContext(string $role, array $values): bool
    {
        $entity = $this->getValidator()->getContext();
        if (!is_object($entity) || !$this->orm->getHeap()->has($entity)) {
            return false;
        }

        $extract = $this->orm->getMapper($role)->extract($entity);
        foreach ($values as $field => $value) {
            if (!isset($extract[$field]) || $extract[$field] !== $value) {
                return false;
            }
        }

        return true;
    }
}
