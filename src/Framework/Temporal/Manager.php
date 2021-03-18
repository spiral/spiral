<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Temporal;

use Cycle\ORM\Promise\Resolver;

/**
 * @internal Manager is an internal library class, please do not use it in your code.
 * @psalm-internal Spiral\Temporal
 *
 * @template T of object
 * @psalm-type Resolver = callable(): T
 */
abstract class Manager
{
    /**
     * @var string|null
     */
    private $default;

    /**
     * @var array<string, Resolver>
     */
    private $resolvers = [];

    /**
     * @var array<string, T>
     */
    private $resolved = [];

    /**
     * @param string|null $default
     */
    public function __construct(?string $default)
    {
        $this->default = $default;
    }

    /**
     * @param string $name
     * @param Resolver $resolver
     */
    public function addResolver(string $name, callable $resolver): void
    {
        $this->resolvers[$name] = $resolver;
    }

    /**
     * @param string|null $name
     * @return object
     * @throw \OutOfBoundsException
     */
    protected function resolve(string $name = null): object
    {
        $name = $name ?? $this->default;

        if ($name === null) {
            throw new \OutOfBoundsException('Default config not defined', 0x00);
        }

        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (isset($this->resolvers[$name])) {
            return $this->resolved[$name] = $this->resolvers[$name]();
        }

        throw new \OutOfBoundsException('Could not resolve service named [' . $name . ']', 0x01);
    }
}
