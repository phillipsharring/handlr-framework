<?php

declare(strict_types=1);

namespace Handlr\Core\Container;

use RuntimeException;

/**
 * Exception thrown when the container cannot resolve or instantiate a service.
 *
 * Common causes:
 * - Binding to a non-existent class or interface
 * - Circular dependency detected
 * - Cannot resolve untyped constructor parameter
 * - Attempting to instantiate an abstract class or interface without binding
 *
 * @example Catching container errors:
 *     try {
 *         $service = $container->get(SomeService::class);
 *     } catch (ContainerException $e) {
 *         // Handle resolution failure
 *         error_log('Failed to resolve service: ' . $e->getMessage());
 *     }
 */
class ContainerException extends RuntimeException {}
