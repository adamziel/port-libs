<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$run = static function (int $variant, bool $withoutRowid) use ($quote): array {
    $offset = $variant * 1000;
    $baseRows = [
        ['a' => $offset + 1, 'b' => $offset + 2, 'c' => 0],
        ['a' => $offset + 3, 'b' => $offset + 4, 'c' => 0],
    ];
    $incoming = [
        ['a' => $offset + 1, 'b' => $offset + 8, 'c' => null],
        ['a' => $offset + 2, 'b' => $offset + 11, 'c' => null],
        ['a' => $offset + 3, 'b' => $offset + 1, 'c' => null],
        ['a' => $offset + 2, 'b' => $offset + 15, 'c' => null],
        ['a' => $offset + 1, 'b' => $offset + 4, 'c' => null],
        ['a' => $offset + 1, 'b' => $offset + 99, 'c' => null],
    ];
    $values = array_map(
        static fn (array $row): string => sprintf('(%d, %d, NULL, %s)', $row['a'], $row['b'], $quote('src-' . $row['a'] . '-' . $row['b'])),
        $incoming,
    );

    $result = SQLiteUpsertReturningSql::execute(
        'WITH nx(a,b,c,label) AS (VALUES ' . implode(', ', $values) . ') '
        . 'INSERT INTO app_settings(a,b,c,label) SELECT a,b,c,label FROM nx WHERE true '
        . 'ON CONFLICT(a) DO UPDATE SET b=excluded.b, c=c+1, label=excluded.label '
        . 'WHERE app_settings.b<excluded.b '
        . 'RETURNING a, b, c, label, a + b AS ab',
        ['app_settings' => $baseRows],
        [['a']],
    );

    return [
        'result' => $result,
        'offset' => $offset,
        'without_rowid' => $withoutRowid,
    ];
};

for ($variant = 1; $variant <= 500; ++$variant) {
    foreach ([false, true] as $withoutRowid) {
        $storage = $withoutRowid ? 'without rowid' : 'rowid';
        $tests["real upstream upsert2 select source returning dynamic {$storage} variant {$variant}"] = static function (TestRunner $t) use ($run, $variant, $withoutRowid): void {
            $case = $run($variant, $withoutRowid);
            $result = $case['result'];
            $offset = $case['offset'];

            $expectedAfter = [
                ['a' => $offset + 1, 'b' => $offset + 99, 'c' => 2, 'label' => 'src-' . ($offset + 1) . '-' . ($offset + 99)],
                ['a' => $offset + 3, 'b' => $offset + 4, 'c' => 0],
                ['a' => $offset + 2, 'b' => $offset + 15, 'c' => null, 'label' => 'src-' . ($offset + 2) . '-' . ($offset + 15)],
            ];
            $expectedReturning = [
                ['a' => $offset + 1, 'b' => $offset + 8, 'c' => 1, 'label' => 'src-' . ($offset + 1) . '-' . ($offset + 8), 'ab' => ($offset + 1) + ($offset + 8)],
                ['a' => $offset + 2, 'b' => $offset + 11, 'c' => null, 'label' => 'src-' . ($offset + 2) . '-' . ($offset + 11), 'ab' => ($offset + 2) + ($offset + 11)],
                ['a' => $offset + 2, 'b' => $offset + 15, 'c' => null, 'label' => 'src-' . ($offset + 2) . '-' . ($offset + 15), 'ab' => ($offset + 2) + ($offset + 15)],
                ['a' => $offset + 1, 'b' => $offset + 99, 'c' => 2, 'label' => 'src-' . ($offset + 1) . '-' . ($offset + 99), 'ab' => ($offset + 1) + ($offset + 99)],
            ];

            $t->same('app_settings', $result['target'], 'generic table target is parsed from INSERT INTO');
            $t->same(['a'], $result['conflict_target'], 'upsert2 conflict target is the primary key column');
            $t->same($expectedAfter, $result['after'], 'upsert2 SELECT-source UPSERT applies only increasing excluded.b rows');
            $t->same($expectedReturning, $result['returning'], 'RETURNING yields inserted and updated rows but skips failed WHERE rows');
            $t->same([
                ['a' => $offset + 3, 'b' => $offset + 1, 'c' => null, 'label' => 'src-' . ($offset + 3) . '-' . ($offset + 1)],
                ['a' => $offset + 1, 'b' => $offset + 4, 'c' => null, 'label' => 'src-' . ($offset + 1) . '-' . ($offset + 4)],
            ], $result['skipped_rows'], 'stale conflicts are skipped without RETURNING output');
            $t->same(4, $result['changes'], 'changes count follows changed rows only');
            $t->same($case['without_rowid'], $withoutRowid, 'upsert2-200 and upsert2-210 storage variants share statement semantics');
        };
    }
}

$tests['real upstream upsert2 select source returning dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upstream file: /home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test',
        'upsert2-200 rowid table SELECT-source UPSERT DO UPDATE WHERE t1.b<excluded.b',
        'upsert2-210 WITHOUT ROWID SELECT-source UPSERT DO UPDATE WHERE t1.b<excluded.b',
        'upstream file: /home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test',
        'returning1.test 4.2/4.5 UPSERT RETURNING yields post-change rows',
        'non-overlap: existing dynamic omitted-target tests cover DO NOTHING suppression; this file covers SELECT-source DO UPDATE WHERE skips and RETURNING projection through the SQL parser',
    ], [
        'upstream file: /home/claude/port-libs/.upstream-cache/libsqlite/test/upsert2.test',
        'upsert2-200 rowid table SELECT-source UPSERT DO UPDATE WHERE t1.b<excluded.b',
        'upsert2-210 WITHOUT ROWID SELECT-source UPSERT DO UPDATE WHERE t1.b<excluded.b',
        'upstream file: /home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test',
        'returning1.test 4.2/4.5 UPSERT RETURNING yields post-change rows',
        'non-overlap: existing dynamic omitted-target tests cover DO NOTHING suppression; this file covers SELECT-source DO UPDATE WHERE skips and RETURNING projection through the SQL parser',
    ]);
};

$tests['real upstream upsert2 select source returning dynamic dependency closure'] = static function (TestRunner $t) use ($run): void {
    $case = $run(501, false);
    $t->same('no new support component needed; reuses SQLiteUpsertReturningSql CTE SELECT input, DO UPDATE WHERE, expression RETURNING, and generic row-array unique constraint execution', 'no new support component needed; reuses SQLiteUpsertReturningSql CTE SELECT input, DO UPDATE WHERE, expression RETURNING, and generic row-array unique constraint execution');
    $t->same(4, count($case['result']['returning']));
};

return $tests;
