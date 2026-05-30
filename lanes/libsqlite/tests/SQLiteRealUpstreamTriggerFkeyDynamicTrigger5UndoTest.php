<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;

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
    'real upstream trigger5 undo corpus cites delete undo trigger' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger5.test');
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER trigItem_UNDO_AD AFTER DELETE ON Item'));
    },
    'real upstream trigger5 undo corpus cites quote old value expression' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger5.test');
        $t->true(is_string($source) && str_contains($source, "|| coalesce(old.a,'NULL') || ',' || quote(old.b) || ',' || old.c || ');'"));
    },
];

for ($i = 1; $i <= 120; ++$i) {
    $rows = [
        ['a' => 1, 'b' => 38205.60865, 'c' => 340],
        ['a' => 2, 'b' => "tenant-" . $i, 'c' => 20 + $i],
        ['a' => 3, 'b' => "quoted ' tenant " . $i, 'c' => 30 + $i],
        ['a' => 4, 'b' => null, 'c' => 40 + $i],
        ['a' => 5, 'b' => -($i + 0.25), 'c' => null],
    ];
    $selector = match ($i % 5) {
        0 => static fn (array $row): bool => ($row['a'] ?? null) === 1,
        1 => static fn (array $row): bool => ($row['a'] ?? 0) >= 4,
        2 => static fn (array $row): bool => is_string($row['b'] ?? null),
        3 => static fn (array $row): bool => ($row['c'] ?? 0) > 40,
        default => static fn (array $row): bool => ($row['a'] ?? 0) % 2 === 1,
    };

    $deleted = array_values(array_filter($rows, $selector));
    $remaining = array_values(array_filter($rows, static fn (array $row): bool => !$selector($row)));
    usort($remaining, static fn (array $left, array $right): int => ($left['a'] ?? 0) <=> ($right['a'] ?? 0));
    $undo = array_map(
        static fn (array $row): string => 'INSERT INTO Item (a,b,c) VALUES ('
            . SQLiteRealExpressionAffinityCorpusPlan::quote($row['a'] ?? null)
            . ','
            . SQLiteRealExpressionAffinityCorpusPlan::quote($row['b'] ?? null)
            . ','
            . SQLiteRealExpressionAffinityCorpusPlan::quote($row['c'] ?? null)
            . ');',
        $deleted
    );

    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::deleteUndoTriggerStatements($rows, $selector);
    $case = 'trigger5-1.1 after delete undo sql dynamic ' . $i;
    foreach ([
        'source' => 'trigger5.test trigger5-1.1',
        'operation' => 'after-delete-trigger-undo-sql-generation',
        'status' => 'commit-ok',
        'deleted_count' => count($deleted),
        'undo_count' => count($undo),
        'remaining_count' => count($remaining),
        'quote_function_used' => true,
        'dependencies.0' => 'sqlite-trigger5-after-delete-old-row-undo-sql',
        'dependencies.1' => 'sqlite-trigger5-quote-function-preserves-real-text-null-values',
        'dependencies.2' => 'sqlite-trigger5-delete-trigger-emits-one-undo-row-per-deleted-row',
    ] as $path => $expected) {
        $tests[$case . ' ' . $path] = static function (TestRunner $t) use ($plan, $path, $expected, $value): void {
            $t->same($expected, $value($plan(), (string) $path));
        };
    }

    $tests[$case . ' undo statements match upstream concatenation'] = static function (TestRunner $t) use ($plan, $undo): void {
        $t->same($undo, $plan()['undo_statements']);
    };
    $tests[$case . ' deleted rows preserve old image order'] = static function (TestRunner $t) use ($plan, $deleted): void {
        $t->same($deleted, $plan()['deleted_rows']);
    };
    $tests[$case . ' remaining rows exclude deleted rows'] = static function (TestRunner $t) use ($plan, $remaining): void {
        $t->same($remaining, $plan()['remaining_rows']);
    };
}

return $tests;
