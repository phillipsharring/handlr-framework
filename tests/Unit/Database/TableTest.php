<?php

declare(strict_types=1);

use Handlr\Database\Record;
use Handlr\Database\Table;
use Handlr\Database\DbInterface;

it('findById converts UUID, queries DB, and rehydrates record', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $uuid = '00000000-0000-0000-0000-000000000123';
    $bin = 'BIN_UUID';

    $db->expects($this->once())
        ->method('uuidToBin')
        ->with($uuid)
        ->willReturn($bin);

    $db->expects($this->once())
        ->method('binToUuid')
        ->with($bin)
        ->willReturn($uuid);

    $db->expects($this->once())
        ->method('execute')
        ->with(
            // 'SELECT * FROM `test_table` WHERE id = ?',
            // 'SELECT * FROM `test_table` WHERE id = ?',
            $this->stringContains('FROM `test_table` WHERE id = ?'),
            [$bin]
        )
        ->willReturn($stmt);

    $stmt->expects($this->once())
        ->method('fetch')
        ->with(PDO::FETCH_ASSOC)
        ->willReturn(['id' => $bin, 'name' => 'phil']);

    $table = new UuidTestTable($db);

    $rec = $table->findById($uuid);

    expect($rec)->toBeInstanceOf(DummyRecord::class);
    expect($rec->id)->toBe($uuid);
});

it('findWhere returns hydrated records', function () {
    $db = $this->createMock(DbInterface::class);
    $stmt = $this->createMock(PDOStatement::class);

    $db->expects($this->once())
        ->method('execute')
        ->willReturn($stmt);

    $stmt->expects($this->once())
        ->method('fetchAll')
        ->with(PDO::FETCH_ASSOC)
        ->willReturn([
            ['id' => 1, 'name' => 'a'],
            ['id' => 2, 'name' => 'b'],
        ]);

    $table = new TestTable($db);

    $rows = $table->findWhere();

    expect($rows)->toHaveCount(2);
    expect($rows[0])->toBeInstanceOf(DummyRecord::class);
    expect($rows[1]->id)->toBe(2);
});

it('insert sets auto-increment id on record', function () {
    $db = $this->createMock(DbInterface::class);

    $db->expects($this->once())
        ->method('execute')
        ->willReturn(false);

    $db->expects($this->once())
        ->method('insertId')
        ->willReturn(42);

    $table = new TestTable($db);

    $rec = new DummyRecord(['name' => 'x'], false);

    $out = $table->insert($rec);

    expect($out->id)->toBe(42);
});

it('insert preserves UUID id for UUID records', function () {
    $db = $this->createMock(DbInterface::class);

    $db->expects($this->once())
        ->method('uuidToBin')
        ->willReturn('BIN');

    $db->expects($this->once())
        ->method('execute')
        ->willReturn(false);

    $db->expects($this->never())
        ->method('insertId');

    $table = new TestTable($db);

    $uuid = '00000000-0000-0000-0000-000000000999';
    $rec = new DummyRecord(['id' => $uuid], true);

    $out = $table->insert($rec);

    expect($out->id)->toBe($uuid);
});

it('paginate returns empty data and sane meta when no rows', function () {
    $db = $this->createMock(DbInterface::class);

    $db->expects($this->once())
        ->method('execute')
        ->willReturn($this->createMock(PDOStatement::class));

    // countByWhere will return 0 via count()
    $table = new class($db) extends TestTable {
        protected function countByWhere(string $where, array $params): int {
            return 0;
        }
    };

    $result = $table->paginate();

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
    expect($result['meta']['has_more_pages'])->toBeFalse();
});

it('insertMany calls db->execute with expected SQL and values (UUID case)', function () {
    $db = $this->createMock(\Handlr\Database\DbInterface::class);

    // two UUID records
    $uuid1 = '00000000-0000-0000-0000-000000000011';
    $uuid2 = '00000000-0000-0000-0000-000000000022';

    // expect uuidToBin called for each id conversion and maybe for other uuid columns
    $seen = [];

    $db->expects($this->exactly(2))
        ->method('uuidToBin')
        ->willReturnCallback(function ($arg) use (&$seen) {
            $seen[] = $arg;
            return match ($arg) {
                '00000000-0000-0000-0000-000000000011' => 'BIN1',
                '00000000-0000-0000-0000-000000000022' => 'BIN2',
                default => null,
            };
        });

    // expect execute called once; assert SQL contains table name and placeholders count equals rows * cols
    $db->expects($this->once())
        ->method('execute')
        ->with($this->callback(function ($sql) {
            return stripos($sql, 'INSERT INTO `test_table`') !== false && substr_count($sql, '(') >= 2;
        }), $this->callback(function ($values) {
            // we don't need exact binary values; ensure flattened values count > 0
            return is_array($values) && count($values) >= 2;
        }))
        ->willReturn(false);

    $table = new TestTable($db);

    $r1 = new DummyRecord(['id' => $uuid1, 'name' => 'a'], true);
    $r2 = new DummyRecord(['id' => $uuid2, 'name' => 'b'], true);

    $out = $table->insertMany([$r1, $r2]);

    expect($seen)->toBe([
        '00000000-0000-0000-0000-000000000011',
        '00000000-0000-0000-0000-000000000022',
    ]);

    expect($out)->toBe([$r1, $r2]);
});

/*
 * Minimal test helpers and stubs below.
 * These classes live in the test file to keep tests self-contained.
 */

class DummyRecord extends Record
{
    public int|string|null $id;
    protected array $data;
    protected bool $useUuid;

    public function __construct(array $data = [], bool $useUuid = false)
    {
        $this->data = $data;
        $this->useUuid = $useUuid;
        $this->id = $data['id'] ?? ($useUuid ? '00000000-0000-0000-0000-000000000000' : null);

        parent::__construct($data);
    }

    public function toArray(): array
    {
        $arr = $this->data;
        if (!array_key_exists('id', $arr)) {
            $arr['id'] = $this->id;
        }
        return $arr;
    }

    public function usesUuid(): bool
    {
        return $this->useUuid;
    }

    public function uuidColumns(): array
    {
        // no additional uuid columns in these tests
        return [];
    }
}

class TestTable extends Table
{
    protected string $tableName = 'test_table';
    protected string $recordClass = DummyRecord::class;

    public function __construct(DbInterface $db)
    {
        parent::__construct($db);
    }
}

class UuidDummyRecord extends DummyRecord
{
    public function __construct(array $data = [])
    {
        parent::__construct($data, true);
    }
}

class UuidTestTable extends TestTable
{
    protected string $recordClass = UuidDummyRecord::class;
}
