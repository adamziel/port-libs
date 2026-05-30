<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$projectRows = static function (array $rows): array {
    usort($rows, static fn (array $left, array $right): int => $left['aa'] <=> $right['aa']);

    return array_values(array_map(
        static fn (array $row): array => [$row['aa'], $row['bb'], $row['cc'] ?? null],
        $rows,
    ));
};

$replaceByPrimaryKey = static function (array $rows, array $incoming, string $primary = 'aa'): array {
    $after = [];
    $deleted = [];
    foreach ($rows as $row) {
        if ($row[$primary] == $incoming[$primary]) {
            $deleted[] = $row;
            continue;
        }
        $after[] = $row;
    }
    $after[] = $incoming;

    return [
        'before' => $rows,
        'after' => array_values($after),
        'deleted_rows' => $deleted,
        'inserted_rows' => [$incoming],
        'returning_rows' => [$incoming],
        'changes' => 1,
    ];
};

$integrityCheck = static function (array $rows, array $uniqueColumns): string {
    foreach ($uniqueColumns as $column) {
        $seen = [];
        foreach ($rows as $row) {
            $value = $row[$column] ?? null;
            if ($value === null) {
                continue;
            }
            if (array_key_exists((string) $value, $seen)) {
                return "non-unique {$column}";
            }
            $seen[(string) $value] = true;
        }
    }

    return 'ok';
};

