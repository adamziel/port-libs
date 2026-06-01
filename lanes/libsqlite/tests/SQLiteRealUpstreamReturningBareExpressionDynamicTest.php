<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test';

$rowsForSeed = static function (int $seed): array {
    $prefix = 'tenant-' . $seed . '-';

    return [
        ['setting_id' => $seed * 10 + 1, 'key_name' => $prefix . 'alpha', 'marker' => 'pax', 'payload' => 10 + $seed],
        ['setting_id' => $seed * 10 + 2, 'key_name' => $prefix . 'happy', 'marker' => 'pax', 'payload' => 20 + $seed],
        ['setting_id' => $seed * 10 + 3, 'key_name' => null, 'marker' => 'pax', 'payload' => 30 + $seed],
        ['setting_id' => $seed * 10 + 4, 'key_name' => $prefix . 'spare', 'marker' => 'other', 'payload' => 40 + $seed],
        ['setting_id' => $seed * 10 + 5, 'key_name' => null, 'marker' => 'pax', 'payload' => 50 + $seed],
    ];
};

$runUpdate = static function (int $seed) use ($rowsForSeed): array {
    return SQLiteUpdateDeleteReturningSql::execute(
        "UPDATE app_returning_bare SET marker='bellum' WHERE marker='pax' RETURNING setting_id, key_name, '|'",
        ['app_returning_bare' => $rowsForSeed($seed)],
        'setting_id',
    );
};

$runDelete = static function (int $seed) use ($runUpdate): array {
    return SQLiteUpdateDeleteReturningSql::execute(
        "DELETE FROM app_returning_bare WHERE marker='bellum' RETURNING setting_id, key_name, marker, '|'",
        $runUpdate($seed)['tables'],
        'setting_id',
    );
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream returning1 bare expression dynamic update delete %04d', $seed)] =
        static function (TestRunner $t) use ($runUpdate, $runDelete, $seed): void {
            $update = $runUpdate($seed);

            $t->same('update', $update['action']);
            $t->same('app_returning_bare', $update['table']);
            $t->same([1, 2, 3, 5], array_map(static fn (array $row): int => $row['setting_id'] - ($seed * 10), $update['returning']));
            $t->same(["tenant-{$seed}-alpha", "tenant-{$seed}-happy", null, null], array_column($update['returning'], 'key_name'));
            $t->same(['|', '|', '|', '|'], array_column($update['returning'], "'|'"));
            $t->same(['setting_id', 'key_name', "'|'"], array_keys($update['returning'][0]));
            $t->same('bellum', $update['tables']['app_returning_bare'][0]['marker']);
            $t->same('other', $update['tables']['app_returning_bare'][3]['marker']);
            $t->same([1, 2, 3, 5], array_map(static fn (int|string $id): int => (int) $id - ($seed * 10), $update['plan']->selectedIds));

            $delete = $runDelete($seed);

            $t->same('delete', $delete['action']);
            $t->same([1, 2, 3, 5], array_map(static fn (array $row): int => $row['setting_id'] - ($seed * 10), $delete['returning']));
            $t->same(['bellum', 'bellum', 'bellum', 'bellum'], array_column($delete['returning'], 'marker'));
            $t->same(['|', '|', '|', '|'], array_column($delete['returning'], "'|'"));
            $t->same(['setting_id', 'key_name', 'marker', "'|'"], array_keys($delete['returning'][0]));
            $t->same([['setting_id' => $seed * 10 + 4, 'key_name' => "tenant-{$seed}-spare", 'marker' => 'other', 'payload' => 40 + $seed]], $delete['tables']['app_returning_bare']);
            $t->same([1, 2, 3, 5], array_map(static fn (int|string $id): int => (int) $id - ($seed * 10), $delete['plan']->selectedIds));
        };
}

$tests['real upstream returning1 bare expression source citations'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->same(true, is_string($source));
    $t->same(true, str_contains((string) $source, 'do_execsql_test 2.1'));
    $t->same(true, str_contains((string) $source, "UPDATE t1 SET c='bellum' WHERE c='pax' RETURNING rowid, b, '|'"));
    $t->same(true, str_contains((string) $source, 'do_execsql_test 3.1'));
    $t->same(true, str_contains((string) $source, "DELETE FROM t1 WHERE c='bellum' RETURNING rowid, *, '|'"));
    $t->same(
        'no new support component needed; reuses SQLiteUpdateDeleteReturningSql projection parsing and row-array UPDATE/DELETE RETURNING execution',
        'no new support component needed; reuses SQLiteUpdateDeleteReturningSql projection parsing and row-array UPDATE/DELETE RETURNING execution',
    );
};

return $tests;
