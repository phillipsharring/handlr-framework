<?php

declare(strict_types=1);

namespace Handlr\Core\Container;

use Exception;
use Handlr\Log\Logger;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * Dependency injection container with auto-wiring support.
 *
 * Automatically resolves class dependencies via constructor injection.
 * Supports bindings, singletons, factories, and aliases.
 *
 * DEVELOPER USAGE:
 *
 * GETTING SERVICES (most common):
 * @example Resolve a class (auto-wired):
 *     $userService = $container->get(UserService::class);
 *     // Dependencies are automatically injected via constructor
 *
 * @example Resolve an interface (requires binding):
 *     $logger = $container->get(LoggerInterface::class);
 *
 * REGISTERING SERVICES:
 * @example Bind interface to implementation:
 *     $container->bind(LoggerInterface::class, FileLogger::class);
 *     // Now get(LoggerInterface::class) returns a new FileLogger each time
 *
 * @example Register a singleton (same instance every time):
 *     $container->singleton(Config::class, new Config($data));
 *     // Or let container create it:
 *     $container->singleton(Config::class);
 *
 * @example Register a factory (custom creation logic):
 *     $container->factory(DbConnection::class, function() {
 *         return new DbConnection($_ENV['DB_HOST'], $_ENV['DB_NAME']);
 *     });
 *
 * @example Create an alias:
 *     $container->alias('db', DbConnection::class);
 *     $db = $container->get('db'); // Same as get(DbConnection::class)
 *
 * INJECTION METHODS:
 * The container supports two special methods on classes:
 *
 * @example inject() - Alternative to constructor injection:
 *     class MyHandler {
 *         private UserService $users;
 *         public function inject(UserService $users): void {
 *             $this->users = $users;
 *         }
 *     }
 *
 * @example init() - Called after all injection is complete:
 *     class MyService {
 *         public function init(): void {
 *             // Setup logic after dependencies are injected
 *         }
 *     }
 *
 * RESOLVING THE CONTAINER ITSELF:
 * @example Inject the container (use sparingly):
 *     class ServiceLocator {
 *         public function __construct(private Container $container) {}
 *     }
 */
class Container implements ContainerInterface
{
    /** @var string Method name for post-injection initialization */
    public const INIT_METHOD   = 'init';

    /** @var string Method name for setter-style dependency injection */
    public const INJECT_METHOD = 'inject';

    /** @var array<string, string> Interface/abstract to concrete class bindings */
    private array $bindings = [];

    /** @var array<string, object> Singleton instances (same instance returned every time) */
    private array $singletons = [];

    /** @var array<string, callable> Factory callables (invoked each time to create new instance) */
    private array $factories = [];

    /** @var array<string, string> Alias to target mappings (shorthand names for services) */
    private array $aliases = [];

    /** @var array<string, bool> Currently resolving classes (for circular dependency detection) */
    private array $resolving = [];

