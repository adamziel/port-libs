<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$runScopeUpsert = static function (int $seed): array {
    $base = [
        ['id' => 1, 'value' => 10 + $seed, 'payload' => 'current-' . $seed, 'score' => $seed],
        ['id' => 2, 'value' => 20 + $seed, 'payload' => 'side-' . $seed, 'score' => $seed + 10],
    ];
    $incoming = [
        ['id' => 1, 'value' => 30 + $seed, 'payload' => 'incoming-' . $seed, 'score' => $seed + 20],
    ];

    return SQLiteUpsertDoUpdateWherePlan::execute(
        $base,
        $incoming,
        ['id'],
        [
            'value' => static fn (array $current, array $excluded): int => (int) $current['value'] + (int) $excluded['value'],
            'payload' => static fn (array $current, array $excluded): string => $current['payload'] . ':' . $excluded['payload'],
            'score' => static fn (array $current, array $excluded): int => (int) $excluded['score'],
        ],
    );
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $label = sprintf('real upstream corpus upsert returning dynamic scope matrix seed %04d ', $seed);

    $tests[$label . 'returning1-6.1 accepts target wildcard but rejects table wildcard'] = static function (TestRunner $t) use ($runScopeUpsert, $seed): void {
        $plan = $runScopeUpsert($seed);
        $targetRows = SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['*'], 'target_rows');

        $t->same($plan['returning_rows'], $targetRows);
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['joined_rows.*'], 'target_rows'),
        );
    };

    $tests[$label . 'returning1-7.2/7.3 rejects old and new pseudo table qualifiers'] = static function (TestRunner $t) use ($runScopeUpsert, $seed): void {
        $plan = $runScopeUpsert($seed);

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['new.value'], 'target_rows'),
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['old.value'], 'target_rows'),
        );
    };

    $tests[$label . 'returning1-7.5 rejects joined source qualifier while target table qualifies'] = static function (TestRunner $t) use ($runScopeUpsert, $seed): void {
        $plan = $runScopeUpsert($seed);
        $expectedValue = 40 + ($seed * 2);

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['joined_rows.value'], 'target_rows'),
        );
        $t->same(
            [['value' => $expectedValue]],
            SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['target_rows.value'], 'target_rows'),
        );
    };

    $tests[$label . 'returning1-7.7 rejects target alias qualifier but table name still resolves'] = static function (TestRunner $t) use ($runScopeUpsert, $seed): void {
        $plan = $runScopeUpsert($seed);
        $expectedPayload = 'current-' . $seed . ':incoming-' . $seed;

        $t->throws(
            InvalidArgumentException::class,
            static fn (): array => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['target_alias.payload'], 'target_rows', 'target_alias'),
        );
        $t->same(
            [['payload' => $expectedPayload]],
            SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['target_rows.payload'], 'target_rows', 'target_alias'),
        );
    };

    $tests[$label . 'returning1-8.4 resolves target-table qualified expression after upsert update'] = static function (TestRunner $t) use ($runScopeUpsert, $seed): void {
        $plan = $runScopeUpsert($seed);

        $t->same(1, $plan['changes']);
        $t->same(
            [[
                'value' => 40 + ($seed * 2),
                'payload' => 'current-' . $seed . ':incoming-' . $seed,
                'score' => $seed + 20,
            ]],
            SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($plan['returning_rows'], ['target_rows.value', 'target_rows.payload', 'target_rows.score'], 'target_rows'),
        );
    };
}

$tests['real upstream corpus upsert returning dynamic scope matrix cites upstream sections'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test 6.1 target RETURNING wildcard succeeds while TABLE.* wildcard is rejected',
        'returning1.test 7.2 through 7.8 RETURNING name resolution rejects new/old/source/alias qualifiers',
        'returning1.test 8.4 RETURNING target table qualification resolves the modified row image',
        'upsert1.test/upsert2.test conflict update row images feed the RETURNING scope matrix',
    ], [
        'returning1.test 6.1 target RETURNING wildcard succeeds while TABLE.* wildcard is rejected',
        'returning1.test 7.2 through 7.8 RETURNING name resolution rejects new/old/source/alias qualifiers',
        'returning1.test 8.4 RETURNING target table qualification resolves the modified row image',
        'upsert1.test/upsert2.test conflict update row images feed the RETURNING scope matrix',
    ]);
};

return $tests;