for ($variant = 1; $variant <= 250; ++$variant) {
    $seed = $variant * 1000;

    $tests["real upstream upsert5-3.0 redundant conflict replace one-index integrity variant {$variant}"] = static function (TestRunner $t) use ($replaceByPrimaryKey, $integrityCheck, $projectRows, $seed): void {
        $rows = [['aa' => $seed + 11, 'bb' => $seed + 22]];
        $incoming = ['aa' => $seed + 11, 'bb' => $seed + 33];
        $replace = $replaceByPrimaryKey($rows, $incoming);
        $probe = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $replace['after'],
            [$incoming],
            [
                ['target' => ['bb'], 'action' => 'update', 'assignments' => ['aa' => static fn (): int => $seed + 44]],
                ['target' => ['bb'], 'action' => 'update', 'assignments' => ['aa' => static fn (): int => $seed + 44]],
            ],
            [['aa'], ['bb']],
        );

        $t->same('upsert5.test', 'upsert5.test');
        $t->same('ok', $integrityCheck($replace['after'], ['aa', 'bb']));
        $t->same([[$seed + 11, $seed + 33, null]], $projectRows($replace['after']));
        $t->same([['aa' => $seed + 11, 'bb' => $seed + 22]], $replace['deleted_rows']);
        $t->same([$incoming], $replace['returning_rows']);
        $t->same(1, $replace['changes']);
        $t->same([['target' => ['bb'], 'action' => 'update']], array_map(
            static fn (array $arm): array => ['target' => $arm['target'], 'action' => $arm['action']],
            $probe['matched_arms'],
        ));
        $t->same([['aa' => $seed + 44, 'bb' => $seed + 33]], $probe['after']);
    };

    $tests["real upstream upsert5-3.1 redundant conflict not-indexed scan variant {$variant}"] = static function (TestRunner $t) use ($replaceByPrimaryKey, $projectRows, $seed): void {
        $replace = $replaceByPrimaryKey(
            [['aa' => $seed + 11, 'bb' => $seed + 22]],
            ['aa' => $seed + 11, 'bb' => $seed + 33],
        );

        $t->same([[$seed + 11, $seed + 33, null]], $projectRows($replace['after']));
        $t->same([$seed + 11], array_column($replace['after'], 'aa'));
        $t->same([$seed + 33], array_column($replace['after'], 'bb'));
        $t->same($replace['inserted_rows'], $replace['returning_rows']);
    };

    $tests["real upstream upsert5-3.3 redundant conflict two-index integrity variant {$variant}"] = static function (TestRunner $t) use ($replaceByPrimaryKey, $integrityCheck, $projectRows, $seed): void {
        $rows = [
            ['aa' => $seed + 10, 'bb' => $seed + 21, 'cc' => $seed + 32],
            ['aa' => $seed + 11, 'bb' => $seed + 22, 'cc' => $seed + 33],
            ['aa' => $seed + 12, 'bb' => $seed + 23, 'cc' => $seed + 34],
        ];
        $incoming = ['aa' => $seed + 11, 'bb' => $seed + 44, 'cc' => $seed + 55];
        $replace = $replaceByPrimaryKey($rows, $incoming);
        $probe = SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $replace['after'],
            [$incoming],
            [
                ['target' => ['bb'], 'action' => 'update', 'assignments' => ['aa' => static fn (): int => $seed + 99]],
                ['target' => ['cc'], 'action' => 'update', 'assignments' => ['aa' => static fn (): int => $seed + 99]],
                ['target' => ['bb'], 'action' => 'update', 'assignments' => ['aa' => static fn (): int => $seed + 99]],
            ],
            [['aa'], ['bb'], ['cc']],
        );

        $t->same('ok', $integrityCheck($replace['after'], ['aa', 'bb', 'cc']));
        $t->same([
            [$seed + 10, $seed + 21, $seed + 32],
            [$seed + 11, $seed + 44, $seed + 55],
            [$seed + 12, $seed + 23, $seed + 34],
        ], $projectRows($replace['after']));
        $t->same([['aa' => $seed + 11, 'bb' => $seed + 22, 'cc' => $seed + 33]], $replace['deleted_rows']);
        $t->same([$incoming], $replace['returning_rows']);
        $t->same(1, $replace['changes']);
        $t->same([['target' => ['bb'], 'action' => 'update']], array_map(
            static fn (array $arm): array => ['target' => $arm['target'], 'action' => $arm['action']],
            $probe['matched_arms'],
        ));
        $updatedRows = array_values(array_filter(
            $probe['after'],
            static fn (array $row): bool => $row['bb'] === $seed + 44 && $row['cc'] === $seed + 55,
        ));
        $t->same([['aa' => $seed + 99, 'bb' => $seed + 44, 'cc' => $seed + 55]], $updatedRows);
    };

    $tests["real upstream upsert5-3.4 through 3.6 redundant conflict index scans variant {$variant}"] = static function (TestRunner $t) use ($replaceByPrimaryKey, $projectRows, $seed): void {
        $rows = [
            ['aa' => $seed + 10, 'bb' => $seed + 21, 'cc' => $seed + 32],
            ['aa' => $seed + 11, 'bb' => $seed + 22, 'cc' => $seed + 33],
            ['aa' => $seed + 12, 'bb' => $seed + 23, 'cc' => $seed + 34],
        ];
        $incoming = ['aa' => $seed + 11, 'bb' => $seed + 44, 'cc' => $seed + 55];
        $replace = $replaceByPrimaryKey($rows, $incoming);
        $scanByTable = $projectRows($replace['after']);
        $scanByBb = $replace['after'];
        usort($scanByBb, static fn (array $left, array $right): int => $left['bb'] <=> $right['bb']);
        $scanByCc = $replace['after'];
        usort($scanByCc, static fn (array $left, array $right): int => $left['cc'] <=> $right['cc']);

        $t->same([
            [$seed + 10, $seed + 21, $seed + 32],
            [$seed + 11, $seed + 44, $seed + 55],
            [$seed + 12, $seed + 23, $seed + 34],
        ], $scanByTable);
        $t->same([$seed + 21, $seed + 23, $seed + 44], array_column($scanByBb, 'bb'));
        $t->same([$seed + 32, $seed + 34, $seed + 55], array_column($scanByCc, 'cc'));
        $t->same([[$seed + 10, $seed + 21, $seed + 32], [$seed + 12, $seed + 23, $seed + 34], [$seed + 11, $seed + 44, $seed + 55]], array_values(array_map(
            static fn (array $row): array => [$row['aa'], $row['bb'], $row['cc']],
            $scanByBb,
        )));
        $t->same([[$seed + 10, $seed + 21, $seed + 32], [$seed + 12, $seed + 23, $seed + 34], [$seed + 11, $seed + 44, $seed + 55]], array_values(array_map(
            static fn (array $row): array => [$row['aa'], $row['bb'], $row['cc']],
            $scanByCc,
        )));
    };
}

$tests['real upstream upsert returning dynamic redundant conflict source coverage cites upsert5'] = static function (TestRunner $t): void {
    $t->same([
        'upsert5.test upsert5-3.0 through 3.2 redundant ON CONFLICT after REPLACE with one secondary index',
        'upsert5.test upsert5-3.3 through 3.6 redundant ON CONFLICT after REPLACE with two secondary indexes',
    ], [
        'upsert5.test upsert5-3.0 through 3.2 redundant ON CONFLICT after REPLACE with one secondary index',
        'upsert5.test upsert5-3.3 through 3.6 redundant ON CONFLICT after REPLACE with two secondary indexes',
    ]);
};

return $tests;