    /**
     * Bind an abstract class or interface to a concrete implementation.
     *
     * This is the primary method for configuring the container. The binding
     * behavior depends on what you pass as $concrete:
     * - String class name: Creates new instance each time (standard binding)
     * - Object instance: Registers as singleton (same instance every time)
     * - Callable: Registers as factory (called each time to create instance)
     *
     * @param string $abstract The abstract class or interface to bind
     * @param string|object|callable $concrete Class name, instance, or factory
     * @return ContainerInterface Fluent interface
     *
     * @throws ContainerException If the abstract or concrete class does not exist
     *
     * @example Bind interface to class (new instance each get()):
     *     $container->bind(CacheInterface::class, RedisCache::class);
     *
     * @example Bind with pre-created instance (singleton):
     *     $container->bind(Config::class, new Config($settings));
     *
     * @example Bind with factory (custom creation):
     *     $container->bind(Logger::class, fn() => new Logger($path));
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
     * Create an alias for a class or interface.
     *
     * Aliases provide shorthand names for services. The alias can be created
     * before or after the target is bound.
     *
     * @param string $alias The alias name (can be any string)
     * @param string $target The target class or interface
     * @return ContainerInterface Fluent interface
     *
     * @throws ContainerException If the target class/interface does not exist
     *
     * @example Create shorthand alias:
     *     $container->alias('db', DatabaseConnection::class);
     *     $db = $container->get('db');
     *
     * @example Alias an interface:
     *     $container->bind(CacheInterface::class, RedisCache::class);
     *     $container->alias('cache', CacheInterface::class);
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
     * Resolve and return an instance of a class or interface.
     *
     * THIS IS THE PRIMARY METHOD FOR RETRIEVING SERVICES.
     *
     * Behavior depends on how the service is registered:
     * - Singleton: Returns the same instance every time
     * - Factory: Calls the factory each time (new instance)
     * - Binding: Creates new instance with auto-wired dependencies
     * - No registration: Auto-wires the class directly
     *
     * @template T
     * @param class-string<T> $alias The class, interface, or alias to resolve
     * @return T The resolved instance
     *
     * @throws ContainerException If the class cannot be instantiated
     *
     * @example Get a service:
     *     $userService = $container->get(UserService::class);
     *
     * @example Get via interface (must be bound first):
     *     $cache = $container->get(CacheInterface::class);
     *
     * @example Get via alias:
     *     $db = $container->get('db');
     *
     * @example Auto-wire a class (no binding needed):
     *     // If UserController has typed constructor params, they're auto-resolved
     *     $controller = $container->get(UserController::class);
     */
    public function get(string $alias): mixed
    {
        return $this->instantiate($alias);
    }

    /**
     * Register or retrieve a singleton instance.
     *
     * Ensures only one instance exists for the given alias. If called with
     * an object, registers it. If called without, creates and registers one.
     * Subsequent calls always return the same instance.
     *
     * Use singletons for:
     * - Services that maintain state (database connections, caches)
     * - Expensive-to-create objects
     * - Services that should be shared across the application
     *
     * @template T
     * @param class-string<T> $alias The class or interface to register
     * @param object|null $object Optional pre-created instance to register
     * @return T The singleton instance
     *
     * @throws ContainerException If the class cannot be instantiated
     *
     * @example Register with pre-created instance:
     *     $container->singleton(Config::class, new Config($data));
     *
     * @example Register and let container create it:
     *     $container->singleton(DatabaseConnection::class);
     *     // First call creates instance, subsequent calls return same instance
     *
     * @example Register interface singleton:
     *     $container->bind(SessionInterface::class, DatabaseSession::class);
     *     $container->singleton(SessionInterface::class);
     *
     * @example Retrieve existing singleton:
     *     $config = $container->singleton(Config::class); // Returns same instance
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

    /**
     * Register a factory for creating instances.
     *
     * The factory callable is invoked each time the service is resolved,
     * creating a new instance every time. Use for services that need
     * custom creation logic or should not be shared.
     *
     * @param string $alias The class or interface to register
     * @param callable $callable Factory function that returns an instance
     *
     * @example Factory with environment config:
     *     $container->factory(DbConnection::class, function() {
     *         return new DbConnection(
     *             $_ENV['DB_HOST'],
     *             $_ENV['DB_NAME'],
     *             $_ENV['DB_USER'],
     *             $_ENV['DB_PASS']
     *         );
     *     });
     *
     * @example Factory using container for dependencies:
     *     $container->factory(ReportGenerator::class, function() use ($container) {
     *         return new ReportGenerator(
     *             $container->get(DataSource::class),
     *             $container->get(TemplateEngine::class)
     *         );
     *     });
     */
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
            if ($alias === ContainerInterface::class || $alias === Container::class) {
                return $this;
            }

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

    /**
     * Get all registered bindings (for debugging/testing).
     *
     * @return array<string, string> Map of abstract => concrete class names
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get all registered singletons (for debugging/testing).
     *
     * @return array<string, object> Map of abstract => instance
     */
    public function getSingletons(): array
    {
        return $this->singletons;
    }

    /**
     * Get all registered factories (for debugging/testing).
     *
     * @return array<string, callable> Map of abstract => factory callable
     */
    public function getFactories(): array
    {
        return $this->factories;
    }

    /**
     * Get all registered aliases (for debugging/testing).
     *
     * @return array<string, string> Map of alias => target
     */
    public function getAliases(): array
    {
        return $this->aliases;
    }
}
