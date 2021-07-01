<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Prototype\NodeVisitors;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Locate all declared and requested properties.
 */
final class LocateProperties extends NodeVisitorAbstract
{
    /** @var array */
    private $properties = [];

    /** @var array */
    private $requested = [];

    private $test=[];

    /**
     * Get names of all virtual properties.
     *
     * @return array
     */
    public function getProperties(): array
    {
//        print_r([$this->requested?:null, $this->properties?:null, $this->test?:null]);
        return array_values(
            array_diff(
                array_keys($this->requested),
                array_values($this->properties)
            )
        );
    }

    /**
     * Detected declared and requested nodes.
     *
     * @param Node $node
     * @return int|null|Node
     */
    public function enterNode(Node $node)
    {
        if (
            $node instanceof Node\Expr\PropertyFetch &&
            $node->var instanceof Node\Expr\Variable &&
            $node->var->name === 'this'
        ) {
            $this->requested[$node->name->name] = $node->name->name;
        }

        if ($node instanceof Node\Stmt\Property) {
            foreach ($node->props as $prop) {
                if ($prop instanceof Node\Stmt\PropertyProperty) {
                    $this->properties[$prop->name->name] = $prop->name->name;
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassMethod) {
            print_r($node);
            foreach ($node->stmts as $stmt) {
                if (
                    $stmt instanceof Node\Expr\PropertyFetch &&
                    $stmt->var instanceof Node\Expr\Variable &&
                    $stmt->var->name === 'this'
                ) {
                    if (!isset($this->requested[$stmt->name->name])) {
                        $this->test[$stmt->name->name] = [(string)$node->name];
                    } else {
                        $this->test[$stmt->name->name][] = (string)$node->name;
                    }
                }
            }
        }
    }
}
