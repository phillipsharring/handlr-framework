<?php


declare(strict_types=1);

use Handlr\Database\Record;

it('generates UUID when usesUuid is true and no id is provided', function () {
    $r = new TestRecord(['name' => 'phil']);

    expect($r->id)->not->toBeNull();
    expect($r->id)->toBeString();
});

it('leaves id null for non-UUID records when no id is provided', function () {
    $r = new NonUuidRecord(['foo' => 'bar']);

    expect($r->id)->toBeNull();
});

it('extracts id from constructor data', function () {
    $r = new TestRecord(['id' => 'abc', 'name' => 'phil']);

    expect($r->id)->toBe('abc');
    expect($r->toArray())->toHaveKey('id', 'abc');
});

it('hydrates known properties onto the object', function () {
    $r = new TestRecord(['name' => 'phil']);

    expect($r->name)->toBe('phil');
});

it('stores unknown properties in data array', function () {
    $r = new TestRecord(['foo' => 'bar']);

    expect($r->foo)->toBe('bar');
});

it('supports magic set and get', function () {
    $r = new TestRecord();

    $r->color = 'blue';

    expect($r->color)->toBe('blue');
});

it('casts values on read according to casts map', function () {
    $r = new TestRecord([
        'age' => '42',
        'active' => '1',
    ]);

    expect($r->age)->toBeInt()->toBe(42);
    expect($r->active)->toBeBool()->toBeTrue();
});

it('toArray returns id and data', function () {
    $r = new TestRecord(['name' => 'phil']);

    $arr = $r->toArray();

    expect($arr)->toHaveKeys(['id', 'name']);
});

it('jsonSerialize returns same output as toArray', function () {
    $r = new TestRecord(['name' => 'phil']);

    expect($r->jsonSerialize())->toBe($r->toArray());
});

class TestRecord extends Record
{
    protected bool $useUuid = true;

    protected array $casts = [
        'age' => 'int',
        'active' => 'bool',
    ];

    public string $name;
}

class NonUuidRecord extends Record
{
    protected bool $useUuid = false;
}
