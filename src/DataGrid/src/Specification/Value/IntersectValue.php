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

final class IntersectValue implements ValueInterface
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

        foreach ($values as $v) {
            if ($this->enum->accepts($v)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritDoc
     * @return array
     */
    public function convert($value): array
    {
        $result = [];
        foreach ((array)$value as $v) {
            if ($this->enum->accepts($v)) {
                $result[] = $this->enum->convert($v);
            }
        }

        return $result;
    }
}
