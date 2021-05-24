<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Prototype\ClassNode;

final class ConstructorParam
{
    /** @var string */
    public $name;

    /** @var bool */
    public $nullable;

    /** @var bool */
    public $hasDefault;

    /** @var mixed|null */
    public $default;

    /** @var Type|null */
    public $type;

    /** @var bool */
    public $byRef = false;

    /** @var bool */
    public $isVariadic = false;

    /** @var bool */
    private $builtIn;

    /**
     * ConstructorParam constructor.
     */
    private function __construct()
    {
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return ConstructorParam
     *
     * @throws \ReflectionException
     */
    public static function createFromReflection(\ReflectionParameter $parameter): ConstructorParam
    {
        $stmt = new self();
        $stmt->name = $parameter->getName();

        if ($parameter->hasType()) {
            $type = $parameter->getType();
            $stmt->type = Type::create($type->getName());
            $stmt->builtIn = $type->isBuiltin();
            $stmt->nullable = $type->allowsNull();
        }

        if ($parameter->isDefaultValueAvailable()) {
            $stmt->hasDefault = true;
            $stmt->default = $parameter->getDefaultValue();
        }

        $stmt->byRef = $parameter->isPassedByReference();
        $stmt->isVariadic = $parameter->isVariadic();

        return $stmt;
    }

    /**
     * @return bool
     */
    public function isBuiltIn(): bool
    {
        return $this->builtIn;
    }
}
