<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Bootloader\Security;

use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\TokenizerBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Security\RulesInterface;
use Spiral\Validation\Checker;
use Spiral\Validation\Condition;
use Spiral\Validation\ParserInterface;
use Spiral\Validation\RuleParser;
use Spiral\Validation\ValidationInterface;
use Spiral\Validation\ValidationProvider;
use Spiral\Validation\ValidatorInterface;

final class ValidationBootloader extends Bootloader implements SingletonInterface
{
    protected const DEPENDENCIES = [
        TokenizerBootloader::class,
    ];

    protected const SINGLETONS = [
        ValidationInterface::class => ValidationProvider::class,
        RulesInterface::class      => ValidationProvider::class,
        ParserInterface::class     => RuleParser::class,
    ];

    /** @var ConfiguratorInterface */
    private $config;

    /**
     * @param ConfiguratorInterface $config
     */
    public function __construct(ConfiguratorInterface $config)
    {
        $this->config = $config;
    }

    /**
     * @param TokenizerBootloader $tokenizer
     */
    public function boot(TokenizerBootloader $tokenizer): void
    {
        $this->config->setDefaults(
            'validation',
            [
                // Checkers are resolved using container and provide ability to isolate some validation rules
                // under common name and class. You can register new checkers at any moment without any
                // performance issues.
                'checkers'   => [
                    'array'    => Checker\ArrayChecker::class,
                    'type'     => Checker\TypeChecker::class,
                    'number'   => Checker\NumberChecker::class,
                    'mixed'    => Checker\MixedChecker::class,
                    'address'  => Checker\AddressChecker::class,
                    'string'   => Checker\StringChecker::class,
                    'file'     => Checker\FileChecker::class,
                    'image'    => Checker\ImageChecker::class,
                    'datetime' => Checker\DatetimeChecker::class,
                    'entity'   => Checker\EntityChecker::class,
                ],

                // Enable/disable validation conditions
                'conditions' => [
                    'absent'     => Condition\AbsentCondition::class,
                    'present'    => Condition\PresentCondition::class,
                    'anyOf'      => Condition\AnyOfCondition::class,
                    'noneOf'     => Condition\NoneOfCondition::class,
                    'withAny'    => Condition\WithAnyCondition::class,
                    'withoutAny' => Condition\WithoutAnyCondition::class,
                    'withAll'    => Condition\WithAllCondition::class,
                    'withoutAll' => Condition\WithoutAllCondition::class,
                ],

                // Aliases are only used to simplify developer life.
                'aliases'    => [
                    'notEmpty'   => 'type::notEmpty',
                    'notNull'    => 'type::notNull',
                    'required'   => 'type::notEmpty',
                    'datetime'   => 'type::datetime',
                    'timezone'   => 'type::timezone',
                    'bool'       => 'type::boolean',
                    'boolean'    => 'type::boolean',
                    'arrayOf'    => 'array::of',
                    'cardNumber' => 'mixed::cardNumber',
                    'regexp'     => 'string::regexp',
                    'email'      => 'address::email',
                    'url'        => 'address::url',
                    'file'       => 'file::exists',
                    'uploaded'   => 'file::uploaded',
                    'filesize'   => 'file::size',
                    'image'      => 'image::valid',
                    'array'      => 'is_array',
                    'callable'   => 'is_callable',
                    'double'     => 'is_double',
                    'float'      => 'is_float',
                    'int'        => 'is_int',
                    'integer'    => 'is_integer',
                    'numeric'    => 'is_numeric',
                    'long'       => 'is_long',
                    'null'       => 'is_null',
                    'object'     => 'is_object',
                    'real'       => 'is_real',
                    'resource'   => 'is_resource',
                    'scalar'     => 'is_scalar',
                    'string'     => 'is_string',
                    'match'      => 'mixed::match',
                ],
            ]
        );


        $tokenizer->addDirectory(\dirname(
            (new \ReflectionClass(ValidatorInterface::class))
                ->getFileName()
        ));
    }

    /**
     * @param string $alias
     * @param mixed  $checker
     */
    public function addChecker(string $alias, $checker): void
    {
        $this->config->modify('validation', new Append('checkers', $alias, $checker));
    }

    /**
     * @param string $alias
     * @param mixed  $condition
     */
    public function addCondition(string $alias, $condition): void
    {
        $this->config->modify('validation', new Append('conditions', $alias, $condition));
    }

    /**
     * @param string $alias
     * @param string $target
     */
    public function addAlias(string $alias, string $target): void
    {
        $this->config->modify('validation', new Append('aliases', $alias, $target));
    }
}
