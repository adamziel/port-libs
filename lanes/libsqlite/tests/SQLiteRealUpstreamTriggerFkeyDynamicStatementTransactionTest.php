<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream fkey2 statement transaction cites update rollback section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-3.1.*: Test UPDATE statements'));
        $t->true(is_string($source) && str_contains($source, 'CHECK constraint failed: e!=5'));
    },
    'real upstream fkey2 statement transaction cites delete rollback section' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test');
        $t->true(is_string($source) && str_contains($source, 'fkey2-3.2.*: Test DELETE statements'));
        $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY constraint failed'));
    },
];

for ($seed = 1; $seed <= 110; ++$seed) {
    $target = 1000 + $seed;
    $safe = 6000 + $seed;
    $abRows = [
        ['a' => $target, 'b' => 'parent-' . $seed],
        ['a' => $safe, 'b' => 'safe-' . $seed],
    ];
    $cdRows = [
        ['c' => $target, 'd' => 'child-' . $seed],
        ['c' => $safe, 'd' => 'safe-child-' . $seed],
    ];
    $efRows = [
        ['e' => $target, 'f' => 'grandchild-' . $seed],
        ['e' => $safe, 'f' => 'safe-grandchild-' . $seed],
    ];

    $updateRollback = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkeyActionStatementTransactionPlan(
        $abRows,
        $cdRows,
        $efRows,
        'update',
        $target,
        5
    );
    $updateCommit = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkeyActionStatementTransactionPlan(
        $abRows,
        $cdRows,
        $efRows,
        'update',
        $target,
        $target + 10
    );
    $deleteRollback = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkeyActionStatementTransactionPlan(
        $abRows,
        $cdRows,
        $efRows,
        'delete',
        $target
    );

    foreach ([
        'source' => 'fkey2.test fkey2-3.1.1..3.2.2',
        'operation' => 'update',
        'status' => 'statement-rolled-back',
        'failure' => 'CHECK constraint failed: e!=5',
        'rolled_back' => true,
        'statement_transaction_opened' => true,
        'ab.0.a' => $target,
        'cd.0.c' => $target,
        'ef.0.e' => $target,
        'attempted_ab.0.a' => 5,
        'attempted_cd.0.c' => 5,
        'attempted_ef.0.e' => 5,
        'actions.0.event' => 'update',
        'actions.1.event' => 'cascade-update',
        'actions.2.event' => 'cascade-update',
        'dependencies.1' => 'sqlite-fkey2-3-rolls-back-cascaded-update-on-check-failure',
    ] as $path => $expected) {
        $tests["real upstream fkey2-3 update statement rollback dynamic {$seed} {$path}"] = static function (TestRunner $t) use ($updateRollback, $path, $expected, $value): void {
            $t->same($expected, $value($updateRollback(), (string) $path));
        };
    }

    foreach ([
        'source' => 'fkey2.test fkey2-3.1.1..3.2.2',
        'operation' => 'update',
        'status' => 'committed',
        'failure' => null,
        'rolled_back' => false,
        'statement_transaction_opened' => true,
        'ab.0.a' => $target + 10,
        'cd.0.c' => $target + 10,
        'ef.0.e' => $target + 10,
        'actions.2.table' => 'ef',
        'dependencies.0' => 'sqlite-fkey2-3-opens-statement-transaction-for-fk-actions',
    ] as $path => $expected) {
        $tests["real upstream fkey2-3 update statement commit dynamic {$seed} {$path}"] = static function (TestRunner $t) use ($updateCommit, $path, $expected, $value): void {
            $t->same($expected, $value($updateCommit(), (string) $path));
        };
    }

    foreach ([
        'source' => 'fkey2.test fkey2-3.1.1..3.2.2',
        'operation' => 'delete',
        'status' => 'statement-rolled-back',
        'failure' => 'FOREIGN KEY constraint failed',
        'rolled_back' => true,
        'statement_transaction_opened' => true,
        'ab.0.a' => $target,
        'cd.0.c' => $target,
        'ef.0.e' => $target,
        'attempted_ab.0.a' => $safe,
        'attempted_cd.0.c' => $safe,
        'attempted_ef.0.e' => $target,
        'actions.0.event' => 'delete',
        'actions.1.event' => 'cascade-delete',
        'actions.2.event' => 'no-action-check',
        'dependencies.2' => 'sqlite-fkey2-3-rolls-back-cascaded-delete-on-child-fk-failure',
    ] as $path => $expected) {
        $tests["real upstream fkey2-3 delete statement rollback dynamic {$seed} {$path}"] = static function (TestRunner $t) use ($deleteRollback, $path, $expected, $value): void {
            $t->same($expected, $value($deleteRollback(), (string) $path));
        };
    }
}

$tests['real upstream fkey2-3 statement transaction rejects unsupported operation'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::fkeyActionStatementTransactionPlan(
        [['a' => 1, 'b' => 'one']],
        [['c' => 1, 'd' => 'child']],
        [['e' => 1, 'f' => 'grandchild']],
        'insert',
        1
    ));
};

return $tests;
