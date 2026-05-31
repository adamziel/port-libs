<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

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

$tests['real upstream trigger1 raise expression cites arbitrary expression section'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test trigger1-24.1'));
    $t->true(is_string($source) && str_contains($source, "SELECT raise(abort,format('attempt to insert %d where is not a power of 2',new.a))"));
};

$tests['real upstream trigger1 raise expression cites failing insert section'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
    $t->true(is_string($source) && str_contains($source, 'do_catchsql_test trigger1-24.2'));
    $t->true(is_string($source) && str_contains($source, 'INSERT INTO t1 VALUES(9876)'));
    $t->true(is_string($source) && str_contains($source, 'attempt to insert 9876 where is not a power of 2'));
};

for ($i = 1; $i <= 100; ++$i) {
    $validValues = [0, 1, 2, 4, 8, 16, 32, 64];
    $invalidValue = 9000 + ($i * 3);
    if (($invalidValue & ($invalidValue - 1)) === 0) {
        ++$invalidValue;
    }

    $validRows = array_map(static fn (int $value): array => ['a' => $value + ($i === 1 ? 0 : 0)], $validValues);
    $abortRows = array_merge(array_slice($validRows, 0, 4), [['a' => $invalidValue], ['a' => 65536 + $i]]);
    $failRows = array_merge(array_slice($validRows, 0, 3), [['a' => $invalidValue], ['a' => 128]]);

    $valid = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo($validRows, 'abort');
    $abort = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo($abortRows, 'abort');
    $fail = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo($failRows, 'fail');
    $rollback = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo($abortRows, 'rollback');

    $case = sprintf('real upstream trigger1 raise expression dynamic %03d', $i);

    foreach ([
        'source' => 'trigger1.test trigger1-24.1..24.2',
        'operation' => 'trigger-raise-expression-message',
        'status' => 'commit-ok',
        'raise_action' => 'abort',
        'attempted_values' => $validValues,
        'inserted_values' => $validValues,
        'failed_index' => null,
        'error_message' => null,
        'statement_rolled_back' => false,
        'dependencies.0' => 'sqlite-trigger1-raise-message-accepts-sql-expression',
    ] as $path => $expected) {
        $tests[$case . ' valid batch ' . $path] = static function (TestRunner $t) use ($valid, $path, $expected, $value): void {
            $t->same($expected, $value($valid(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'raise_action' => 'abort',
        'failed_index' => 4,
        'failed_value' => $invalidValue,
        'error_message' => sprintf('attempt to insert %d where is not a power of 2', $invalidValue),
        'inserted_values' => [],
        'statement_rolled_back' => true,
        'prior_successes_preserved' => false,
        'dependencies.1' => 'sqlite-trigger1-raise-expression-can-reference-new-row',
    ] as $path => $expected) {
        $tests[$case . ' abort expression error ' . $path] = static function (TestRunner $t) use ($abort, $path, $expected, $value): void {
            $t->same($expected, $value($abort(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'raise_action' => 'fail',
        'failed_index' => 3,
        'failed_value' => $invalidValue,
        'error_message' => sprintf('attempt to insert %d where is not a power of 2', $invalidValue),
        'inserted_values' => [0, 1, 2],
        'statement_rolled_back' => false,
        'prior_successes_preserved' => true,
    ] as $path => $expected) {
        $tests[$case . ' fail expression preserves prior rows ' . $path] = static function (TestRunner $t) use ($fail, $path, $expected, $value): void {
            $t->same($expected, $value($fail(), (string) $path));
        };
    }

    foreach ([
        'status' => 'constraint-failed',
        'raise_action' => 'rollback',
        'failed_index' => 4,
        'failed_value' => $invalidValue,
        'inserted_values' => [],
        'statement_rolled_back' => true,
        'dependencies.2' => 'sqlite-trigger1-raise-abort-rolls-back-statement',
    ] as $path => $expected) {
        $tests[$case . ' rollback expression error ' . $path] = static function (TestRunner $t) use ($rollback, $path, $expected, $value): void {
            $t->same($expected, $value($rollback(), (string) $path));
        };
    }
}

$tests['real upstream trigger1 raise expression rejects unsupported action'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo([['a' => 1]], 'ignore'));
};

$tests['real upstream trigger1 raise expression rejects missing input column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerRaiseExpressionPowerOfTwo([['b' => 1]], 'abort'));
};

return $tests;
