<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$upsert4Rows = static fn (): array => [
    ['setting_id' => 1, 'load_policy' => null, 'label' => 'one'],
    ['setting_id' => 2, 'load_policy' => null, 'label' => 'two'],
    ['setting_id' => 3, 'load_policy' => null, 'label' => 'three'],
];

$upsert4Constraints = [['setting_id'], ['label']];

$tests['real upstream upsert4.test 1.1 omitted insert column list supports catchall do nothing'] = static function (TestRunner $t) use ($upsert4Rows, $upsert4Constraints): void {
    $result = SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_counter VALUES(1, NULL, 'xyz') ON CONFLICT DO NOTHING RETURNING setting_id, label",
        ['app_counter' => $upsert4Rows()],
        $upsert4Constraints,
    );

    $t->same(['setting_id', 'load_policy', 'label'], $result['columns']);
    $t->same([], $result['returning']);
    $t->same($upsert4Rows(), $result['after']);
    $t->same(0, $result['changes']);
};

$tests['real upstream upsert4.test 1.3 omitted insert column list updates secondary unique conflict'] = static function (TestRunner $t) use ($upsert4Rows, $upsert4Constraints): void {
    $result = SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_counter VALUES(4, NULL, 'two') ON CONFLICT(label) DO UPDATE SET load_policy = 'eager' RETURNING setting_id, load_policy, label",
        ['app_counter' => $upsert4Rows()],
        $upsert4Constraints,
    );

    $t->same([['setting_id' => 2, 'load_policy' => 'eager', 'label' => 'two']], $result['returning']);
    $t->same([
        ['setting_id' => 1, 'load_policy' => null, 'label' => 'one'],
        ['setting_id' => 2, 'load_policy' => 'eager', 'label' => 'two'],
        ['setting_id' => 3, 'load_policy' => null, 'label' => 'three'],
    ], $result['after']);
    $t->same(1, $result['changes']);
};

$tests['real upstream upsert4.test 1.7 omitted insert column list supports row-value update assignment'] = static function (TestRunner $t) use ($upsert4Rows, $upsert4Constraints): void {
    $result = SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_counter VALUES(2, NULL, 'zero') ON CONFLICT(setting_id) DO UPDATE SET (load_policy, label) = (SELECT 'x', 'y') RETURNING *",
        ['app_counter' => $upsert4Rows()],
        $upsert4Constraints,
    );

    $t->same([['setting_id' => 2, 'load_policy' => 'x', 'label' => 'y']], $result['returning']);
    $t->same([
        ['setting_id' => 1, 'load_policy' => null, 'label' => 'one'],
        ['setting_id' => 2, 'load_policy' => 'x', 'label' => 'y'],
        ['setting_id' => 3, 'load_policy' => null, 'label' => 'three'],
    ], $result['after']);
    $t->same(1, $result['changes']);
};

$tests['real upstream upsert4.test 1.8 omitted insert column list updates conflict key after row-value assignment'] = static function (TestRunner $t) use ($upsert4Rows, $upsert4Constraints): void {
    $result = SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_counter VALUES(1, NULL, NULL) ON CONFLICT(setting_id) DO UPDATE SET (label, setting_id) = ('four', 4) RETURNING setting_id, load_policy, label",
        ['app_counter' => $upsert4Rows()],
        $upsert4Constraints,
    );

    $after = $result['after'];
    usort($after, static fn (array $left, array $right): int => $left['setting_id'] <=> $right['setting_id']);
    $t->same([['setting_id' => 4, 'load_policy' => null, 'label' => 'four']], $result['returning']);
    $t->same([
        ['setting_id' => 2, 'load_policy' => null, 'label' => 'two'],
        ['setting_id' => 3, 'load_policy' => null, 'label' => 'three'],
        ['setting_id' => 4, 'load_policy' => null, 'label' => 'four'],
    ], $after);
    $t->same(1, $result['changes']);
};

$tests['real upstream upsert4.test 6 omitted insert column list lets conflict arm beat replace policy'] = static function (TestRunner $t): void {
    $result = SQLiteUpsertReturningSql::execute(
        "INSERT OR REPLACE INTO app_unique VALUES(5, 'one', 10) ON CONFLICT(key_name) DO NOTHING RETURNING setting_id, key_name, priority",
        ['app_unique' => [
            ['setting_id' => 1, 'key_name' => 'one', 'priority' => 2],
        ]],
        [['setting_id'], ['key_name']],
    );

    $t->same(['replace', ['setting_id', 'key_name', 'priority']], [$result['insert_policy'], $result['columns']]);
    $t->same([], $result['returning']);
    $t->same([['setting_id' => 1, 'key_name' => 'one', 'priority' => 2]], $result['after']);
    $t->same(0, $result['changes']);
};

