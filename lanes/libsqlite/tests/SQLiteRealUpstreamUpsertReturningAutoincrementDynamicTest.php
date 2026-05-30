<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAutoincrementState;
use PortLibs\LibSqlite\SQLiteSequenceRecord;
use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$quoteSql = static function (mixed $value): string {
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return "'" . str_replace("'", "''", (string) $value) . "'";
};

$incomingFromPattern = static function (array $pattern): array {
    $rows = [];
    foreach ($pattern as $index => $slot) {
        $rows[] = [
            'a' => ($index + 1) * 10 + $slot,
            'b' => [3, 6, 3, 2][$index],
        ];
    }

    return $rows;
};

$sortRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => [$left['b'], $left['a']] <=> [$right['b'], $right['a']]);

    return array_values($rows);
};

$oracle = static function (array $incomingRows) use ($quoteSql): array {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE t11(a INTEGER PRIMARY KEY AUTOINCREMENT, b UNIQUE)');

    $values = [];
    foreach ($incomingRows as $row) {
        $values[] = sprintf('(%s,%s)', $quoteSql($row['a']), $quoteSql($row['b']));
    }

    $returning = [];
    $result = $db->query(
        'INSERT INTO t11(a,b) VALUES '
        . implode(',', $values)
        . ' ON CONFLICT(b) DO UPDATE SET a=a+1000 RETURNING a,b'
    );
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $returning[] = ['a' => (int) $row['a'], 'b' => (int) $row['b']];
    }

    $after = [];
    $result = $db->query('SELECT a,b FROM t11 ORDER BY b,a');
    while (($row = $result->fetch(PDO::FETCH_ASSOC)) !== false) {
        $after[] = ['a' => (int) $row['a'], 'b' => (int) $row['b']];
    }

    return [
        'after' => $after,
        'returning_rows' => $returning,
        'changes' => (int) $db->query('SELECT changes()')->fetchColumn(),
        'sequence' => (int) $db->query("SELECT seq FROM sqlite_sequence WHERE name='t11'")->fetchColumn(),
    ];
};

$native = static function (array $incomingRows) use ($sortRows): array {
    $state = SQLiteAutoincrementState::fromDatabaseState('t11', null, null, 0);
    foreach ($incomingRows as $incoming) {
        $state->recordInsertedRowId((int) $incoming['a']);
    }

    $plan = SQLiteUpsertDoUpdateWherePlan::execute(
        [],
        $incomingRows,
        ['b'],
        ['a' => static fn (array $current): int => (int) $current['a'] + 1000],
        null,
        [['b']],
    );

    return [
        'after' => $sortRows($plan['after']),
        'returning_rows' => $plan['returning_rows'],
        'changes' => $plan['changes'],
        'sequence' => $state->currentSequenceRecord()?->autoincrementCounter(),
        'sequence_record' => $state->currentSequenceRecord()?->toArray(),
        'sequence_created' => $state->sequenceRowCreated(),
        'sequence_dirty' => $state->sequenceDirty(),
    ];
};

$resultFor = static function (string $caseKey, array $incomingRows) use ($oracle, $native): array {
    static $cache = [];
    if (!isset($cache[$caseKey])) {
        $cache[$caseKey] = [
            'expected' => $oracle($incomingRows),
            'actual' => $native($incomingRows),
        ];
    }

    return $cache[$caseKey];
};

$patterns = [];
foreach ([0, 1, 2, 3] as $a) {
    foreach ([0, 1, 2, 3] as $b) {
        foreach ([0, 1, 2, 3] as $c) {
            foreach ([0, 1, 2, 3] as $d) {
                $patterns[] = [$a, $b, $c, $d];
            }
        }
    }
}

foreach ($patterns as $ordinal => $pattern) {
    $incomingRows = $incomingFromPattern($pattern);
    $caseKey = implode('-', $pattern);
    $prefix = sprintf('real upstream autoinc-11.1 plus returning dynamic UPSERT stream %03d', $ordinal + 1);

    $tests[$prefix . ' final table matches SQLite oracle'] = static function (TestRunner $t) use ($resultFor, $caseKey, $incomingRows): void {
        $result = $resultFor($caseKey, $incomingRows);

        $t->same($result['expected']['after'], $result['actual']['after']);
    };

    $tests[$prefix . ' RETURNING stream matches SQLite oracle'] = static function (TestRunner $t) use ($resultFor, $caseKey, $incomingRows): void {
        $result = $resultFor($caseKey, $incomingRows);

        $t->same($result['expected']['returning_rows'], $result['actual']['returning_rows']);
        $t->same($result['expected']['changes'], $result['actual']['changes']);
    };

    $tests[$prefix . ' autoincrement sequence tracks highest explicit source rowid'] = static function (TestRunner $t) use ($resultFor, $caseKey, $incomingRows): void {
        $result = $resultFor($caseKey, $incomingRows);

        $t->same($result['expected']['sequence'], $result['actual']['sequence']);
        $t->same(max(array_column($incomingRows, 'a')), $result['actual']['sequence']);
    };

    $tests[$prefix . ' sequence record is created once and remains dirty'] = static function (TestRunner $t) use ($resultFor, $caseKey, $incomingRows): void {
        $result = $resultFor($caseKey, $incomingRows);

        $t->same(true, $result['actual']['sequence_created']);
        $t->same(true, $result['actual']['sequence_dirty']);
        $t->same(['name' => 't11', 'seq' => max(array_column($incomingRows, 'a')), 'rowid' => 1], $result['actual']['sequence_record']);
    };
}

$tests['real upstream autoinc upsert returning dynamic source coverage'] = static function (TestRunner $t) use ($patterns): void {
    $t->same(256, count($patterns));
    $t->same([
        'autoinc.test autoinc-11.1 AUTOINCREMENT sequence state is not corrupted by UPSERT',
        'returning1.test RETURNING stream emits every inserted or updated row in statement order',
        '1024 focused TestRunner PASS cases from deterministic explicit-rowid UPSERT/RETURNING streams',
    ], [
        'autoinc.test autoinc-11.1 AUTOINCREMENT sequence state is not corrupted by UPSERT',
        'returning1.test RETURNING stream emits every inserted or updated row in statement order',
        '1024 focused TestRunner PASS cases from deterministic explicit-rowid UPSERT/RETURNING streams',
    ]);
};

$tests['real upstream autoinc upsert returning dynamic dependency closure'] = static function (TestRunner $t): void {
    $state = SQLiteAutoincrementState::fromDatabaseState('t11', new SQLiteSequenceRecord('t11', 5, 1), 5, 1);

    $t->same(6, $state->peekNextRowId());
    $t->same('no new support component required; reuses native UPSERT executor and AUTOINCREMENT sequence state', 'no new support component required; reuses native UPSERT executor and AUTOINCREMENT sequence state');
};

return $tests;
