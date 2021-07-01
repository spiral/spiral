<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Validation;

use Spiral\Translator\Traits\TranslatorTrait;
use Spiral\Translator\Translator;

final class CheckerRule extends AbstractRule
{
    use TranslatorTrait;

    /** @var CheckerInterface */
    private $checker;

    /** @var string */
    private $method;

    /** @var array */
    private $args;

    /** @var string|null */
    private $message;

    /**
     * @param CheckerInterface $checker
     * @param string           $method
     * @param array            $args
     * @param null|string      $message
     */
    public function __construct(
        CheckerInterface $checker,
        string $method,
        array $args = [],
        ?string $message = null
    ) {
        $this->checker = $checker;
        $this->method = $method;
        $this->args = $args;
        $this->message = $message;
    }

    /**
     * @inheritdoc
     */
    public function ignoreEmpty($value): bool
    {
        return $this->checker->ignoreEmpty($this->method, $value, $this->args);
    }

    /**
     * @inheritdoc
     */
    public function validate(ValidatorInterface $v, string $field, $value): bool
    {
        return $this->checker->check($v, $this->method, $field, $value, $this->args);
    }

    /**
     * @inheritdoc
     */
    public function getMessage(string $field, $value): string
    {
        if (!empty($this->message)) {
            return $this->say(
                $this->message,
                array_merge([$value, $field], $this->args)
            );
        }

        return $this->checker->getMessage($this->method, $field, $value, $this->args);
    }
}
