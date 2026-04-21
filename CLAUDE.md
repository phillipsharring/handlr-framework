# Handlr Backend Framework  - Claude Code Notes

## Architecture

PHP API framework with Laravel-style conventions. Installed via Composer as `phillipsharring/handlr-framework`.

## Core Abstractions

- **Pipe**  - HTTP layer. Knows `Request`/`Response`. Extracts input, calls Handler, returns Response.
- **Handler**  - Business logic. Receives `HandlerInput|array`, returns `?HandlerResult`. Used for both request handlers and event listeners.
- **HandlerInput**  - Typed input object. Constructor takes `array $body` and optional `Validator`.
- **HandlerResult**  - Return type for handlers. `$result->ok($data)` or `$result->fail(['error message'])`.
- **Listener**  - A Handler registered on an event. Same interface, returns `null`.
- **EventManager**  - `register(eventName, handler)` / `dispatch(eventName, handlerInput)`. Synchronous.
- **Table**  - Active Record-style data access. `insert()`, `update()`, `delete()`, `findById()`, `findFirst()`, `findWhere()`.
- **Record**  - Data object with magic `__get`/`__set` over internal `$data` array.
- **Query**  - Abstract base for read-only query classes: `rows()`, `row()`, `scalar()`, `count()`, `column()`, `uuidToBin()`, `binToUuid()`.

## Record Classes (CRITICAL)

Records use `@property` docblocks for IDE support, NOT real public properties. All data lives in the internal `$data` array via `__get()`/`__set()` magic methods. Real public properties shadow the magic methods and break `toPersistableArray()` / `update()`.

```php
// CORRECT
/** @property string|null $name */
class ThingRecord extends Record { }

// WRONG  - breaks update()
class ThingRecord extends Record {
    public ?string $name = null;  // shadows __get/__set
}
```

## Table Classes

Use `$tableName` not `$table`:
```php
protected string $tableName = 'things';  // correct
protected string $table = 'things';       // wrong
```

### Table Method Reference

- `insert(Record $record)`  - inserts record, auto-generates UUID for `id`. Requires a Record object, NOT an array.
- `update(Record $record)`  - updates by primary key.
- `delete(Record $record)`  - deletes by `$record->primaryKey()` (defaults to `id`). For composite-PK tables, use raw SQL.
- `findById($id)`  - primary key lookup. Use this for single-record fetches.
- `findFirst($columns, $conditions)`  - returns first match or null.
- `findWhere($columns, $conditions, $orderBy, $limit)`  - `$orderBy` is array of `[column, direction?]` pairs. `$limit` is `?int`.
- `findFirst([], ['id' => $x, 'other_uuid' => $y])` with multiple UUID conditions can fail silently. Use `findById($id)` then verify ownership separately.
- Conditions support `['column' => null]` which generates `column IS NULL`.
- `['column' => ['NOT NULL']]` generates `column IS NOT NULL`. Do NOT use `['<>', null]`  - it generates `column <> ?` with null bound, which is always false in SQL.

### Table Limitations

- `insert()` and `delete()` require an `id` column. Pivot/junction tables without `id` need raw SQL.
- `insert()` auto-adds a UUID `id` (Record has `$useUuid = true` by default).

## Pipe → Handler Validation Pattern

```php
/**
 * @var SomeInput $input
 * @see SomeInput::validateSomething()
 */
[$input, $errors] = $this->factory->makeValidatedInput(
    $request,
    SomeInput::class,
    'validateSomething',                              // validation method
    ['user_id' => $this->authContext->getUserId()]     // server-set values
);
```

- Always pass a validation method (3rd arg). Handlers assume input is valid.
- Server-set values (e.g. `user_id`) go via `additionalData` (4th arg), not manual assignment after the call.
- `additionalData` takes precedence over `parsedBody`  - users can't override server-set values.
- Add `@see` docblock pointing to the validation method.

## Routing

```php
$router->group('/api', [CorsPipe::class, VerifyOriginPipe::class])
    ->through([RequireAuthPipe::class])
        ->get('/things', [GetThingsList::class])
        ->post('/things', [PostCreateThing::class])
        ->get('/things/{id:uuid}', [GetOneThing::class])
    ->end()
->end();
```

