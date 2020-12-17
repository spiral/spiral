<?php

declare(strict_types=1);

namespace Spiral\DataGrid;

interface InputMapperInterface
{
    public function withInput(InputInterface $input): InputMapperInterface;

    public function hasOption(string $option): bool;

    public function getOption(string $option, $default = null);
}
