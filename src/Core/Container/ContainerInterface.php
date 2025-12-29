<?php

declare(strict_types=1);

namespace Handlr\Core\Container;

interface ContainerInterface
{
    /**
     * Binds an abstract type (interface or class) to a concrete implementation, object, or factory.
     *
     * @param string $abstract The abstract type (interface or class name).
     * @param string|object|callable $concrete The concrete implementation, pre-instantiated object, or factory.
     *
     * @return ContainerInterface
     */
    public function bind(string $abstract, string|object|callable $concrete): ContainerInterface;

    /**
     * Registers or retrieves a singleton instance.
     *
     * If an instance is already registered, it is returned. Otherwise, the class
     * is resolved, stored as a singleton, and returned.
     *
     * @template T
     * @param class-string<T> $alias The class or interface to resolve.
     * @param null|object $object An optional pre-instantiated object.
     * @return T The singleton instance.
     *
     * @throws ContainerException If the class cannot be instantiated.
     */
    public function singleton(string $alias, ?object $object = null): object;

    /**
     * Registers a factory for a given abstract type.
     *
     * The factory will create a new instance of the service every time it is resolved.
     *
     * @param string $alias The class or interface to resolve.
     * @param callable $callable The factory method for creating the service.
     *
     * @return void
     */
    public function factory(string $alias, callable $callable): void;

    /**
     * Resolves an abstract type (interface or class) to its concrete implementation or instance.
     *
     * @template T
     * @param class-string<T> $alias The class, interface, or alias to resolve.
     * @return T The resolved instance of the class or interface.
     *
     * @throws ContainerException If the class cannot be resolved.
     */
    public function get(string $alias): mixed;

    /**
     * Creates an alias for an interface -> concrete binding. Can be aliased
     * before binding is set.
     *
     * Aliases provide alternative names for services but do not create new bindings.
     *
     * @param string $alias The alias to create.
     * @param string $target The target binding.
     *
     * @return ContainerInterface
     */
    public function alias(string $alias, string $target): ContainerInterface;
}
