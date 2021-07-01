<?php

declare(strict_types=1);

namespace Spiral\Tests\Prototype\Fixtures;

use Spiral\Prototype\Traits\PrototypeTrait;
use Spiral\Tests\Prototype\ClassNode\ConflictResolver\Fixtures\Test as Test2;

class LocalRedundantInjection
{
    use PrototypeTrait;
    /** @var Test2 */
    private $test;

    /**
     * @param Test2 $test6
     */
    public function __construct(Test2 $test6)
    {
        $this->test = $test6;
        $this->test;
    }
}
