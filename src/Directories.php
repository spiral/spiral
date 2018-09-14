<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Framework;

use Spiral\Framework\Exceptions\DirectoryException;

/**
 * Manage application directories set.
 */
final class Directories implements DirectoriesInterface
{
    /** @var array */
    private $directories = [];

    /**
     * @param array $directories
     */
    public function __construct(array $directories)
    {
        foreach ($directories as $name => $directory) {
            $this->set($name, $directory);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->directories);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, string $path): DirectoriesInterface
    {
        $path = str_replace(['\\', '//'], '/', $path);
        $this->directories[$name] = rtrim($path, '/') . '/';

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): string
    {
        if (!$this->has($name)) {
            throw new DirectoryException("Undefined directory '{$name}'");
        }

        return $this->directories[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getAll(): array
    {
        return $this->directories;
    }
}