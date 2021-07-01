<?php

/**
 * Spiral Framework. PHP Data Grid
 *
 * @license MIT
 * @author  Anton Tsitou (Wolfy-J)
 * @author  Valentin Vintsukevich (vvval)
 */

declare(strict_types=1);

namespace Spiral\DataGrid;

use Spiral\DataGrid\Exception\SchemaException;
use Spiral\DataGrid\Specification\FilterInterface;
use Spiral\DataGrid\Specification\SorterInterface;

/**
 * DataSchema describe the set of available filters, sorting and pagination mode for the underlying data source. Class
 * operates as isolated configuration source.
 */
class GridSchema
{
    /** @var FilterInterface[] */
    private $filters = [];

    /** @var SorterInterface[] */
    private $sorters = [];

    /** @var callable|null */
    private $wrapper;

    /** @var FilterInterface|null */
    private $paginator;

    public function addFilterWrapper(callable $wrapper): void
    {
        $this->wrapper = $wrapper;
    }

    public function wrapFilters(array $input): array
    {
        if (!$this->wrapper) {
            return $input;
        }

        $wrapper = $this->wrapper;
        return $wrapper($input);
    }

    /**
     * Define new data filter.
     *
     * @param string          $name
     * @param FilterInterface $filter
     * @throws SchemaException
     */
    public function addFilter(string $name, FilterInterface $filter): void
    {
        if ($this->hasFilter($name)) {
            throw new SchemaException("Filter `$name` is already defined");
        }

        $this->filters[strtolower($name)] = $filter;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasFilter(string $name): bool
    {
        return isset($this->filters[strtolower($name)]);
    }

    /**
     * Get the filter configuration.
     *
     * @param string $name
     * @return FilterInterface
     * @throws SchemaException
     */
    public function getFilter(string $name): FilterInterface
    {
        if (!$this->hasFilter($name)) {
            throw new SchemaException("No such filter `$name`");
        }

        return $this->filters[strtolower($name)];
    }

    /**
     * @return FilterInterface[]
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Define new value sorter.
     *
     * @param string          $name
     * @param SorterInterface $sorter
     * @throws SchemaException
     */
    public function addSorter(string $name, SorterInterface $sorter): void
    {
        if ($this->hasSorter($name)) {
            throw new SchemaException("Sorter `$name` is already defined");
        }

        $this->sorters[strtolower($name)] = $sorter;
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasSorter(string $name): bool
    {
        return isset($this->sorters[strtolower($name)]);
    }

    /**
     * Get the sorter configuration.
     *
     * @param string $name
     * @return SorterInterface
     * @throws SchemaException
     */
    public function getSorter(string $name): SorterInterface
    {
        if (!$this->hasSorter($name)) {
            throw new SchemaException("No such sorter `$name`");
        }

        return $this->sorters[strtolower($name)];
    }

    /**
     * @return SorterInterface[]
     */
    public function getSorters(): array
    {
        return $this->sorters;
    }

    /**
     * Set the pagination filter.
     *
     * @param FilterInterface $paginator
     */
    public function setPaginator(FilterInterface $paginator): void
    {
        $this->paginator = $paginator;
    }

    /**
     * Get the pagination configuration associated with data source. When null - no pagination can be applied.
     *
     * @return FilterInterface|null
     */
    public function getPaginator(): ?FilterInterface
    {
        return $this->paginator;
    }
}
