# Handlr Framework

![Handlr](handlr.png)

A lightweight PHP middleware-style framework built around the **Pipe + Handler** pattern.

## Installation

```bash
composer require phillipsharring/handlr-framework
```

For a ready-to-go project structure, use the [Handlr App Skeleton](https://github.com/phillipsharring/handlr-app-skeleton).

## Architecture

Handlr separates HTTP concerns from business logic:

- **Pipe**  - middleware that sees `Request`/`Response`. Handles auth, validation, CORS, CSRF, etc.
- **Handler**  - pure business logic. Receives typed `HandlerInput`, returns `HandlerResult`. No HTTP awareness.
- **HandlerInput**  - validated input object. Used identically for HTTP requests and event listeners.
- **HandlerResult**  - structured result: `ok($data)` or `fail($errors)`.

Below the Pipe layer, everything is Handler/HandlerInput  - whether it came from HTTP or an event dispatch.

## What's Included

### Core
- `Request` / `Response`  - HTTP abstractions
- `Router` / `RouteGroup`  - routing with middleware pipelines and `{param:type}` constraints
- `Kernel`  - request dispatcher
- `Container`  - dependency injection with singleton/bind patterns
- `EventManager`  - synchronous pub/sub

### Database
- `Db`  - lazy PDO wrapper with transaction support
- `NullDb`  - throws on query (for simulation/test modes)
- `Table`  - CRUD base class (insert, update, delete, findWhere, findById, paginate)
- `Record`  - domain object base class with UUID support
- `Query`  - read-only query base class (rows, row, scalar, count, column)
- `MigrationRunner` / `Seeder`  - database lifecycle

### Auth & Security
- `AuthContext`  - request-scoped user identity holder
- `AuthorizationService`  - RBAC permission checking via injectable `PermissionsProviderInterface`
- `AuthSubject` / `AuthorizedUser`  - authenticated user contract and implementation
- Session-based auth pipes: `StartSessionPipe`, `SessionAuthPipe`, `RequireAuthPipe`
- Permission guard pipes: `RequirePermissionPipe`, `RequireAllPermissionsPipe`
- `CsrfService`  - token generation, validation, and rotation
- CSRF pipes: `EnsureCsrfTokenPipe`, `VerifyCsrfTokenPipe`
- `CorsPipe` / `VerifyOriginPipe`  - origin validation

### Validation
- `Validator` with rule strings (`'string|min:3'`, `'int|min:1,max:50'`)
- 20+ built-in rules, 9 sanitizers
- `ValidatedInputFactory`  - pipe-to-handler validation bridge

### API
- `Presenter`  - response envelope builder (success, error, validation error formatting)

### Utilities
- `SortableList` trait  - column sort extraction and meta generation
- `TreeSortKeyService`  - hierarchical sort key computation with locking
- `Logger`  - file-based PSR-3 logger

### Code Generation
- `make:migration`, `make:seeder`, `make:record`, `make:table`, `make:handler`, `make:pipe`, `make:scaffold`

## Standard Pipes

| Pipe | Purpose |
|------|---------|
| `ErrorPipe` | Global exception handler |
| `LogPipe` | Request logging |
| `JsonPipe` | JSON body parsing |
| `ViewPipe` | Template rendering |
| `StartSessionPipe` | PHP session initialization |
| `SessionAuthPipe` | Session-to-AuthContext bridge |
| `RequireAuthPipe` | 401 if not authenticated |
| `RequirePermissionPipe` | 403 if missing any listed permission |
| `RequireAllPermissionsPipe` | 403 if missing all listed permissions |
| `CorsPipe` | Same-origin CORS headers |
| `VerifyOriginPipe` | Origin/Referer validation for writes |
| `EnsureCsrfTokenPipe` | Token initialization + cookie transport |
| `VerifyCsrfTokenPipe` | Token validation + rotation |

## Quick Example

```php
// routes.php
$router->group('/api', [CorsPipe::class, VerifyOriginPipe::class])
    ->through([StartSessionPipe::class, SessionAuthPipe::class, EnsureCsrfTokenPipe::class, VerifyCsrfTokenPipe::class])
        ->get('/auth/me', [GetAuthStatus::class])
        ->through([RequireAuthPipe::class])
            ->get('/items', [GetItemsList::class])
            ->post('/items', [PostCreateItem::class])
        ->end()
    ->end()
->end();
```

## Requirements

- PHP >= 8.4
- PDO extension

## License

MIT
