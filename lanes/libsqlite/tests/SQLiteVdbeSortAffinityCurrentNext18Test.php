<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeSortCompare;

$tests = [];

$rows = [
    ['option_id' => 1, 'autoload' => 'yes', 'option_name' => 'plugin_10', 'priority' => '10', 'payload' => new SQLiteBlobValue('b')],
    ['option_id' => 2, 'autoload' => 'yes', 'option_name' => 'Plugin_2', 'priority' => '2', 'payload' => new SQLiteBlobValue('a')],
    ['option_id' => 3, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => null, 'payload' => 'z'],
    ['option_id' => 4, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => '1', 'payload' => 'y'],
    ['option_id' => 5, 'autoload' => null, 'option_name' => 'network', 'priority' => '3', 'payload' => 'x'],
    ['option_id' => 6, 'autoload' => 'YES', 'option_name' => 'plugin_2', 'priority' => '02', 'payload' => new SQLiteBlobValue('c')],
    ['option_id' => 7, 'autoload' => 'yes', 'option_name' => null, 'priority' => '2.0', 'payload' => 'w'],
    ['option_id' => 8, 'autoload' => 'yes', 'option_name' => 'plugin_02', 'priority' => new SQLiteBlobValue('2'), 'payload' => 'v'],
    ['option_id' => 9, 'autoload' => 'yes', 'option_name' => 'Plugin_2 ', 'priority' => '2x', 'payload' => 'u'],
];

$makeCursor = static fn () => SQLiteVdbeSortCompare::cursor(
    $rows,
    ['autoload', 'priority', 'option_name', 'option_id'],
    'GCGD',
    ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'],
    [false, false, false, false],
    ['LAST', 'LAST', 'LAST', null]
);

$tests['vdbe sort affinity current next18 starts on first affinity-sorted row'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();

    $t->same(0, $cursor->position());
    $t->same(4, $cursor->currentValue('option_id'));
};

$tests['vdbe sort affinity current next18 exposes current composite record'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();

    $t->same(['no', '1', 'cache ', 4], $cursor->currentRecord(['autoload', 'priority', 'option_name', 'option_id']));
};

$tests['vdbe sort affinity current next18 current value returns nullable column'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $cursor->next();

    $t->same(null, $cursor->currentValue('priority'));
};

$tests['vdbe sort affinity current next18 next row returns current before advancing'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $row = $cursor->nextRow();

    $t->same(4, $row['option_id']);
    $t->same(1, $cursor->position());
    $t->same(3, $cursor->currentValue('option_id'));
};

$tests['vdbe sort affinity current next18 scans expected row ids'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $seen = [];
    while (($row = $cursor->nextRow()) !== null) {
        $seen[] = $row['option_id'];
    }

    $t->same([4, 3, 2, 8, 6, 7, 1, 9, 5], $seen);
};

$tests['vdbe sort affinity current next18 uses later keys before input sequence'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $seen = [];
    while (($row = $cursor->nextRow()) !== null) {
        $priority = $row['priority'] instanceof SQLiteBlobValue ? $row['priority']->bytes : $row['priority'];
        if (($row['autoload'] ?? null) !== null && strtolower((string) $row['autoload']) === 'yes' && is_numeric($priority) && (float) $priority === 2.0) {
            $seen[] = $row['option_id'];
        }
    }

    $t->same([2, 8, 6, 7], $seen);
};

$tests['vdbe sort affinity current next18 keeps nulls last on first key'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $last = null;
    while (($row = $cursor->nextRow()) !== null) {
        $last = $row;
    }

    $t->same(5, $last['option_id']);
    $t->same(null, $last['autoload']);
};

$tests['vdbe sort affinity current next18 keeps nulls last on second key within group'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $cursor->next();

    $t->same(3, $cursor->currentValue('option_id'));
    $t->same(null, $cursor->currentValue('priority'));
};

$tests['vdbe sort affinity current next18 treats blob numeric key as numeric'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $priorities = [];
    while (($row = $cursor->nextRow()) !== null) {
        if (in_array($row['option_id'], [2, 6, 7, 8], true)) {
            $priorities[] = $row['priority'] instanceof SQLiteBlobValue ? $row['priority']->bytes : $row['priority'];
        }
    }

    $t->same(['2', '2', '02', '2.0'], $priorities);
};

$tests['vdbe sort affinity current next18 applies rtrim tie before option id'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $pluginTwos = [];
    while (($row = $cursor->nextRow()) !== null) {
        if (in_array($row['option_id'], [2, 6], true)) {
            $pluginTwos[] = $row['option_id'];
        }
    }

    $t->same([2, 6], $pluginTwos);
};

$tests['vdbe sort affinity current next18 malformed numeric text sorts after numeric values'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $beforeMalformed = [];
    while (($row = $cursor->nextRow()) !== null) {
        if ($row['option_id'] === 9) {
            break;
        }
        if (($row['autoload'] ?? null) !== null && strtolower((string) $row['autoload']) === 'yes') {
            $beforeMalformed[] = $row['option_id'];
        }
    }

    $t->same([2, 8, 6, 7, 1], $beforeMalformed);
};

$tests['vdbe sort affinity current next18 current record follows next movement'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $cursor->next();
    $cursor->next();

    $t->same(['yes', '2', 'Plugin_2', 2], $cursor->currentRecord(['autoload', 'priority', 'option_name', 'option_id']));
};

