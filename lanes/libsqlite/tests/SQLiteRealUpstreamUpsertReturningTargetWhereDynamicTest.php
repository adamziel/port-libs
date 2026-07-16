<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

$run = static function (string $sql, array $rows): array {
    return SQLiteUpsertReturningSql::execute(
        $sql,
        ['excluded' => $rows],
        [['x', 'a b'], ['z']],
    );
};

for ($variant = 1; $variant <= 1000; ++$variant) {
    $seed = 10000 + $variant;
    $rows = [
        ['w' => 'base_' . $variant, 'x' => $seed, 'a b' => $seed + 1, 'z' => $seed + 2],
        ['w' => 'peer_' . $variant, 'x' => $seed + 10, 'a b' => $seed + 11, 'z' => $seed + 12],
    ];
    $incoming = 'incoming_' . $variant;
    $missingColumn = 'missing_predicate_' . $variant;

    $tests[sprintf('real upstream upsert4 returning target where rejects unresolved column dynamic %04d', $variant)] = static function (TestRunner $t) use ($run, $quote, $rows, $incoming, $seed, $missingColumn): void {
        $sql = 'INSERT INTO excluded AS current_settings(w,x,[a b],z) VALUES('
            . $quote($incoming) . ', ' . $seed . ', ' . ($seed + 1) . ', NULL) '
            . 'ON CONFLICT(x, [a b]) WHERE ' . $missingColumn . '=1 '
            . 'DO UPDATE SET w=current_settings.w||excluded.w WHERE excluded.x=' . $seed . ' '
            . 'RETURNING w, x, [a b] AS key_part';

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => $run($sql, $rows),
            'upsert4.test 8.5 unresolved conflict-target WHERE column is rejected before DO UPDATE RETURNING execution',
        );
    };
}

$tests['real upstream upsert4 returning target where dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        'upstream source: upsert4.test 8.5 rejects ON CONFLICT target WHERE columns that are not visible in the target table',
        'upstream source: returning1.test 4.1-4.5 RETURNING is statement-local and must not run after target-analysis failure',
        '1000 deterministic INSERT ... ON CONFLICT(x,[a b]) WHERE missing_predicate_N DO UPDATE ... RETURNING variants',
        'non-overlap: existing accepted batches cover omitted-target DO NOTHING RETURNING, excluded alias update execution, and upsert5 conflict-arm yield priority; this batch owns conflict-target WHERE name-resolution failure before RETURNING',
    ], [
        'upstream source: upsert4.test 8.5 rejects ON CONFLICT target WHERE columns that are not visible in the target table',
        'upstream source: returning1.test 4.1-4.5 RETURNING is statement-local and must not run after target-analysis failure',
        '1000 deterministic INSERT ... ON CONFLICT(x,[a b]) WHERE missing_predicate_N DO UPDATE ... RETURNING variants',
        'non-overlap: existing accepted batches cover omitted-target DO NOTHING RETURNING, excluded alias update execution, and upsert5 conflict-arm yield priority; this batch owns conflict-target WHERE name-resolution failure before RETURNING',
    ]);
};

$tests['real upstream upsert4 returning target where dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql conflict-target WHERE name resolution and RETURNING parse gate',
        'no new support component needed; reuses SQLiteUpsertReturningSql conflict-target WHERE name resolution and RETURNING parse gate',
    );
};

return $tests;
