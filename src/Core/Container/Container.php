<?php

declare(strict_types=1);

namespace Handlr\Core\Container;

use Exception;
use Handlr\Log\Logger;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionUnionType;

final class Container implements ContainerInterface
{
    public const INIT_METHOD   = 'init';
    public const INJECT_METHOD = 'inject';

    /**
     * Stores interface to concrete class bindings (string interface, string concrete)
     * or functions that will return a class (lazy load behavior)
     */
    private array $bindings = [];

    /**
     * Stores instances of already resolved classes that should only be instantiated once (singleton behavior)
     */
    private array $singletons = [];

    /**
     * Stores callable functions (lazy loading behavior)
     */
    private array $factories = [];

    /**
     * Stores alias-to-abstract mappings. An alias can point to an interface,
     * in bindings or a singleton instance
     */
    private array $aliases = [];


    private array $resolving = [];

    /**
     * Binds an abstract class or interface to a concrete class.
     * This creates a mapping from an interface or abstract class to a concrete class implementation.
     *
     * @param string $abstract The abstract class or interface to bind
     * @param string|object|callable $concrete The concrete class to which the abstract is bound, or an instance, or lazy loader
     *
     * @throws ContainerException If the abstract or concrete class does not exist
     */
    public function bind(string $abstract, string|object|callable $concrete): ContainerInterface
    {
        if (!class_exists($abstract) && !interface_exists($abstract)) {
            throw new ContainerException("Cannot bind to a non-existent class or interface ($abstract).");
        }

        if (is_string($concrete) && !class_exists($concrete)) {
            throw new ContainerException("Cannot bind to non-existent concrete class ($concrete).");
        }

        match (true) {
            // lazy loaded factory
            is_callable($concrete) => $this->factory($abstract, $concrete),

            // instantiated object, singleton
            is_object($concrete)   => $this->singleton($abstract, $concrete),

            // string interface -> concrete binding, classic binding
            is_string($concrete)   => $this->bindings[$abstract] = $concrete,

            default                => $this->throwBindingException($abstract, $concrete),
        };

        return $this;
    }

    private function throwBindingException(string $abstract, mixed $concrete): void
    {
        $type = gettype($concrete);
        $message = "Invalid binding type `$type` for abstract `$abstract`. Expected a string (class name), object, or callable (factory).";

        (new Logger())->error($message);
        throw new ContainerException($message);
    }

    /**
     * Creates an alias for an interface -> concrete binding.
     *
     * @param string $alias The alias name to create
     * @param string $target The target, usually an interface that will be bound
     *                       to a concrete but could also alias a concrete class.
     *
     * @throws ContainerException If the alias does not exist in bindings
     */
    public function alias(string $alias, string $target): ContainerInterface
    {
        if (!class_exists($target) && !interface_exists($target)) {
            throw new ContainerException("Cannot create alias for non-existent target $target).");
        }

        $this->aliases[$alias] = $target;

        return $this;
    }

    /**
     * Returns a new instance of the class or interface associated with the alias.
     * This method always returns a fresh instance, resolving dependencies as needed.
     *
     * @template T
     * @param class-string<T> $alias The class, interface, or alias to resolve
     * @return T The newly created instance
     *
     * @throws ContainerException If the class cannot be instantiated
     */
    public function get(string $alias): mixed
    {
        return $this->instantiate($alias);
    }

    /**
     * Stores an instance associated with the alias as a "singleton"
     * If no instance is passed, it will be instantiated.
     * The object passed doesn't need to be a "true" singleton, only that the
     * container will always return the _same_ instance, as opposed to a
     * string interface -> string concrete binding, or a lazy  loading, both
     * of which will return a new instance every time
     *
     * @template T
     * @param class-string<T> $alias The class, interface, or alias to resolve as a singleton
     * @param null|object $object An already instantiated instance object
     * @return T The singleton instance
     *
     * @throws ContainerException If the class cannot be instantiated
     */
    public function singleton(string $alias, ?object $object = null): object
    {
        $abstract = $this->aliases[$alias] ?? $alias;

        if (!isset($this->singletons[$abstract])) {
            $instance = $object ?? $this->instantiate($abstract);
            $this->singletons[$abstract] = $instance;
        }

        return $this->singletons[$abstract];
    }

    public function factory(string $alias, callable $callable): void
    {
        $this->factories[$alias] = $callable;
    }

