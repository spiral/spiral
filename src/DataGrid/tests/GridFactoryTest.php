<?php

/**
 * Spiral Framework. PHP Data Grid
 *
 * @author Valentin Vintsukevich (vvval)
 */

declare(strict_types=1);

namespace Spiral\Tests\DataGrid;

use Exception;
use PHPUnit\Framework\TestCase;
use Spiral\DataGrid\Compiler;
use Spiral\DataGrid\Exception\CompilerException;
use Spiral\DataGrid\Exception\GridViewException;
use Spiral\DataGrid\Grid;
use Spiral\DataGrid\GridFactory;
use Spiral\DataGrid\GridInterface;
use Spiral\DataGrid\GridSchema;
use Spiral\DataGrid\Input\ArrayInput;
use Spiral\DataGrid\Specification\Filter\Any;
use Spiral\DataGrid\Specification\Filter\Equals;
use Spiral\DataGrid\Specification\Filter\Gte;
use Spiral\DataGrid\Specification\Filter\NotEquals;
use Spiral\DataGrid\Specification\FilterInterface;
use Spiral\DataGrid\Specification\Pagination\PagePaginator;
use Spiral\DataGrid\Specification\Sorter\Sorter;
use Spiral\DataGrid\Specification\SorterInterface;
use Spiral\DataGrid\Specification\Value;
use Spiral\Tests\DataGrid\Fixture\NullPaginator;
use Spiral\Tests\DataGrid\Fixture\WrapWriter;
use Spiral\Tests\DataGrid\Fixture\WriterIterateNonIterable;
use Spiral\Tests\DataGrid\Fixture\WriterOne;

class GridFactoryTest extends TestCase
{
    public function testEmpty(): void
    {
        $factory = $this->factory();
        $grid = $factory->create([], new GridSchema());

        $this->assertNull($grid->getOption('option'));
    }

    public function testNonIterable(): void
    {
        $this->expectException(GridViewException::class);

        $factory = $this->factory();
        $factory->create('some non-iterable source', new GridSchema());
    }

    /**
     * @throws Exception
     */
    public function testIterateToIterable(): void
    {
        $this->assertTrue(true);

        $compiler = new Compiler();
        $compiler->addWriter(new WriterIterateNonIterable());

        $schema = new GridSchema();
        $schema->setPaginator(new PagePaginator(10));

        $factory = new GridFactory($compiler);
        $factory->create('some non-iterable source', $schema);
    }

    /**
     * @dataProvider filtersProvider
     * @param array           $input
     * @param array           $defaults
     * @param string          $name
     * @param FilterInterface $filter
     * @param array           $expected
     */
    public function testFilters(
        array $input,
        array $defaults,
        string $name,
        FilterInterface $filter,
        array $expected
    ): void {
        $factory = $this->factory()->withDefaults($defaults)->withInput(new ArrayInput($input));

        $schema = new GridSchema();
        $schema->addFilter($name, $filter);
        $grid = $factory->create([], $schema);

        $this->assertEquals($expected, $grid->getOption(GridInterface::FILTERS));
    }

    /**
     * @return iterable
     */
    public function filtersProvider(): iterable
    {
        $stringFilter = new Equals('field', new Value\StringValue());
        $numericFilter = new Equals('field', new Value\NumericValue());

        return [
            //filters are not array
            [[GridFactory::KEY_FILTER => 'scalar value'], [], 'filter', $stringFilter, []],
            [[], [GridFactory::KEY_FILTER => 'scalar value'], 'filter', $stringFilter, []],

            //filters do not match schema
            [[GridFactory::KEY_FILTER => ['filter' => 'value']], [], 'filter 2', $stringFilter, []],
            [[], [GridFactory::KEY_FILTER => ['filter' => 'value']], 'filter 2', $stringFilter, []],

            //filters do not match expected value type
            [[GridFactory::KEY_FILTER => ['filter' => 'value']], [], 'filter', $numericFilter, []],
            [[], [GridFactory::KEY_FILTER => ['filter' => 'value']], 'filter', $numericFilter, []],

            //filters match schema
            [[GridFactory::KEY_FILTER => ['filter' => 'value']], [], 'filter', $stringFilter, ['filter' => 'value']],
            [[], [GridFactory::KEY_FILTER => ['filter' => 'value']], 'filter', $stringFilter, ['filter' => 'value']],
            [[GridFactory::KEY_FILTER => ['filter' => '123']], [], 'filter', $numericFilter, ['filter' => 123]],
            [[], [GridFactory::KEY_FILTER => ['filter' => 123]], 'filter', $numericFilter, ['filter' => 123]],
        ];
    }

