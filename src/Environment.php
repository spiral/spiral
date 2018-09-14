<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Framework;

final class Environment implements EnvironmentInterface
{
    /** @var string */
    private $id = '';

    /** @var array */
    private $values = [];

    /**
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $_ENV + $values;
    }

    /**
     * @inheritdoc
     */
    public function getID(): string
    {
        if (empty($this->id)) {
            $this->id = md5(serialize($this->values));
        }

        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function set(string $name, $value)
    {
        $this->values[$name] = $_ENV[$name] = $value;
        putenv("$name=$value");
    }

    /**
     * @inheritdoc
     */
    public function get(string $name, $default = null)
    {
        if (array_key_exists($name, $this->values)) {
            return $this->normalize($this->values[$name]);
        }

        return $default;
    }

    /**
     * @param mixed $value
     * @return bool|null|string
     */
    protected function normalize($value)
    {
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;

            case 'false':
            case '(false)':
                return false;

            case 'null':
            case '(null)':
                return null;

            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }
}