<?php

declare(strict_types=1);

namespace Spiral\Validation\Checker;

use Spiral\Validation\AbstractChecker;
use Spiral\Validation\ValidationInterface;

class ArrayChecker extends AbstractChecker
{
    /** @var ValidationInterface */
    private $validation;

    public function __construct(ValidationInterface $validation)
    {
        $this->validation = $validation;
    }

    public function of($value, $checker): bool
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!$this->validation->validate(compact('item'), ['item' => [$checker]])->isValid()) {
                return false;
            }
        }

        return true;
    }
}