    /**
     * Resolves a class or alias, and instantiates it with its dependencies.
     * If the alias points to a bound concrete class, that class will be instantiated.
     * This method is responsible for creating new instances.
     *
     * @param string $alias The alias, class, or interface to resolve
     * @return object The newly instantiated object
     *
     * @throws ContainerException If the class or alias cannot be resolved or instantiated
     */
    private function instantiate(string $alias): object
    {
        try {
            // Resolve an alias if there is one
            $abstract = $this->aliases[$alias] ?? $alias;

            // Check if there's a singleton and return it
            if (isset($this->singletons[$abstract])) {
                return $this->singletons[$abstract];
            }

            // Check if there's a factory and return it's result
            if (isset($this->factories[$abstract])) {
                return ($this->factories[$abstract])();
            }

            // Check for a string instance -> string classname binding
            // if there is none, we're probably just getting a classname
            $className = $this->bindings[$abstract] ?? $abstract;

            return $this->resolveSafely($className);
        } catch (ContainerException $e) {
            throw $e;
        } catch (ReflectionException $e) {
            throw new ContainerException(
                "Failed to resolve $alias: " . $e->getMessage(),
                0,
                $e
            );
        } catch (Exception $e) {
            throw new ContainerException(
                "An unexpected exception occurred while resolving $alias: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @param string $class
     * @return object
     * @throws ReflectionException
     */
    private function resolveSafely(string $class): object
    {
        if (isset($this->resolving[$class])) {
            throw new ContainerException("Circular dependency detected for $class.");
        }

        $this->resolving[$class] = true;

        try {
            return $this->resolve($class);
        } finally {
            unset($this->resolving[$class]);
        }
    }

    /**
     * Uses Reflection to inspect the class, resolve its constructor dependencies,
     * and instantiate it. Delegates parameter resolution to `resolveParameter()`
     * and handles both built-in and class-based types. If no required parameters
     * exist or injection is explicitly defined, it may fall back to calling
     * `inject()` after instantiation.
     *
     * @param string $class The fully qualified class name to resolve.
     * @return object The resolved instance of the class.
     * @throws ContainerException If the class cannot be instantiated or a parameter cannot be resolved.
     * @throws ReflectionException If reflection fails.
     */
    private function resolve(string $class): object
    {
        $reflector = $this->getReflector($class);
        $this->ensureInstantiable($reflector);

        $constructor = $reflector->getConstructor();

        // no constructor, instantiate and maybe inject
        if (!$constructor) {
            return $this->tryInject($class, $reflector);
        }

        $parameters = $constructor->getParameters();

        // constructor has parameters, try to resolve them
        try {
            $dependencies = array_map([$this, 'resolveParameter'], $parameters);
            // this is what makes the instance using the constructor
            $instance = $reflector->newInstanceArgs($dependencies);

            // if instantiation worked, optionally call inject()
            return $this->maybeCallInject($instance, $reflector);
        } catch (ContainerException | ReflectionException $e) {
            // constructor parameters could not be resolved, fall back to inject
            // but only if all parameters are optional
            if ($this->hasOnlyOptionalParameters($parameters)) {
                return $this->tryInject($class, $reflector);
            }

            throw $e;
        }
    }

    /**
     * Resolves the value for a constructor or injection parameter.
     * - Built-in types with default values are returned directly.
     * - Class-based types are resolved from the container.
     * - Union types with a default are allowed.
     * Throws if the parameter is untyped or cannot be resolved.
     *
     * @param ReflectionParameter $param The parameter to resolve.
     * @return mixed The resolved value.
     * @throws ContainerException If the parameter is untyped, has no default, or cannot be resolved.
     */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        // Check for union types first
        if ($type instanceof ReflectionUnionType) {
            return $this->resolveUnionParameter($param);
        }

        if ($type === null || $type->isBuiltin()) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            throw new ContainerException(
                "Cannot resolve built-in parameter: {$param->getName()}"
                    . ' Please ensure it has a type or a default value.'
            );
        }

        // Resolve class-based dependencies through the container
        return $this->resolveDependency($type->getName());
    }

    /**
     * Resolves a union type parameter by checking if it has a default value.
     * If it does, the default value is returned; otherwise, an exception is thrown.
     *
     * @param ReflectionParameter $param The union type parameter to resolve.
     * @return mixed The default value of the parameter if available.
     * @throws ContainerException If the union type parameter has no default value.
     */
    private function resolveUnionParameter(ReflectionParameter $param): mixed
    {
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }
        throw new ContainerException(
            "Cannot resolve union type for parameter \${$param->getName()} — no default provided."
        );
    }

