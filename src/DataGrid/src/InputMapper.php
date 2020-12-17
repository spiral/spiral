<?php

declare(strict_types=1);

namespace Spiral\DataGrid;

class InputMapper implements InputMapperInterface
{
    /** @var InputInterface */
    private $input;

    /** @var array */
    private $mapping;

    /** @var array */
    private $mapped = [];

    public function __construct(array $mapping)
    {
        $this->mapping = $mapping;
    }

    public function withInput(InputInterface $input): InputMapperInterface
    {
        $mapper = clone $this;
        $mapper->input = $input;
        $mapper->mapped = [];
        $mapper->map();

        return $mapper;
    }

    public function hasOption(string $option): bool
    {
        if (!isset($this->mapped[$option])) {
            return $this->input->hasValue($option);
        }

        $mappedOptions = $this->mapped[$option];
        foreach ($mappedOptions as $mappedOption) {
            $paths = $mappedOption[0];
            $firstPath = array_shift($paths);
            if ($this->input->hasValue($firstPath)) {
                return true;
            }
        }

        return false;
    }

    public function getOption(string $option, $default = null)
    {
        if (!isset($this->mapped[$option])) {
            return $this->input->getValue($option, $default);
        }

        $mappedOptions = $this->mapped[$option];
        $output = [];
        foreach ($mappedOptions as $mappedOption) {
            [$paths, $to] = $mappedOption;
            $firstPath = array_shift($paths);
            if (!$this->input->hasValue($firstPath)) {
                continue;
            }

            $data = $this->input->getValue($firstPath);
            if (!empty($paths) && !is_array($data)) {
                continue;
            }

            foreach ($paths as $path) {
                if (!is_array($data) || !isset($data[$path])) {
                    continue 2;
                }
                $data = $data[$path];
            }

            $result = $data;
            foreach ($to as $path) {
                $result = [$path => $result];
            }

            $output[] = $result;
        }

        return !empty($output) ? array_merge(...$output) : [];
    }

    protected function map(): void
    {
        foreach ($this->mapping as $from => $to) {
            $to = explode('.', $to);
            $option = array_shift($to);
            if (!isset($this->mapped[$option])) {
                $this->mapped[$option] = [];
            }
            $this->mapped[$option][] = [explode('.', $from), array_reverse($to)];
        }
    }
}
