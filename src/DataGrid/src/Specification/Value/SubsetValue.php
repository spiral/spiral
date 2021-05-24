<?php

/**
 * Spiral Framework. PHP Data Grid
 *
 * @license MIT
 * @author  Anton Tsitou (Wolfy-J)
 * @author  Valentin Vintsukevich (vvval)
 */

declare(strict_types=1);

namespace Spiral\DataGrid\Specification\Value;

use Spiral\DataGrid\Specification\ValueInterface;

final class SubsetValue implements ValueInterface
{
    /** @var ValueInterface */
    private $enum;

    /**
     * @param ValueInterface $enum
     * @param mixed          ...$values
     */
    public function __construct(ValueInterface $enum, ...$values)
    {
        $this->enum = new EnumValue($enum, ...$values);
    }

    /**
     * @inheritDoc
     */
    public function accepts($value): bool
    {
        $values = (array)$value;

        if (count($values) === 1) {
            return $this->enum->accepts(array_values($values)[0]);
        }

        if (empty($values)) {
            return false;
        }

        return $this->arrayType()->accepts($values);
    }

    /**
     * @inheritDoc
     */
    public function convert($value)
    {
        return $this->arrayType()->convert((array)$value);
    }

    /**
     * @return ArrayValue
     */
    private function arrayType(): ArrayValue
    {
        return new ArrayValue($this->enum);
    }
}
