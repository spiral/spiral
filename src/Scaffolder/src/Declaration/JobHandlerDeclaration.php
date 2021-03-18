<?php

/**
 * Spiral Framework. Scaffolder
 *
 * @license MIT
 * @author  Valentin V (vvval)
 */

declare(strict_types=1);

namespace Spiral\Scaffolder\Declaration;

use Spiral\Jobs\JobHandler;
use Spiral\Reactor\ClassDeclaration;
use Spiral\Reactor\DependedInterface;

class JobHandlerDeclaration extends ClassDeclaration implements DependedInterface
{
    /**
     * @param string $name
     * @param string $comment
     */
    public function __construct(string $name, string $comment = '')
    {
        parent::__construct($name, 'JobHandler', [], $comment);

        $this->declareStructure();
    }

    /**
     * {@inheritDoc}
     * @psalm-suppress UndefinedClass
     */
    public function getDependencies(): array
    {
        return [JobHandler::class => null];
    }

    /**
     * Declare constants and boot method.
     */
    private function declareStructure(): void
    {
        $method = $this->method('invoke');
        $method->setPublic();
        $method->setReturn('void');
    }
}