$tests['vdbe sort affinity current next18 remaining rows start from current position'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $cursor->next();
    $cursor->next();
    $remaining = $cursor->remainingRows();

    $t->same([2, 8, 6, 7, 1, 9, 5], array_column($remaining, 'option_id'));
};

$tests['vdbe sort affinity current next18 current record returns null at eof'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    for ($i = 0; $i < 12; $i++) {
        $cursor->next();
    }

    $t->same(null, $cursor->currentRecord(['option_id']));
};

$tests['vdbe sort affinity current next18 current value returns null at eof'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    for ($i = 0; $i < 12; $i++) {
        $cursor->next();
    }

    $t->same(null, $cursor->currentValue('option_id'));
};

$tests['vdbe sort affinity current next18 next row returns null at eof'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    for ($i = 0; $i < 9; $i++) {
        $cursor->nextRow();
    }

    $t->same(null, $cursor->nextRow());
    $t->same(9, $cursor->position());
};

$tests['vdbe sort affinity current next18 next remains pinned at eof'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    for ($i = 0; $i < 14; $i++) {
        $cursor->next();
    }

    $t->same(9, $cursor->position());
    $t->true($cursor->eof());
};

$tests['vdbe sort affinity current next18 rejects empty current record column list'] = static function (TestRunner $t) use ($makeCursor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCursor()->currentRecord([]));
};

$tests['vdbe sort affinity current next18 rejects associative current record column list'] = static function (TestRunner $t) use ($makeCursor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCursor()->currentRecord(['name' => 'option_name']));
};

$tests['vdbe sort affinity current next18 rejects missing current record column'] = static function (TestRunner $t) use ($makeCursor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCursor()->currentRecord(['missing']));
};

$tests['vdbe sort affinity current next18 rejects missing current value column'] = static function (TestRunner $t) use ($makeCursor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $makeCursor()->currentValue('missing'));
};

$tests['vdbe sort affinity current next18 descending scan exposes current row'] = static function (TestRunner $t) use ($rows): void {
    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['priority', 'option_id'], 'CD', ['BINARY', 'BINARY'], [true, false], ['LAST', null]);

    $t->same(9, $cursor->currentValue('option_id'));
};

$tests['vdbe sort affinity current next18 descending nulls first scan exposes numeric suffix'] = static function (TestRunner $t) use ($rows): void {
    $cursor = SQLiteVdbeSortCompare::cursor($rows, ['priority', 'option_id'], 'CD', ['BINARY', 'BINARY'], [true, false], ['FIRST', null]);
    $cursor->next();

    $t->same(9, $cursor->currentValue('option_id'));
};

$tests['vdbe sort affinity current next18 blob payload survives current row'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $cursor->next();
    $cursor->next();

    $payload = $cursor->currentValue('payload');
    $t->true($payload instanceof SQLiteBlobValue);
    $t->same('a', $payload->bytes);
};

$tests['vdbe sort affinity current next18 current record can project non-sort columns'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $cursor->next();
    $cursor->next();

    $record = $cursor->currentRecord(['option_id', 'payload']);
    $t->same(2, $record[0]);
    $t->true($record[1] instanceof SQLiteBlobValue);
};

$tests['vdbe sort affinity current next18 sortRows and cursor agree'] = static function (TestRunner $t) use ($rows, $makeCursor): void {
    $ordered = SQLiteVdbeSortCompare::sortRows($rows, ['autoload', 'priority', 'option_name', 'option_id'], 'GCGD', ['NOCASE', 'BINARY', 'RTRIM', 'BINARY'], [false, false, false, false], ['LAST', 'LAST', 'LAST', null]);
    $cursor = $makeCursor();
    $seen = [];
    while (($row = $cursor->nextRow()) !== null) {
        $seen[] = $row['option_id'];
    }

    $t->same(array_column($ordered, 'option_id'), $seen);
};

$tests['vdbe sort affinity current next18 current can be read repeatedly'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();

    $t->same(4, $cursor->currentValue('option_id'));
    $t->same(4, $cursor->currentValue('option_id'));
    $t->same(0, $cursor->position());
};

$tests['vdbe sort affinity current next18 next row after repeated current advances once'] = static function (TestRunner $t) use ($makeCursor): void {
    $cursor = $makeCursor();
    $cursor->currentValue('option_id');
    $row = $cursor->nextRow();

    $t->same(4, $row['option_id']);
    $t->same(1, $cursor->position());
};

$tests['vdbe sort affinity current next18 empty cursor is immediately eof'] = static function (TestRunner $t): void {
    $cursor = SQLiteVdbeSortCompare::cursor([], ['option_id'], 'D');

    $t->true($cursor->eof());
    $t->same(null, $cursor->nextRow());
};

$tests['vdbe sort affinity current next18 single row cursor yields one row'] = static function (TestRunner $t): void {
    $cursor = SQLiteVdbeSortCompare::cursor([['option_id' => 11, 'priority' => '02']], ['priority'], 'C');

    $t->same(11, $cursor->currentValue('option_id'));
    $t->same(11, $cursor->nextRow()['option_id']);
    $t->same(null, $cursor->nextRow());
};

return $tests;
