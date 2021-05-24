<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Models;

use Spiral\Models\Exception\EntityException;

/**
 * DataEntity in spiral used to represent basic data set with filters and accessors. Most of spiral
 * models (ORM and ODM, HttpFilters) will extend data entity.
 */
class DataEntity extends AbstractEntity
{
    /**
     * Set of fields allowed to be filled using setFields() method.
     *
     * @see setFields()
     * @var array
     */
    protected const FILLABLE = [];

    /**
     * List of fields not allowed to be filled by setFields() method. Replace with and empty array
     * to allow all fields.
     *
     * By default all entity fields are settable! Opposite behaviour has to be described in entity
     * child implementations.
     *
     * @see setFields()
     * @var array|string
     */
    protected const SECURED = '*';

    /**
     * @see setField()
     * @var array
     */
    protected const SETTERS = [];

    /**
     * @see getField()
     * @var array
     */
    protected const GETTERS = [];

    /**
     * Accessor used to mock field data and filter every request thought itself.
     *
     * @see getField()
     * @see setField()
     * @var array
     */
    protected const ACCESSORS = [];

    /**
     * Check if field can be set using setFields() method.
     *
     * @param string $field
     * @return bool
     *
     * @see  $secured
     * @see  setField()
     * @see  $fillable
     */
    protected function isFillable(string $field): bool
    {
        if (static::FILLABLE === '*') {
            return true;
        }

        if (!empty(static::FILLABLE)) {
            return in_array($field, static::FILLABLE);
        }

        if (static::SECURED === '*') {
            return false;
        }

        return !in_array($field, static::SECURED);
    }

    /**
     * Check and return name of mutator (getter, setter, accessor) associated with specific field.
     *
     * @param string $field
     * @param string $type Mutator type (setter, getter, accessor).
     * @return mixed|null
     *
     * @throws EntityException
     */
    protected function getMutator(string $field, string $type)
    {
        $target = [];
        switch ($type) {
            case ModelSchema::MUTATOR_ACCESSOR:
                $target = static::ACCESSORS;
                break;
            case ModelSchema::MUTATOR_GETTER:
                $target = static::GETTERS;
                break;
            case ModelSchema::MUTATOR_SETTER:
                $target = static::SETTERS;
                break;
        }

        return $target[$field] ?? null;
    }
}