    /**
     * @dataProvider fetchCountProvider
     * @param array $defaults
     * @param mixed $source
     * @param mixed $expected
     */
    public function testFetchCount(array $defaults, $source, $expected): void
    {
        $factory = $this->factory()->withDefaults($defaults);
        $grid = $factory->create($source, new GridSchema());
        $this->assertEquals($expected, $grid->getOption(GridInterface::COUNT));
    }

    /**
     * @dataProvider fetchCountProvider
     * @param array $defaults
     * @param mixed $source
     * @param mixed $expected
     */
    public function testFetchCustomCount(array $defaults, $source, $expected): void
    {
        $factory = $this->factory()->withDefaults($defaults)->withCounter(static function ($select): int {
            return count($select) * 2;
        });

        $grid = $factory->create($source, new GridSchema());
        $this->assertEquals($expected * 2, $grid->getOption(GridInterface::COUNT));
    }

    /**
     * @return iterable
     */
    public function fetchCountProvider(): iterable
    {
        return [
            //no TRUE fetch count parameter
            [[], [], null],
            [[GridFactory::KEY_FETCH_COUNT => null], [], null],
            [[GridFactory::KEY_FETCH_COUNT => false], [], null],

            //has TRUE fetch count
            [[GridFactory::KEY_FETCH_COUNT => true], [], 0],
            [[GridFactory::KEY_FETCH_COUNT => true], [''], 1],
            [[GridFactory::KEY_FETCH_COUNT => 'true'], [''], 1],
        ];
    }

    /**
     * @dataProvider sortersProvider
     * @param array           $input
     * @param array           $defaults
     * @param string          $name
     * @param SorterInterface $sorter
     * @param array           $expected
     */
    public function testSorters(
        array $input,
        array $defaults,
        string $name,
        SorterInterface $sorter,
        array $expected
    ): void {
        $factory = $this->factory()->withDefaults($defaults)->withInput(new ArrayInput($input));

        $schema = new GridSchema();
        $schema->addSorter($name, $sorter);
        $grid = $factory->create([], $schema);

        $this->assertEquals($expected, $grid->getOption(GridInterface::SORTERS));
    }

    /**
     * @return iterable
     */
    public function sortersProvider(): iterable
    {
        $sorter = new Sorter('field');

        return [
            //sorters are not array
            [[GridFactory::KEY_SORT => 'asc'], [], 'sorter', $sorter, []],
            [[], [GridFactory::KEY_SORT => 'desc'], 'sorter', $sorter, []],

            //sorters do not match schema
            [[GridFactory::KEY_SORT => ['sorter' => 'asc']], [], 'sorter 2', $sorter, []],
            [[], [GridFactory::KEY_SORT => ['sorter' => 'desc']], 'sorter 2', $sorter, []],

            //sorters do not match expected value
            [[GridFactory::KEY_SORT => ['sorter' => 'ascending']], [], 'sorter', $sorter, []],
            [[], [GridFactory::KEY_SORT => ['sorter' => 'descending']], 'sorter', $sorter, []],

            //sorters match schema
            [[GridFactory::KEY_SORT => ['sorter' => 'asc']], [], 'sorter', $sorter, ['sorter' => 'asc']],
            [[GridFactory::KEY_SORT => ['sorter' => 'ASC']], [], 'sorter', $sorter, ['sorter' => 'asc']],
            [[GridFactory::KEY_SORT => ['sorter' => 1]], [], 'sorter', $sorter, ['sorter' => 'asc']],
            [[GridFactory::KEY_SORT => ['sorter' => '1']], [], 'sorter', $sorter, ['sorter' => 'asc']],
            [[], [GridFactory::KEY_SORT => ['sorter' => 'desc']], 'sorter', $sorter, ['sorter' => 'desc']],
            [[], [GridFactory::KEY_SORT => ['sorter' => 'DESC']], 'sorter', $sorter, ['sorter' => 'desc']],
            [[], [GridFactory::KEY_SORT => ['sorter' => -1]], 'sorter', $sorter, ['sorter' => 'desc']],
            [[], [GridFactory::KEY_SORT => ['sorter' => '-1']], 'sorter', $sorter, ['sorter' => 'desc']],
        ];
    }

    /**
     * @dataProvider paginatorProvider
     * @param array           $input
     * @param array           $defaults
     * @param FilterInterface $paginator
     * @param                 $expected
     * @param string|null     $expectedException
     */
    public function testPaginator(
        array $input,
        array $defaults,
        FilterInterface $paginator,
        $expected,
        string $expectedException = null
    ): void {
        if ($expectedException !== null) {
            $this->expectException($expectedException);
        }

        $factory = $this->factory()->withDefaults($defaults)->withInput(new ArrayInput($input));

        $schema = new GridSchema();
        $schema->setPaginator($paginator);
        $grid = $factory->create([], $schema);

        $this->assertEquals($expected, $grid->getOption(GridInterface::PAGINATOR));
    }

