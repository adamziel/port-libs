<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$valueAt = static function (array $array, string $path): mixed {
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

$statementFor = static function (string $type, int $seed): array {
    return match ($type) {
        'update' => [
            'type' => 'update',
            'set' => ['c' => 1000 + $seed],
            'where' => static fn (array $row): bool => (int) $row['a'] === $seed,
        ],
        'delete' => [
            'type' => 'delete',
            'where' => static fn (array $row): bool => (int) $row['a'] === $seed,
        ],
        'insert' => [
            'type' => 'insert',
            'row' => ['a' => $seed, 'b' => $seed + 20, 'c' => $seed + 30],
        ],
        default => throw new InvalidArgumentException('unsupported statement type'),
    };
};

$expectedTriggerChanges = static function (string $program, string $type, string $timing, array $rows, array $logRows, int $seed): int {
    $matchingRows = array_values(array_filter($rows, static fn (array $row): bool => (int) $row['a'] === $seed));
    $statementChanges = count($matchingRows);
    if ($type === 'insert') {
        $statementChanges = 1;
    }

    $rowCountAtTrigger = count($rows);
    if ($type === 'insert' && $timing === 'after') {
        ++$rowCountAtTrigger;
    } elseif ($type === 'delete' && $timing === 'after') {
        $rowCountAtTrigger -= min(1, $statementChanges);
    }

    return match ($program) {
        'update-b-from-old' => $type === 'insert' ? 0 : $rowCountAtTrigger,
        'insert-log-new-c' => 1,
        'delete-log-a1' => count(array_filter($logRows, static fn (array $row): bool => (int) $row['a'] === 1)),
        'compound-insert-update-delete-log' => 1 + ($type === 'insert' ? 0 : $rowCountAtTrigger + 1) + count($logRows),
        'insert-log-select-table' => $rowCountAtTrigger,
        default => throw new InvalidArgumentException('unsupported trigger program'),
    };
};

$tests = [
    'real upstream trigger2 program execution cites upstream section header' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test');
        $t->true(is_string($source));
        $t->true(str_contains($source, '2. Trigger program execution tests.'));
        $t->true(str_contains($source, 'trigger2-2.$ii-before'));
        $t->true(str_contains($source, 'trigger2-2.$ii-after'));
    },
];

$programs = [
    'update-b-from-old',
    'insert-log-new-c',
    'delete-log-a1',
    'compound-insert-update-delete-log',
    'insert-log-select-table',
];
$types = ['update', 'delete', 'insert'];
$timings = ['before', 'after'];

for ($seed = 1; $seed <= 50; ++$seed) {
    foreach ($programs as $program) {
        foreach ($types as $type) {
            foreach ($timings as $timing) {
                $rows = [
                    ['a' => $seed, 'b' => $seed + 1, 'c' => $seed + 2],
                    ['a' => $seed + 100, 'b' => $seed + 101, 'c' => $seed + 102],
                ];
                if ($seed % 4 === 0) {
                    $rows[] = ['a' => $seed + 200, 'b' => $seed + 201, 'c' => $seed + 202];
                }
                $logRows = [
                    ['a' => 1, 'b' => 2, 'c' => 3],
                    ['a' => 10 + $seed, 'b' => 20 + $seed, 'c' => 30 + $seed],
                ];
                if ($seed % 5 === 0) {
                    $logRows[] = ['a' => 1, 'b' => 200 + $seed, 'c' => 300 + $seed];
                }

                $statement = $statementFor($type, $seed);
                $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution(
                    $rows,
                    $logRows,
                    $statement,
                    $program,
                    $timing
                );
                $triggerChanges = $expectedTriggerChanges($program, $type, $timing, $rows, $logRows, $seed);
                $case = sprintf('real upstream trigger2.test trigger2-2 program execution seed %03d %s %s %s', $seed, $program, $type, $timing);
                $expectations = [
                    'source' => 'trigger2.test trigger2-2',
                    'operation' => 'trigger-program-statement-execution',
                    'status' => 'commit-ok',
                    'timing' => $timing,
                    'statement_type' => $type,
                    'program' => $program,
                    'statement_changes' => 1,
                    'trigger_program_changes' => $triggerChanges,
                    'total_changes' => 1 + $triggerChanges,
                    'context_count' => 1,
                    'dependencies.0' => 'sqlite-trigger2-before-program-runs-before-statement-row-change',
                    'dependencies.1' => 'sqlite-trigger2-after-program-runs-after-statement-row-change',
                    'dependencies.2' => 'sqlite-trigger2-trigger-program-can-update-insert-delete-select',
                    'dependencies.3' => 'sqlite-trigger2-old-new-row-values-feed-program',
                ];

                foreach ($expectations as $path => $expected) {
                    $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $valueAt): void {
                        $t->same($expected, $valueAt($plan(), (string) $path));
                    };
                }

                $tests[$case . ' context row image boundary'] = static function (TestRunner $t) use ($plan, $type, $seed): void {
                    $actual = $plan();
                    if ($type === 'insert') {
                        $t->same([], $actual['contexts'][0]['old']);
                        $t->same($seed, $actual['contexts'][0]['new']['a']);
                        return;
                    }
                    if ($type === 'delete') {
                        $t->same($seed, $actual['contexts'][0]['old']['a']);
                        $t->same([], $actual['contexts'][0]['new']);
                        return;
                    }

                    $t->same($seed, $actual['contexts'][0]['old']['a']);
                    $t->same(1000 + $seed, $actual['contexts'][0]['new']['c']);
                };
            }
        }
    }
}

$tests['real upstream trigger2 program execution rejects unsupported timing'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution([], [], ['type' => 'insert', 'row' => ['a' => 1, 'b' => 2, 'c' => 3]], 'insert-log-new-c', 'during'));
};

$tests['real upstream trigger2 program execution rejects unsupported program'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution([], [], ['type' => 'insert', 'row' => ['a' => 1, 'b' => 2, 'c' => 3]], 'vacuum-log', 'after'));
};

$tests['real upstream trigger2 program execution rejects unsupported statement type'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerProgramStatementExecution([], [], ['type' => 'replace'], 'insert-log-new-c', 'after'));
};

return $tests;
