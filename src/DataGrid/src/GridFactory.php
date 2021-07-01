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

use Spiral\DataGrid\Exception\CompilerException;
use Spiral\DataGrid\Exception\GridViewException;
use Spiral\DataGrid\Input\ArrayInput;
use Spiral\DataGrid\Input\NullInput;
use Spiral\DataGrid\Specification\FilterInterface;

/**
 * Generates grid views based on provided inout source and grid specifications.
 */
class GridFactory implements GridFactoryInterface
{
    public const KEY_FILTER      = 'filter';
    public const KEY_SORT        = 'sort';
    public const KEY_PAGINATE    = 'paginate';
    public const KEY_FETCH_COUNT = 'fetchCount';

    /** @var callable */
    private $count = 'count';

    /** @var Compiler */
    private $compiler;

    /** @var InputInterface */
    private $input;

    /** @var InputInterface */
    private $defaults;

    /** @var GridInterface */
    private $view;

    /**
     * @param Compiler            $compiler
     * @param InputInterface|null $input
     * @param GridInterface|null  $view
     */
    public function __construct(Compiler $compiler, InputInterface $input = null, GridInterface $view = null)
    {
        $this->compiler = $compiler;
        $this->input = $input ?? new NullInput();
        $this->defaults = new NullInput();
        $this->view = $view ?? new Grid();
    }

    /**
     * Associate new input source with grid generator.
     *
     * @param InputInterface $input
     * @return GridFactory
     */
    public function withInput(InputInterface $input): self
    {
        $generator = clone $this;
        $generator->input = $input;

        return $generator;
    }

    /**
     * USe default filter values.
     *
     * @param array $data
     * @return $this
     */
    public function withDefaults(array $data): self
    {
        $generator = clone $this;
        $generator->defaults = new ArrayInput($data);

        return $generator;
    }

    /**
     * @param callable $counter
     * @return $this
     */
    public function withCounter(callable $counter): self
    {
        $generator = clone $this;
        $generator->count = $counter;

        return $generator;
    }

    /**
     * @inheritDoc
     */
    public function create($source, GridSchema $schema): GridInterface
    {
        $view = clone $this->view;

        ['view' => $view, 'source' => $source] = $this->applyFilters($view, $source, $schema);
        ['view' => $view, 'source' => $source] = $this->applyCounter($view, $source, $schema);
        ['view' => $view, 'source' => $source] = $this->applySorters($view, $source, $schema);
        ['view' => $view, 'source' => $source] = $this->applyPaginator($view, $source, $schema);

        if (!is_iterable($source)) {
            throw new GridViewException('GridView expects the source to be iterable after all.');
        }

        return $view->withSource($source);
    }

    protected function applyFilters(GridInterface $view, $source, GridSchema $schema): array
    {
        ['source' => $source, 'filters' => $filters] = $this->getFilters($source, $schema);
        $view = $view->withOption(GridInterface::FILTERS, $filters);

        return compact('view', 'source');
    }

    protected function getFilters($source, GridSchema $schema): array
    {
        $values = [];
        $filters = [];
        foreach ($this->getOptionArray(static::KEY_FILTER) ?? [] as $name => $value) {
            if ($schema->hasFilter($name)) {
                $filter = $schema->getFilter($name)->withValue($value);

                if ($filter !== null) {
                    $filters[$name] = $filter;
                    $values[$name] = $filter->getValue();
                }
            }
        }

        foreach ($schema->wrapFilters($filters) as $filter) {
            $source = $this->compiler->compile($source, $filter);
        }

        return ['source' => $source, 'filters' => $values];
    }

    protected function applyCounter(GridInterface $view, $source, GridSchema $schema): array
    {
        if (is_countable($source) && $this->getOption(static::KEY_FETCH_COUNT)) {
            $view = $view->withOption(GridInterface::COUNT, ($this->count)($source));
        }

        return compact('view', 'source');
    }

    protected function applySorters(GridInterface $view, $source, GridSchema $schema): array
    {
        ['source' => $source, 'sorters' => $sorters] = $this->getSorters($source, $schema);
        $view = $view->withOption(GridInterface::SORTERS, $sorters);

        return compact('view', 'source');
    }

    protected function getSorters($source, GridSchema $schema): array
    {
        $sorters = [];
        foreach ($this->getOptionArray(static::KEY_SORT) ?? [] as $name => $value) {
            if ($schema->hasSorter($name)) {
                $sorter = $schema->getSorter($name)->withDirection($value);

                if ($sorter !== null) {
                    $source = $this->compiler->compile($source, $sorter);
                    $sorters[$name] = $sorter->getValue();
                }
            }
        }

        return compact('source', 'sorters');
    }

    protected function applyPaginator(GridInterface $view, $source, GridSchema $schema): array
    {
        if ($schema->getPaginator() !== null) {
            ['source' => $source, 'paginator' => $paginator] = $this->getPaginator($source, $schema);
            $view = $view->withOption(GridInterface::PAGINATOR, $paginator);
        }

        return compact('view', 'source');
    }

    protected function getPaginator($source, GridSchema $schema): array
    {
        $paginator = $schema->getPaginator();
        if (!$paginator instanceof FilterInterface) {
            throw new CompilerException('Paginator can not be null');
        }

        $withValue = $paginator->withValue($this->getOption(static::KEY_PAGINATE));
        if ($withValue === null) {
            throw new CompilerException('Paginator can not be null');
        }

        return [
            'source'    => $this->compiler->compile($source, $withValue),
            'paginator' => $withValue->getValue(),
        ];
    }

    /**
     * Return array of options for the input. Checks the default input in case of value missing in parent.
     *
     * @param string $option
     * @return array
     */
    protected function getOptionArray(string $option): array
    {
        $result = $this->getOption($option);
        if (!is_array($result)) {
            return [];
        }

        return $result;
    }

    /**
     * Return array of options for the input. Checks the default input in case of value missing in parent.
     *
     * @param string $option
     * @return mixed
     */
    protected function getOption(string $option)
    {
        if ($this->input->hasValue($option)) {
            return $this->input->getValue($option);
        }

        return $this->defaults->getValue($option);
    }
}