    /**
     * Checks if all parameters in the given array are optional.
     * This is used to determine if we can safely call `inject()` when
     * constructor parameters could not be resolved.
     *
     * @param ReflectionParameter[] $parameters The parameters to check.
     * @return bool True if all parameters are optional, false otherwise.
     */
    private function hasOnlyOptionalParameters(array $parameters): bool
    {
        foreach ($parameters as $p) {
            if (!$p->isOptional()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Resolves a given class or interface name by checking for
     * bindings, singletons, or factories registered in the container. If none
     * are found, it attempts to instantiate the dependency using `resolve()`.
     *
     * @param string $abstract The fully qualified class name or interface to resolve.
     * @return object The resolved instance of the dependency.
     * @throws ContainerException If the dependency cannot be resolved.
     */
    private function resolveDependency(string $abstract): object
    {
        // Resolve alias if it exists
        $alias = $this->aliases[$abstract] ?? $abstract;

        // Check if there's a singleton for the resolved alias or abstract
        // If so return that, otherwise get the class
        return $this->singletons[$alias] ?? $this->instantiate($alias);
    }

    /**
     * Does the creation of a ReflectionClass instance,
     * which is used to inspect the class for constructor details and other
     * reflection-based operations.
     *
     * @param string $class The fully qualified class name to reflect.
     * @return ReflectionClass The reflection instance for the given class.
     * @throws ReflectionException If the class does not exist or cannot be reflected.
     */
    private function getReflector(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }

    /**
     * Checks whether a class can be instantiated. If the class
     * is abstract, an interface, or otherwise non-instantiable, an exception
     * is thrown.
     *
     * @param ReflectionClass $reflector The ReflectionClass instance for the class.
     * @return void
     * @throws ContainerException If the class is not instantiable.
     */
    private function ensureInstantiable(ReflectionClass $reflector): void
    {
        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Cannot instantiate {$reflector->getName()}");
        }
    }

    /**
     * Attempts to instantiate a class without invoking its constructor,
     * and then injects dependencies using the `inject()` method if it exists.
     *
     * This is used in two cases:
     * 1. When the class has no constructor.
     * 2. When constructor parameters could not be resolved, but all are optional
     *    and the class defines an `inject()` method for dependency injection.
     *
     * The method enforces the injection convention: if `inject()` is defined,
     * the constructor must not require container-resolvable parameters.
     *
     * @param string $class The fully qualified class name to instantiate.
     * @param ReflectionClass $reflector A reflection of the class.
     * @return object The instantiated and optionally injected object.
     * @throws ContainerException|ReflectionException If the injection convention is violated.
     */
    private function tryInject(string $class, ReflectionClass $reflector): object
    {
        $instance = $this->instantiateWithoutConstructor($class);
        return $this->maybeCallInject($instance, $reflector);
    }

    /**
     * Optionally calls the `inject()` method on a class after it has been instantiated.
     * Validates that the class adheres to the auto-injection convention (i.e.,
     * constructor must not expect container-resolved dependencies).
     *
     * @param object $instance The instance to inject dependencies into.
     * @param ReflectionClass $reflector The reflection of the class.
     * @return object The instance after dependency injection (if applicable).
     * @throws ContainerException|ReflectionException If the constructor violates injection rules.
     */
    private function maybeCallInject(object $instance, ReflectionClass $reflector): object
    {
        if ($reflector->hasMethod(self::INJECT_METHOD)) {
            // Safe to inject
            $method = $reflector->getMethod(self::INJECT_METHOD);
            $dependencies = array_map([$this, 'resolveParameter'], $method->getParameters());
            $method->invokeArgs($instance, $dependencies);
        }

        $this->maybeCallInit($instance, $reflector);

        return $instance;
    }

    /**
     * Optionally calls a public `init()` method on the instance
     * if it exists and has no parameters after all dependencies
     * have been resolved and injected.
     *
     * The `init()` method is intended for lightweight setup that does not
     * require container-managed dependencies.
     * @throws ReflectionException
     */
    private function maybeCallInit(object $instance, ReflectionClass $reflector): void
    {
        if (!$reflector->hasMethod(self::INIT_METHOD)) {
            return;
        }

        $method = $reflector->getMethod(self::INIT_METHOD);

        // Only call public init() methods that have no parameters
        if (!$method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return;
        }

        $method->invoke($instance);
    }

    /**
     * Used when a class does not have a constructor. It simply creates a new
     * instance of the class without any additional logic.
     *
     * @param string $class The fully qualified class name to instantiate.
     * @return object A new instance of the class.
     */
    private function instantiateWithoutConstructor(string $class): object
    {
        return new $class();
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public function getSingletons(): array
    {
        return $this->singletons;
    }

    public function getFactories(): array
    {
        return $this->factories;
    }

    public function getAliases(): array
    {
        return $this->aliases;
    }
}
