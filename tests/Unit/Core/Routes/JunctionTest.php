<?php

declare(strict_types=1);

use Handlr\Core\Container\Container;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Core\Routes\Router;
use Handlr\Pipes\Pipe;

// ── Fixtures ──

class JunctionTestPipe implements Pipe
{
    public static array $hits = [];

    public function __construct(public string $label = 'pipe') {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        self::$hits[] = $this->label;
        return $next($request, $response, $args);
    }
}

class JunctionTestPipeHandler implements Pipe
{
    public function __construct(public string $body) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        return $response->withJson(['body' => $this->body]);
    }
}

function makeJunctionRequest(string $method, string $uri): Request
{
    return new Request(
        query: [],
        post: [],
        body: '',
        server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
        headers: []
    );
}

beforeEach(function () {
    JunctionTestPipe::$hits = [];
});

// ── junction declaration + retrieval ──

it('returns the same RouteGroup from intoJunction that was declared', function () {
    $router = new Router(new Container());
    $declared = $router->group('/api')->junction('api.root');

    expect($router->intoJunction('api.root'))->toBe($declared);
});

it('lists declared junction names in declaration order', function () {
    $router = new Router(new Container());
    $router->group('/api')->junction('first');
    $router->group('/v2')->junction('second');

    expect($router->junctionNames())->toBe(['first', 'second']);
});

// ── error: duplicate junction ──

it('throws when the same junction name is declared twice', function () {
    $router = new Router(new Container());
    $router->group('/api')->junction('api.root');

    expect(fn() => $router->group('/v2')->junction('api.root'))
        ->toThrow(RuntimeException::class, "Junction 'api.root' was already declared");
});

// ── error: unknown junction ──

it('throws with the available junction names when looking up an unknown one', function () {
    $router = new Router(new Container());
    $router->group('/api')->junction('api.session');
    $router->group('/api')->junction('api.authed');

    expect(fn() => $router->intoJunction('api.athed'))
        ->toThrow(RuntimeException::class, 'Available junctions: api.session, api.authed');
});

it('reports (none) when no junctions have been declared', function () {
    $router = new Router(new Container());

    expect(fn() => $router->intoJunction('missing'))
        ->toThrow(RuntimeException::class, 'Available junctions: (none)');
});

// ── junction inherits prefix and pipe stack ──

it('routes attached to a junction inherit its prefix and pipe stack', function () {
    $container = new Container();
    $router = new Router($container);

    $apiPipe = new JunctionTestPipe('api');
    $authPipe = new JunctionTestPipe('auth');

    $container->singleton(JunctionTestPipe::class . '.api', $apiPipe);
    $container->singleton(JunctionTestPipe::class . '.auth', $authPipe);

    $router->group('/api', [$apiPipe])
        ->through([$authPipe])
            ->junction('api.authed')
        ->end()
    ->end();

    // A "provider" attaches to the junction.
    $handlerPipe = new JunctionTestPipeHandler('ok');
    $router->intoJunction('api.authed')
        ->get('/things', [$handlerPipe]);

    $router->dispatch(makeJunctionRequest('GET', '/api/things'), new Response());

    // Both group pipes ran, in order, before the handler.
    expect(JunctionTestPipe::$hits)->toBe(['api', 'auth']);
});

// ── route conflict detection across origins ──

it('throws when two registrations collide on the same method+path', function () {
    $router = new Router(new Container());
    $handler = new JunctionTestPipeHandler('first');

    $router->pushOrigin('FirstProvider');
    $router->get('/things', [$handler]);
    $router->popOrigin();

    $router->pushOrigin('SecondProvider');
    expect(fn() => $router->get('/things', [$handler]))
        ->toThrow(
            RuntimeException::class,
            'Route GET /things was already registered by FirstProvider; cannot redeclare from SecondProvider.'
        );
});

it('attributes routes registered without a pushed origin to "app"', function () {
    $router = new Router(new Container());
    $handler = new JunctionTestPipeHandler('first');

    $router->get('/things', [$handler]);

    $router->pushOrigin('SomeProvider');
    expect(fn() => $router->get('/things', [$handler]))
        ->toThrow(RuntimeException::class, 'already registered by app');
});

// ── normalization edge case ──

it('detects conflicts even when paths differ only in trailing slash', function () {
    $router = new Router(new Container());
    $handler = new JunctionTestPipeHandler('first');

    $router->get('/things', [$handler]);

    expect(fn() => $router->get('/things/', [$handler]))
        ->toThrow(RuntimeException::class, 'already registered');
});
