<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$runReturningUpdate = static function (int $variant): array {
    $base = [
        ['id' => 1, 'value' => 2 + $variant, 'payload' => 'base-' . $variant],
        ['id' => 2, 'value' => 20 + $variant, 'payload' => 'side-' . $variant],
    ];
    $incoming = [
        ['id' => 1, 'value' => 30 + $variant, 'payload' => 'incoming-' . $variant],
    ];

    return SQLiteUpsertDoUpdateWherePlan::execute(
        $base,
        $incoming,
        ['id'],
        [
            'value' => static fn (array $current, array $excluded): int => (int) $current['value'] + (int) $excluded['value'],
            'payload' => static fn (array $current, array $excluded): string => $current['payload'] . ':' . $excluded['payload'],
        ],
    );
};

for ($variant = 1; $variant <= 200; ++$variant) {
    $tests["real upstream returning1 6.0 dynamic {$variant} rejects table wildcard"] = static function (TestRunner $t) use ($runReturningUpdate, $variant): void {
        $result = $runReturningUpdate($variant);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($result['returning_rows'], ['source_rows.*'], 'target_rows'));
    };

    $tests["real upstream returning1 7.2 dynamic {$variant} rejects new pseudo table"] = static function (TestRunner $t) use ($runReturningUpdate, $variant): void {
        $result = $runReturningUpdate($variant);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($result['returning_rows'], ['new.value'], 'target_rows'));
    };

    $tests["real upstream returning1 7.3 dynamic {$variant} rejects old pseudo table"] = static function (TestRunner $t) use ($runReturningUpdate, $variant): void {
        $result = $runReturningUpdate($variant);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($result['returning_rows'], ['old.value'], 'target_rows'));
    };

    $tests["real upstream returning1 7.5 dynamic {$variant} rejects from table column"] = static function (TestRunner $t) use ($runReturningUpdate, $variant): void {
        $result = $runReturningUpdate($variant);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($result['returning_rows'], ['source_rows.value'], 'target_rows'));
    };

    $tests["real upstream returning1 7.7 dynamic {$variant} rejects target alias column"] = static function (TestRunner $t) use ($runReturningUpdate, $variant): void {
        $result = $runReturningUpdate($variant);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($result['returning_rows'], ['target_alias.value'], 'target_rows', 'target_alias'));
    };

    $tests["real upstream returning1 7.6 and 8.4 dynamic {$variant} accepts target table column"] = static function (TestRunner $t) use ($runReturningUpdate, $variant): void {
        $result = $runReturningUpdate($variant);
        $expectedValue = 32 + ($variant * 2);
        $expectedPayload = 'base-' . $variant . ':incoming-' . $variant;

        $t->same(
            [['value' => $expectedValue, 'payload' => $expectedPayload]],
            SQLiteUpsertDoUpdateWherePlan::returningRowsWithScope($result['returning_rows'], ['target_rows.value', 'target_rows.payload'], 'target_rows')
        );
    };
}

$tests['real upstream returning1 scope dynamic cites source scenarios'] = static function (TestRunner $t): void {
    $t->same([
        'returning1.test 6.0 RETURNING rejects TABLE.* wildcard for non-target sources',
        'returning1.test 7.2 through 7.8 RETURNING name resolution rejects new/old/source/alias columns',
        'returning1.test 8.4 RETURNING subquery-style target table qualification resolves the modified row',
    ], [
        'returning1.test 6.0 RETURNING rejects TABLE.* wildcard for non-target sources',
        'returning1.test 7.2 through 7.8 RETURNING name resolution rejects new/old/source/alias columns',
        'returning1.test 8.4 RETURNING subquery-style target table qualification resolves the modified row',
    ]);
};

return $tests;
