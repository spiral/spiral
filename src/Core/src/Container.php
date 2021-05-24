<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Core;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use ReflectionFunctionAbstract as ContextFunction;
use Spiral\Core\Container\Autowire;
use Spiral\Core\Container\InjectableInterface;
use Spiral\Core\Container\InjectorInterface;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\Exception\Container\ArgumentException;
use Spiral\Core\Exception\Container\AutowireException;
use Spiral\Core\Exception\Container\ContainerException;
use Spiral\Core\Exception\Container\InjectionException;
use Spiral\Core\Exception\Container\NotFoundException;
use Spiral\Core\Exception\LogicException;

/**
 * Auto-wiring container: declarative singletons, contextual injections, parent container
 * delegation and ability to lazy wire.
 *
 * Container does not support setter injections, private properties and etc. Normally it will work
 * with classes only to be as much invisible as possible. Attention, this is hungry implementation
 * of container, meaning it WILL try to resolve dependency unless you specified custom lazy
 * factory.
 *
 * You can use injectors to delegate class resolution to external container.
 *
 * @see \Spiral\Core\Container::registerInstance() to add your own behaviours.
 *
 * @see InjectableInterface
 * @see SingletonInterface
 */
final class Container implements
    ContainerInterface,
    BinderInterface,
    FactoryInterface,
    ResolverInterface,
    ScopeInterface
{
    /**
     * @var array
     */
    private $bindings = [
        ContainerInterface::class => self::class,
        BinderInterface::class    => self::class,
        FactoryInterface::class   => self::class,
        ScopeInterface::class     => self::class,
        ResolverInterface::class  => self::class,
    ];

    /**
     * List of classes responsible for handling specific instance or interface. Provides ability to
     * delegate container functionality.
     *
     * @var array
     */
    private $injectors = [];

    /**
     * Contains names of all classes which were checked for the available injectors.
     *
     * @var array
     */
    private $injectorsCache = [];

    /**
     * Container constructor.
     */
    public function __construct()
    {
        $this->bindings[static::class] = self::class;
        $this->bindings[self::class] = $this;
    }

    /**
     * Container can not be cloned.
     */
    public function __clone()
    {
        throw new LogicException('Container is not clonable');
    }

    /**
     * {@inheritDoc}
     */
    public function resolveArguments(ContextFunction $reflection, array $parameters = []): array
    {
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $parameter->getName();
            $class = null;

            /**
             * Container do not currently support union types. In the future, we
             * can provide the possibility of autowiring based on priorities (TBD).
             */
            if ($type instanceof \ReflectionUnionType) {
                $error = 'Parameter $%s in %s contains a union type hint that cannot be inferred unambiguously';
                $error = \sprintf($error, $reflection->getName(), $this->getLocationString($reflection));

                throw new ContainerException($error);
            }

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                try {
                    $class = new \ReflectionClass($type->getName());
                } catch (\ReflectionException $e) {
                    $location = $this->getLocationString($reflection);

                    $error = 'Unable to resolve `\$%s` parameter in %s: %s';
                    $error = \sprintf($error, $parameter->getName(), $location, $e->getMessage());

                    throw new ContainerException($error, $e->getCode(), $e);
                }
            }

            if (isset($parameters[$name]) && is_object($parameters[$name])) {
                if ($parameters[$name] instanceof Autowire) {
                    // Supplied by user as late dependency
                    $arguments[] = $parameters[$name]->resolve($this);
                } else {
                    // Supplied by user as object
                    $arguments[] = $parameters[$name];
                }
                continue;
            }

            // no declared type or scalar type or array
            if (!isset($class)) {
                // Provided from outside
                if (\array_key_exists($name, $parameters)) {
                    // Make sure it's properly typed
                    $this->assertType($parameter, $reflection, $parameters[$name]);
                    $arguments[] = $parameters[$name];
                    continue;
                }

                if ($parameter->isDefaultValueAvailable()) {
                    //Default value
                    $arguments[] = $parameter->getDefaultValue();
                    continue;
                }

                //Unable to resolve scalar argument value
                throw new ArgumentException($parameter, $reflection);
            }

            try {
                //Requesting for contextual dependency
                $arguments[] = $this->get($class->getName(), $name);
                continue;
            } catch (AutowireException $e) {
                if ($parameter->isOptional()) {
                    //This is optional dependency, skip
                    $arguments[] = null;
                    continue;
                }

                throw $e;
            }
        }

        return $arguments;
    }

    /**
     * {@inheritdoc}
     *
     * Context parameter will be passed to class injectors, which makes possible to use this method
     * as:
     *
     * $this->container->get(DatabaseInterface::class, 'default');
     *
     * Attention, context ignored when outer container has instance by alias.
     *
     * @param string|null $context Call context.
     *
     * @throws ContainerException
     * @throws \Throwable
     */
    public function get($id, string $context = null)
    {
        if ($id instanceof Autowire) {
            return $id->resolve($this);
        }

        return $this->make($id, [], $context);
    }

    /**
     * {@inheritdoc}
     *
     * @param string|null $context Related to parameter caused injection if any.
     *
     * @throws \Throwable
     */
    public function make(string $alias, array $parameters = [], string $context = null)
    {
        if (!isset($this->bindings[$alias])) {
            //No direct instructions how to construct class, make is automatically
            return $this->autowire($alias, $parameters, $context);
        }

        $binding = $this->bindings[$alias];
        if (\is_object($binding)) {
            //When binding is instance, assuming singleton
            return $binding;
        }

        if (\is_string($binding)) {
            //Binding is pointing to something else
            return $this->make($binding, $parameters, $context);
        }

        unset($this->bindings[$alias]);
        try {
            if ($binding[0] === $alias) {
                $instance = $this->autowire($alias, $parameters, $context);
            } else {
                $instance = $this->evaluateBinding($alias, $binding[0], $parameters, $context);
            }
        } finally {
            $this->bindings[$alias] = $binding;
        }

        if ($binding[1]) {
            //Indicates singleton
            $this->bindings[$alias] = $instance;
        }

        return $instance;
    }

    /**
     * @inheritdoc
     */
    public function runScope(array $bindings, callable $scope)
    {
        $cleanup = $previous = [];
        foreach ($bindings as $alias => $resolver) {
            if (isset($this->bindings[$alias])) {
                $previous[$alias] = $this->bindings[$alias];
            } else {
                $cleanup[] = $alias;
            }

            $this->bind($alias, $resolver);
        }

        try {
            if (ContainerScope::getContainer() !== $this) {
                return ContainerScope::runScope($this, $scope);
            }

            return $scope();
        } finally {
            foreach (\array_reverse($previous) as $alias => $resolver) {
                $this->bindings[$alias] = $resolver;
            }

            foreach ($cleanup as $alias) {
                unset($this->bindings[$alias]);
            }
        }
    }

    /**
     * Bind value resolver to container alias. Resolver can be class name (will be constructed
     * for each method call), function array or Closure (executed every call). Only object resolvers
     * supported by this method.
     *
     * @param string $alias
     * @param string|array|callable $resolver
     */
    public function bind(string $alias, $resolver): void
    {
        if (\is_array($resolver) || $resolver instanceof \Closure || $resolver instanceof Autowire) {
            // array means = execute me, false = not singleton
            $this->bindings[$alias] = [$resolver, false];

            return;
        }

        $this->bindings[$alias] = $resolver;
    }

    /**
     * Bind value resolver to container alias to be executed as cached. Resolver can be class name
     * (will be constructed only once), function array or Closure (executed only once call).
     *
     * @param string $alias
     * @param string|array|callable $resolver
     */
    public function bindSingleton(string $alias, $resolver): void
    {
        if (\is_object($resolver) && !$resolver instanceof \Closure && !$resolver instanceof Autowire) {
            // direct binding to an instance
            $this->bindings[$alias] = $resolver;

            return;
        }

        $this->bindings[$alias] = [$resolver, true];
    }

    /**
     * Check if alias points to constructed instance (singleton).
     *
     * @param string $alias
     * @return bool
     */
    public function hasInstance(string $alias): bool
    {
        if (!$this->has($alias)) {
            return false;
        }

        while (isset($this->bindings[$alias]) && \is_string($this->bindings[$alias])) {
            //Checking alias tree
            $alias = $this->bindings[$alias];
        }

        return isset($this->bindings[$alias]) && \is_object($this->bindings[$alias]);
    }

    /**
     * {@inheritdoc}
     */
    public function has($id): bool
    {
        return \array_key_exists($id, $this->bindings);
    }

    /**
     * @param string $alias
     */
    public function removeBinding(string $alias): void
    {
        unset($this->bindings[$alias]);
    }

    /**
     * Bind class or class interface to the injector source (InjectorInterface).
     *
     * @param string $class
     * @param string $injector
     * @return self
     */
    public function bindInjector(string $class, string $injector): Container
    {
        $this->injectors[$class] = $injector;
        $this->injectorsCache = [];

        return $this;
    }

    /**
     * @param string $class
     */
    public function removeInjector(string $class): void
    {
        unset($this->injectors[$class]);
        $this->injectorsCache = [];
    }

    /**
     * Every declared Container binding. Must not be used in production code due container format is
     * vary.
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Every binded injector.
     *
     * @return array
     */
    public function getInjectors(): array
    {
        return $this->injectors;
    }

    /**
     * Automatically create class.
     *
     * @param string $class
     * @param array $parameters
     * @param string $context
     * @return object
     *
     * @throws AutowireException
     * @throws \Throwable
     */
    protected function autowire(string $class, array $parameters, string $context = null)
    {
        if (!\class_exists($class)) {
            throw new NotFoundException(\sprintf("Undefined class or binding '%s'", $class));
        }

        // automatically create instance
        $instance = $this->createInstance($class, $parameters, $context);

        // apply registration functions to created instance
        return $this->registerInstance($instance, $parameters);
    }

    /**
     * @param ContextFunction $reflection
     * @return string
     */
    private function getLocationString(ContextFunction $reflection): string
    {
        $location = $reflection->getName();

        if ($reflection instanceof \ReflectionMethod) {
            return "{$reflection->getDeclaringClass()->getName()}::{$location}()";
        }

        return $location;
    }

    /**
     * Assert that given value are matched parameter type.
     *
     * @param \ReflectionParameter $parameter
     * @param ContextFunction $context
     * @param mixed $value
     *
     * @throws ArgumentException
     * @throws \ReflectionException
     */
    private function assertType(\ReflectionParameter $parameter, ContextFunction $context, $value): void
    {
        if ($value === null) {
            if (
                !$parameter->isOptional() &&
                !($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() === null)
            ) {
                throw new ArgumentException($parameter, $context);
            }

            return;
        }

        $type = $parameter->getType();
        if ($type === null) {
            return;
        }

        $typeName = $type->getName();
        if ($typeName === 'array' && !\is_array($value)) {
            throw new ArgumentException($parameter, $context);
        }

        if (($typeName === 'int' || $typeName === 'float') && !\is_numeric($value)) {
            throw new ArgumentException($parameter, $context);
        }

        if ($typeName === 'bool' && !\is_bool($value) && !\is_numeric($value)) {
            throw new ArgumentException($parameter, $context);
        }
    }

    /**
     * Create instance of desired class.
     *
     * @param string $class
     * @param array $parameters Constructor parameters.
     * @param string|null $context
     * @return object
     *
     * @throws ContainerException
     * @throws \Throwable
     */
    private function createInstance(string $class, array $parameters, string $context = null)
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new ContainerException($e->getMessage(), $e->getCode(), $e);
        }

        //We have to construct class using external injector when we know exact context
        if ($parameters === [] && $this->checkInjector($reflection)) {
            $injector = $this->injectors[$reflection->getName()];

            $instance = null;
            try {
                /** @var InjectorInterface $injectorInstance */
                $injectorInstance = $this->get($injector);

                if (!$injectorInstance instanceof InjectorInterface) {
                    throw new InjectionException(
                        \sprintf(
                            "Class '%s' must be an instance of InjectorInterface for '%s'",
                            \get_class($injectorInstance),
                            $reflection->getName()
                        )
                    );
                }

                $instance = $injectorInstance->createInjection($reflection, $context);
                if (!$reflection->isInstance($instance)) {
                    throw new InjectionException(
                        \sprintf(
                            "Invalid injection response for '%s'",
                            $reflection->getName()
                        )
                    );
                }
            } finally {
                $this->injectors[$reflection->getName()] = $injector;
            }

            return $instance;
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException(\sprintf("Class '%s' can not be constructed", $class));
        }

        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            // Using constructor with resolved arguments
            $instance = $reflection->newInstanceArgs($this->resolveArguments($constructor, $parameters));
        } else {
            // No constructor specified
            $instance = $reflection->newInstance();
        }

        return $instance;
    }

    /**
     * Checks if given class has associated injector.
     *
     * @param \ReflectionClass $reflection
     * @return bool
     */
    private function checkInjector(\ReflectionClass $reflection): bool
    {
        $class = $reflection->getName();
        if (\array_key_exists($class, $this->injectors)) {
            return $this->injectors[$class] !== null;
        }

        if (
            $reflection->implementsInterface(InjectableInterface::class)
            && $reflection->hasConstant('INJECTOR')
        ) {
            $this->injectors[$class] = $reflection->getConstant('INJECTOR');

            return true;
        }

        if (!isset($this->injectorsCache[$class])) {
            $this->injectorsCache[$class] = null;

            // check interfaces
            foreach ($this->injectors as $target => $injector) {
                if (
                    \class_exists($target, true)
                    && $reflection->isSubclassOf($target)
                ) {
                    $this->injectors[$class] = $injector;

                    return true;
                }

                if (
                    \interface_exists($target, true)
                    && $reflection->implementsInterface($target)
                ) {
                    $this->injectors[$class] = $injector;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Register instance in container, might perform methods like auto-singletons, log populations
     * and etc. Can be extended.
     *
     * @param object $instance  Created object.
     * @param array $parameters Parameters which been passed with created instance.
     * @return object
     */
    private function registerInstance($instance, array $parameters)
    {
        //Declarative singletons (only when class received via direct get)
        if ($parameters === [] && $instance instanceof SingletonInterface) {
            $alias = \get_class($instance);
            if (!isset($this->bindings[$alias])) {
                $this->bindings[$alias] = $instance;
            }
        }

        // Your code can go here (for example LoggerAwareInterface, custom hydration and etc)
        return $instance;
    }

    /**
     * @param string $alias
     * @param mixed $target Value binded by user.
     * @param array $parameters
     * @param string|null $context
     * @return mixed|null|object
     *
     * @throws ContainerException
     * @throws \Throwable
     */
    private function evaluateBinding(string $alias, $target, array $parameters, string $context = null)
    {
        if (\is_string($target)) {
            // Reference
            return $this->make($target, $parameters, $context);
        }

        if ($target instanceof Autowire) {
            return $target->resolve($this, $parameters);
        }

        if ($target instanceof \Closure) {
            try {
                $reflection = new \ReflectionFunction($target);
            } catch (\ReflectionException $e) {
                throw new ContainerException($e->getMessage(), $e->getCode(), $e);
            }

            // Invoking Closure with resolved arguments
            return $reflection->invokeArgs(
                $this->resolveArguments($reflection, $parameters)
            );
        }

        if (is_array($target) && isset($target[1])) {
            // In a form of resolver and method
            [$resolver, $method] = $target;

            // Resolver instance (i.e. [ClassName::class, 'method'])
            $resolver = $this->get($resolver);

            try {
                $method = new \ReflectionMethod($resolver, $method);
            } catch (\ReflectionException $e) {
                throw new ContainerException($e->getMessage(), $e->getCode(), $e);
            }

            $method->setAccessible(true);

            // Invoking factory method with resolved arguments
            return $method->invokeArgs(
                $resolver,
                $this->resolveArguments($method, $parameters)
            );
        }

        throw new ContainerException(\sprintf("Invalid binding for '%s'", $alias));
    }
}
