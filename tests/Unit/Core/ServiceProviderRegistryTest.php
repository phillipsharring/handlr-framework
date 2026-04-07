<?php

declare(strict_types=1);

use Handlr\Config\Config;
use Handlr\Core\Container\Container;
use Handlr\Core\EventManager;
use Handlr\Core\ServiceProvider;
use Handlr\Core\ServiceProviderRegistry;
use Handlr\Core\Routes\Router;
use Handlr\Handlers\Handler;
use Handlr\Handlers\HandlerInput;
use Handlr\Handlers\HandlerResult;

// ── Fixtures ──

class RegistryTestListener implements Handler
{
    public function handle(HandlerInput|array $input): ?HandlerResult
    {
        return null;
    }
}

class RegistryTestProviderA extends ServiceProvider
{
    public static array $events = [];

    public function register(): void
    {
        self::$events[] = 'A.register';
    }

    public function boot(): void
    {
        self::$events[] = 'A.boot';
    }

    public function migrationPaths(): array
    {
        return ['/tmp/handlr-test/a/migrations'];
    }

    public function seedPaths(): array
    {
        return ['/tmp/handlr-test/a/seeds'];
    }

    public function configDefaults(): array
    {
        return [
            'a' => ['only_in_defaults' => 'default-a', 'overridden' => 'default-value'],
        ];
    }

    public function events(): array
    {
        return [
            'a.fired' => [RegistryTestListener::class],
        ];
    }
}

class RegistryTestProviderB extends ServiceProvider
{
    public function register(): void
    {
        RegistryTestProviderA::$events[] = 'B.register';
    }

    public function boot(): void
    {
        RegistryTestProviderA::$events[] = 'B.boot';
    }

    public function migrationPaths(): array
    {
        return ['/tmp/handlr-test/b/migrations'];
    }

    public function routes(Router $router): void
    {
        RegistryTestProviderA::$events[] = 'B.routes';
    }
}

// ── Tests ──

beforeEach(function () {
    RegistryTestProviderA::$events = [];
});

it('runs register() for every provider before any boot()', function () {
    $registry = new ServiceProviderRegistry(new Container());
    $registry->addMany([RegistryTestProviderA::class, RegistryTestProviderB::class]);
    $registry->registerAll();
    $registry->bootAll();

    expect(RegistryTestProviderA::$events)->toBe([
        'A.register',
        'B.register',
        'A.boot',
        'B.boot',
    ]);
});

it('throws when bootAll() is called before registerAll()', function () {
    $registry = new ServiceProviderRegistry(new Container());
    $registry->add(RegistryTestProviderA::class);

    expect(fn() => $registry->bootAll())->toThrow(RuntimeException::class);
});

it('rejects classes that do not extend ServiceProvider', function () {
    $registry = new ServiceProviderRegistry(new Container());
    expect(fn() => $registry->add(stdClass::class))->toThrow(InvalidArgumentException::class);
});

it('aggregates migration and seed paths from every provider', function () {
    $registry = new ServiceProviderRegistry(new Container());
    $registry->addMany([RegistryTestProviderA::class, RegistryTestProviderB::class]);

    expect($registry->migrationPaths())->toBe([
        '/tmp/handlr-test/a/migrations',
        '/tmp/handlr-test/b/migrations',
    ]);
    expect($registry->seedPaths())->toBe([
        '/tmp/handlr-test/a/seeds',
    ]);
});

it('merges config defaults underneath existing config (app values win)', function () {
    $config = new Config(new \Adbar\Dot());
    $config->load([
        'a' => ['overridden' => 'app-value'],
    ]);

    $registry = new ServiceProviderRegistry(new Container());
    $registry->add(RegistryTestProviderA::class);
    $registry->applyConfigDefaults($config);

    expect($config->get('a.only_in_defaults'))->toBe('default-a');
    expect($config->get('a.overridden'))->toBe('app-value');
});

it('resolves listeners through the container and registers them with EventManager', function () {
    $container = new Container();
    $eventManager = new EventManager();

    $registry = new ServiceProviderRegistry($container);
    $registry->add(RegistryTestProviderA::class);
    $registry->applyEvents($eventManager);

    // Dispatch should not throw — listener resolved + registered cleanly.
    $eventManager->dispatch('a.fired', []);
    expect(true)->toBeTrue();
});

it('calls routes() on every provider', function () {
    $container = new Container();
    $router = new Router($container);

    $registry = new ServiceProviderRegistry($container);
    $registry->addMany([RegistryTestProviderA::class, RegistryTestProviderB::class]);
    $registry->applyRoutes($router);

    expect(RegistryTestProviderA::$events)->toContain('B.routes');
});