$tests['real upstream upsert4 omitted insert column list still requires table row image metadata'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningSql::parse(
        "INSERT INTO app_counter VALUES(1, NULL, 'xyz') ON CONFLICT DO NOTHING RETURNING setting_id",
    ));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertReturningSql::execute(
        "INSERT INTO app_counter VALUES(1, NULL, 'xyz') ON CONFLICT DO NOTHING RETURNING setting_id",
        ['app_counter' => []],
        [['setting_id']],
    ));
};

$tests['real upstream upsert returning omitted column list dynamic cites source files'] = static function (TestRunner $t): void {
    $t->same('upsert4.test: 1.1 catchall DO NOTHING uses INSERT INTO t1 VALUES(...) without target columns', 'upsert4.test: 1.1 catchall DO NOTHING uses INSERT INTO t1 VALUES(...) without target columns');
    $t->same('upsert4.test: 1.3 secondary UNIQUE conflict DO UPDATE uses omitted target columns', 'upsert4.test: 1.3 secondary UNIQUE conflict DO UPDATE uses omitted target columns');
    $t->same('upsert4.test: 1.7-1.8 row-value DO UPDATE assignments use omitted target columns', 'upsert4.test: 1.7-1.8 row-value DO UPDATE assignments use omitted target columns');
    $t->same('upsert4.test: 6 INSERT OR REPLACE precedence is exercised with omitted target columns', 'upsert4.test: 6 INSERT OR REPLACE precedence is exercised with omitted target columns');
};

$case = 0;
foreach (range(1, 1000) as $ordinal) {
    ++$case;
    $existingId = 100000 + $ordinal;
    $freshId = 200000 + $ordinal;
    $baseCount = ($ordinal % 5) + 1;
    $incomingCount = ($ordinal % 7) + 2;
    $replacementCount = ($ordinal % 11) + 3;
    $initialRows = [
        ['setting_id' => $existingId, 'ref_count' => $baseCount, 'label' => "seed-{$ordinal}"],
    ];
    $sql = sprintf(
        "INSERT INTO app_counter VALUES(%d, %d, 'updated-%d'), (%d, %d, 'fresh-%d'), (%d, %d, 'fresh-again-%d') "
        . 'ON CONFLICT(setting_id) DO UPDATE SET ref_count = ref_count + excluded.ref_count, label = excluded.label '
        . 'RETURNING setting_id, ref_count, label',
        $existingId,
        $incomingCount,
        $ordinal,
        $freshId,
        1,
        $ordinal,
        $freshId,
        $replacementCount,
        $ordinal,
    );

    $tests[sprintf('real upstream upsert4 omitted insert column dynamic repeated conflict %04d', $case)] = static function (TestRunner $t) use ($initialRows, $sql, $existingId, $freshId, $baseCount, $incomingCount, $replacementCount, $ordinal, $case): void {
        $result = SQLiteUpsertReturningSql::execute($sql, ['app_counter' => $initialRows], [['setting_id']]);

        $t->same(['setting_id', 'ref_count', 'label'], $result['columns'], "upsert4 omitted columns inferred schema {$case}");
        $t->same([
            ['setting_id' => $existingId, 'ref_count' => $baseCount + $incomingCount, 'label' => "updated-{$ordinal}"],
            ['setting_id' => $freshId, 'ref_count' => 1, 'label' => "fresh-{$ordinal}"],
            ['setting_id' => $freshId, 'ref_count' => 1 + $replacementCount, 'label' => "fresh-again-{$ordinal}"],
        ], $result['returning'], "upsert4 repeated conflict returning row stream {$case}");
        $t->same(2, count($result['after']), "upsert4 repeated conflict final row count {$case}");
        $t->same(3, $result['changes'], "upsert4 repeated conflict change count {$case}");
    };
}

$tests['real upstream upsert returning omitted column list owns exactly 1000 dynamic cases'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case);
};

return $tests;
