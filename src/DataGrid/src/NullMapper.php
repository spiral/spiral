<?php

declare(strict_types=1);

namespace Spiral\DataGrid;

class NullMapper implements InputMapperInterface
{
    /** @var InputInterface */
    private $input;

    public function withInput(InputInterface $input): InputMapperInterface
    {
        $mapper = clone $this;
        $mapper->input = $input;

        return $mapper;
    }

    public function hasOption(string $option): bool
    {
        return $this->input->hasValue($option);
    }

    public function getOption(string $option, $default = null)
    {
        return $this->input->getValue($option, $default);
    }
}
