<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Tests\Prototype;

use PHPUnit\Framework\TestCase;
use Spiral\Core\Container;
use Spiral\Prototype\ClassNode;
use Spiral\Prototype\Exception\ClassNotDeclaredException;
use Spiral\Prototype\Injector;
use Spiral\Prototype\NodeExtractor;
use Spiral\Tests\Prototype\ClassNode\ConflictResolver\Fixtures as ResolverFixtures;
use Spiral\Tests\Prototype\Fixtures\LocalRedundantInjection;
use Spiral\Tests\Prototype\Fixtures\Dependencies;
use Spiral\Tests\Prototype\Fixtures\TestClass;

class InjectorTest extends TestCase
{
    use BackwardCompatibilityTrait;

    public function setUp(): void
    {
        if ((string)ini_get('zend.assertions') === 1) {
            ini_set('zend.assertions', 0);
        }
    }

    public function testLocalRedundantInjection(): void
    {
        $target = LocalRedundantInjection::class;
        $reflection = new \ReflectionClass($target);
        $filename = $reflection->getFileName();
        $source = file_get_contents($filename);

        try {
            $i = new Injector();
            $printed = $i->injectDependencies(
                file_get_contents($filename),
                $this->getDefinition($filename, ['test' => TestClass::class])
            );

            $this->assertMatchesRegularExpression('/@var TestClass[\s|\r\n]/', $printed);
            $this->assertStringContainsString('@param TestClass $test', $printed);
            $this->assertStringNotContainsString('private $test;', $printed);
            $this->assertStringNotContainsString('private TestClass $test;', $printed);
//            print_r($printed);
        } finally {
            file_put_contents($filename, $source);
        }
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testSimpleInjection(): void
    {
        $i = new Injector();

        $filename = __DIR__ . '/Fixtures/TestClass.php';
        $printed = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class])
        );

        $this->assertStringContainsString(TestClass::class, $printed);
    }

    /**
     * @dataProvider typedProvider
     * @param bool $useTypedProperties
     * @param bool $noPhpDoc
     * @param bool $expectedTypedProperty
     * @param bool $expectedPhpDoc
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testTypedInjection(
        bool $useTypedProperties,
        bool $noPhpDoc,
        bool $expectedTypedProperty,
        bool $expectedPhpDoc
    ): void {
        $i = new Injector();

        $filename = __DIR__ . '/Fixtures/TestClass.php';
        $printed = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class]),
            false,
            $useTypedProperties,
            $noPhpDoc
        );

        if ($expectedTypedProperty) {
            $this->assertStringContainsString('private TestClass $testClass;', $printed);
        } else {
            $this->assertStringNotContainsString('private TestClass $testClass;', $printed);
        }

        if ($expectedPhpDoc) {
            $this->assertRegExp('/@var TestClass[\s|\r\n]/', $printed);
        } else {
            $this->assertNotRegExp('/@var TestClass[\s|\r\n]/', $printed);
        }
    }

    public function typedProvider(): iterable
    {
        return [
            [true, true, true, false],
            [true, false, true, true],
            [false, false, false, true],
            [false, true, false, true],
        ];
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testEmptyInjection(): void
    {
        $i = new Injector();

        $filename = __DIR__ . '/Fixtures/TestEmptyClass.php';
        $content = file_get_contents($filename);
        $printed = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, [])
        );

        $this->assertEquals($content, $printed);
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testTraitRemove(): void
    {
        $i = new Injector();

        $filename = __DIR__ . '/Fixtures/TestClass.php';
        $r = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class])
        );

        $this->assertStringContainsString('use PrototypeTrait;', $r);

        $r = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class]),
            true
        );

        $this->assertStringNotContainsString('use PrototypeTrait;', $r);
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testParentConstructorCallInjection(): void
    {
        $i = new Injector();

        $filename = __DIR__ . '/Fixtures/ChildClass.php';
        $r = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class])
        );

        $this->assertStringContainsString(TestClass::class, $r);
        $this->assertStringContainsString('parent::__construct(', $r);
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testNoParentConstructorCallInjection(): void
    {
        $i = new Injector();

        $filename = __DIR__ . '/Fixtures/ChildWithConstructorClass.php';
        $r = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class])
        );

        $this->assertStringContainsString(TestClass::class, $r);
        $this->assertStringNotContainsString('parent::__construct(', $r);
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testModifyConstructor(): void
    {
        $filename = __DIR__ . '/Fixtures/WithConstructor.php';
        $extractor = new Traverse\Extractor();

        $parameters = $extractor->extractFromFilename($filename);
        $this->assertArrayNotHasKey('testClass', $parameters);

        $i = new Injector();

        $printed = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class])
        );

        $this->assertStringContainsString('@param HydratedClass $h', $printed);
        $this->assertStringContainsString('@param TestClass $testClass', $printed);

        $parameters = $extractor->extractFromString($printed);
        $this->assertArrayHasKey('testClass', $parameters);
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testPriorOptionalConstructorParameters(): void
    {
        $filename = __DIR__ . '/Fixtures/OptionalConstructorArgsClass.php';
        $extractor = new Traverse\Extractor();

        $parameters = $extractor->extractFromFilename($filename);
        $this->assertArrayNotHasKey('testClass', $parameters);

        $i = new Injector();

        $printed = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition($filename, ['testClass' => TestClass::class])
        );

        $parameters = $extractor->extractFromString($printed);
        $this->assertSame(['testClass', 'a', 'b', 'c', 'd', 'e'], array_keys($parameters));

        $this->assertFalse($parameters['a']['optional']);
        $this->assertFalse($parameters['b']['optional']);
        $this->assertTrue($parameters['c']['optional']);
        $this->assertTrue($parameters['d']['optional']);
        $this->assertTrue($parameters['e']['optional']);
    }

    /**
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    public function testParentConstructorParamsTypeDefinition(): void
    {
        $i = new Injector();

        $filename = __DIR__ . '/ClassNode/ConflictResolver/Fixtures/ChildClass.php';
        $printed = $i->injectDependencies(
            file_get_contents($filename),
            $this->getDefinition(
                $filename,
                [
                    'test'  => ResolverFixtures\Test::class,
                    'test2' => ResolverFixtures\SubFolder\Test::class,
                    'test3' => ResolverFixtures\ATest3::class,
                ]
            )
        );

        $extractor = new Traverse\Extractor();
        $parameters = $extractor->extractFromString($printed);

        $this->assertArrayHasKey('str1', $parameters);
        $this->assertEquals('string', $parameters['str1']['type']);
        $this->assertStringContainsString('* @param string $str', $printed);
        $this->assertFalse($parameters['str1']['optional']);
        $this->assertFalse($parameters['str1']['byRef']);
        $this->assertFalse($parameters['str1']['variadic']);

        $this->assertArrayHasKey('var', $parameters);
        $this->assertNull($parameters['var']['type']);
        $this->assertStringContainsString(' * @param $var', $printed);
        $this->assertFalse($parameters['var']['optional']);

        $this->assertArrayHasKey('untypedVarWithDefault', $parameters);
        $this->assertNull($parameters['untypedVarWithDefault']['type']);
        $this->assertStringContainsString('* @param $untypedVarWithDefault', $printed);
        $this->assertTrue($parameters['untypedVarWithDefault']['optional']);

        $this->assertArrayHasKey('refVar', $parameters);
        $this->assertNull($parameters['refVar']['type']);
        $this->assertStringContainsString('* @param $refVar', $printed);
        $this->assertFalse($parameters['refVar']['optional']);
        $this->assertTrue($parameters['refVar']['byRef']);
        $this->assertFalse($parameters['refVar']['variadic']);

        //Parameter type ATest3 has an alias in a child class
        $this->assertArrayHasKey('testApp', $parameters);
        $this->assertEquals('ATestAlias', $parameters['testApp']['type']);
        $this->assertStringContainsString('@param ATestAlias $testApp', $printed);
        $this->assertStringNotContainsString('@param ATest3 $testApp', $printed);
        $this->assertFalse($parameters['testApp']['optional']);

        $this->assertArrayHasKey('str2', $parameters);
        $this->assertEquals('?string', $parameters['str2']['type']);
        $this->assertStringContainsString('* @param string|null $str2', $printed);
        $this->assertFalse($parameters['str2']['optional']);

        //We do not track leading "\" in the class name here
        $this->assertArrayHasKey('nullableClass1', $parameters);
        $this->assertEquals('?StdClass', $parameters['nullableClass1']['type']);
        $this->assertStringContainsString('* @param \StdClass|null $nullableClass1', $printed);
        $this->assertFalse($parameters['nullableClass1']['optional']);

        $this->assertArrayHasKey('test1', $parameters);
        $this->assertEquals('?Test', $parameters['test1']['type']);
        $this->assertStringContainsString('* @param Test|null $test1', $printed);
        $this->assertTrue($parameters['test1']['optional']);

        $this->assertArrayHasKey('str3', $parameters);
        $this->assertEquals('?string', $parameters['str3']['type']);
        $this->assertStringContainsString('* @param string|null $str3', $printed);
        $this->assertTrue($parameters['str3']['optional']);

        $this->assertArrayHasKey('int', $parameters);
        $this->assertEquals('?int', $parameters['int']['type']);
        $this->assertStringContainsString('* @param int|null $int', $printed);
        $this->assertTrue($parameters['int']['optional']);

        $this->assertArrayHasKey('nullableClass2', $parameters);
        $this->assertEquals('?StdClass', $parameters['nullableClass2']['type']);
        $this->assertStringContainsString('* @param \StdClass|null $nullableClass2', $printed);
        $this->assertTrue($parameters['nullableClass2']['optional']);

        $this->assertArrayHasKey('variadicVar', $parameters);
        $this->assertEquals('string', $parameters['variadicVar']['type']);
        $this->assertStringContainsString('* @param string ...$variadicVar', $printed);
        $this->assertFalse($parameters['variadicVar']['optional']);
        $this->assertFalse($parameters['variadicVar']['byRef']);
        $this->assertTrue($parameters['variadicVar']['variadic']);
    }

    /**
     * @param string $filename
     * @param array  $dependencies
     *
     * @return ClassNode
     * @throws \ReflectionException
     * @throws ClassNotDeclaredException
     */
    private function getDefinition(string $filename, array $dependencies): ClassNode
    {
        return $this->getExtractor()->extract($filename, Dependencies::convert($dependencies));
    }

    private function getExtractor(): NodeExtractor
    {
        $container = new Container();

        return $container->get(NodeExtractor::class);
    }
}