- `RouteGroup::through()` creates a pipe-only group (no prefix change). Requires `->end()`.
- Parameter types: `{id}` (anything), `{id:int}` (digits), `{id:uuid}` (UUID), `{slug:slug}` (`[a-z0-9-]+`).
- Public routes: place before `RequireAuthPipe` group.

## Presenter

- `withData($array)`  - for collections (array of items). Do NOT pass a single associative array.
- `withSingleData($array)`  - for single records (associative array).
- `fromRecord($record)`  - for Record objects.
- `validationError($message, $errors)`  - 422 with field-level errors.
- `invariantError($message)`  - 422 with a single error message.
- `success($message)`  - success response with message.

## Database & Transactions

- `DbInterface` MUST be a singleton. Without it, each resolution creates a separate connection. Transactions spanning event chains will deadlock if listeners use different connections.
- `Db` uses lazy PDO  - constructor validates config, `connect()` creates PDO on first query.
- Atomic conditional UPDATE: `UPDATE table SET col = ? WHERE id = ? AND col IS NULL` + `affectedRows()` check. Eliminates SELECT-then-UPDATE race conditions.
- `array_unique()` with PDO positional parameters: preserves original keys, creating gaps. PDO requires sequential 0-indexed keys. Always: `array_values(array_unique(...))`.

## Migrations

- Named classes extending `BaseMigration`, NOT anonymous classes.
- Format: `Migration_{timestamp}_{DescriptiveName}`
- `up()` and `down()` methods.
- SQL as HEREDOC/NOWDOC.
- `composer run migrate` runs `up 1`. Run from `backend/` directory.
- `composer run migrate:rollback` (optional step count).
- No ENUM columns. Use `VARCHAR(N)` and validate with `'in|values'`.

```php
class Migration_20250826012000_AddSlugToThings extends BaseMigration
{
    public function up(): void
    {
        $this->db->execute(<<<'SQL'
            ALTER TABLE `things`
                ADD COLUMN `slug` VARCHAR(255) DEFAULT NULL AFTER `name`,
                ADD UNIQUE INDEX `idx_things_slug` (`slug`)
        SQL);
    }

    public function down(): void
    {
        $this->db->execute(<<<'SQL'
            ALTER TABLE `things`
                DROP INDEX `idx_things_slug`,
                DROP COLUMN `slug`
        SQL);
    }
}
```

## Validation

- Rule type names use short forms: `int` not `integer`, `bool` not `boolean`.
- `json` rule is in `RULES_WITHOUT_SANITIZATION`  - request body JSON fields arrive as PHP arrays (already decoded). Input constructors handle `json_encode()` themselves.
- Validator rule strings: `'required'`, `'string|trim,min:3,max:30'`, `'int|min:1'`, `'uuid'`, `'bool'`.

## CSRF/XSRF Protection

Token-in-header strategy: backend stores token in session, mirrors to `XSRF-TOKEN` cookie (JS-readable). Frontend reads cookie and sends `X-CSRF-Token` header.

- Token rotates after every successful POST/PATCH/DELETE.
- GET/HEAD/OPTIONS are exempt.
- 403 = bad token. Response includes fresh token.
- Auth routes use `EnsureCsrfTokenPipe` (issue only, no verification  - `VerifyOriginPipe` covers cross-origin).
- All other routes add `VerifyCsrfTokenPipe`.

## SQL Style

- Backtick every identifier: `` `t`.`column` ``
- Alias with `AS`: `` FROM `things` AS `t` ``
- Explicit join types: `INNER JOIN` not bare `JOIN`
- NOWDOC/HEREDOC for SQL blocks
- One clause per line, SQL keywords uppercase
- Joined table on the left of ON

## Bootstrap & Environment

- `APP_ENV` controls bootstrap behavior (defaults to `local`).
- `APP_ENV=simulation` → binds `NullDb` (throws on queries). All others → binds `Db`.
- `$container->singleton(Foo::class)` (no 2nd arg) eagerly resolves deps  - register DB-dependent singletons AFTER `Loader::load()`.

## Dependency Injection

- Constructor injection. Services auto-wired.
- `AuthContext`, `EventManager`, `LoggerInterface` are singletons.
- `DbInterface` bound to `Db::class` (must be singleton).
- Always use injectable services, never static methods.