    /**
     * @return iterable
     */
    public function paginatorProvider(): iterable
    {
        $null = new NullPaginator();
        $paginator = new PagePaginator(25, [25, 50, 100]);

        return [
            //null paginator
            [[], [], $null, null, CompilerException::class],
            [$this->paginatorInput(2), [], $null, null, CompilerException::class],
            [[], $this->paginatorInput(2), $null, null, CompilerException::class],
            [$this->paginatorInput(2), $this->paginatorInput(3), $null, null, CompilerException::class],

            //Main input overrides the default one
            [$this->paginatorInput(3), $this->paginatorInput(2), $paginator, ['limit' => 25, 'page' => 3]],
            [$this->paginatorInput(3, 50), $this->paginatorInput(1, 100), $paginator, ['limit' => 50, 'page' => 3]],

            //valid values
            [[], [], $paginator, ['limit' => 25, 'page' => 1]],
            [$this->paginatorInput(2), [], $paginator, ['limit' => 25, 'page' => 2]],
            [[], $this->paginatorInput(2), $paginator, ['limit' => 25, 'page' => 2]],
            [$this->paginatorInput(2, 100), [], $paginator, ['page' => 2, 'limit' => 100]],
            [[], $this->paginatorInput(2, 100), $paginator, ['page' => 2, 'limit' => 100]],
            [$this->paginatorInput(null, 100), [], $paginator, ['page' => 1, 'limit' => 100]],
            [[], $this->paginatorInput(null, 100), $paginator, ['page' => 1, 'limit' => 100]],
        ];
    }

    public function testWrapFilters(): void
    {
        $schema = new GridSchema();
        $schema->addFilter(
            'size',
            new Equals('size', new Value\EnumValue(new Value\StringValue(), 'xs', 's', 'm', 'l', 'xl'))
        );
        $schema->addFilter(
            'color',
            new NotEquals('color', new Value\EnumValue(new Value\StringValue(), 'red', 'green', 'yellow'))
        );
        $schema->addFilter(
            'price',
            new Gte('price', new Value\FloatValue())
        );
        $schema->addFilterWrapper(
            function (array $filters): array {
                $size = $filters['size'] ?? null;
                $color = $filters['color'] ?? null;
                if ($size && $color) {
                    unset($filters['size'], $filters['color']);
                    $filters[] = new Any($size, $color);
                }
                return $filters;
            }
        );

        $compiler = new Compiler();
        $compiler->addWriter(new WrapWriter());

        $factory = new GridFactory(
            $compiler,
            new ArrayInput([GridFactory::KEY_FILTER => ['size' => 'xl', 'color' => 'red', 'price' => 1]]),
            new Grid()
        );

        /** @var Grid $view */
        $view = $factory->create([], $schema);
        $source = $view->getSource();

        $this->assertCount(2, $source);
        $this->assertInstanceOf(Gte::class, $source[0]);
        $this->assertInstanceOf(Any::class, $source[1]);

        /** @var Any $any */
        $any = $source[1];
        $filters = $any->getFilters();
        $this->assertCount(2, $filters);
        $this->assertSame('xl', $filters[0]->getValue());
        $this->assertSame('red', $filters[1]->getValue());

        $factory = new GridFactory(
            $compiler,
            new ArrayInput([GridFactory::KEY_FILTER => ['size' => 'xl', 'price' => 1]]),
            new Grid()
        );

        /** @var Grid $view */
        $view = $factory->create([], $schema);
        $source = $view->getSource();

        $this->assertCount(2, $source);
        $this->assertInstanceOf(Equals::class, $source[0]);
        $this->assertInstanceOf(Gte::class, $source[1]);
    }

    /**
     * @return GridFactory
     */
    private function factory(): GridFactory
    {
        $compiler = new Compiler();
        $compiler->addWriter(new WriterOne());

        return new GridFactory($compiler);
    }

    /**
     * @param int|null $page
     * @param int|null $limit
     * @return array
     */
    private function paginatorInput(int $page = null, int $limit = null): array
    {
        $result = [];
        if ($page === null && $limit === null) {
            return $result;
        }

        if ($page !== null) {
            $result['page'] = $page;
        }

        if ($limit !== null) {
            $result['limit'] = $limit;
        }

        return [GridFactory::KEY_PAGINATE => $result];
    }
}
