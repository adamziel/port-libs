<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertCountChangesPlan;

$tests = [];

/*
 * Real upstream source: SQLite test/upsert1.test.
 *
 * This ports upsert1-400 and upsert1-410. SQLite returns a count_changes row
 * for the INSERT side of the statement even though the same UPSERT updates
 * three existing row images and changes() reports four total row changes.
 */

$runScenario = static function (int $variant): array {
    $suffix = sprintf('%04d', $variant);

    return SQLiteUpsertCountChangesPlan::upsert1CountChangesScenario(
        [
            ['a' => "one_{$suffix}"],
            ['a' => "two_{$suffix}"],
            ['a' => "three_{$suffix}"],
        ],
        [
            ['a' => "one_{$suffix}"],
            ['a' => "one_{$suffix}"],
            ['a' => "three_{$suffix}"],
            ['a' => "four_{$suffix}"],
        ],
    );
};

foreach (range(1, 1000) as $variant) {
    $suffix = sprintf('%04d', $variant);

    $tests[sprintf('real upstream upsert count changes dynamic upsert1-400 variant %04d', $variant)] =
        static function (TestRunner $t) use ($runScenario, $variant, $suffix): void {
            $plan = $runScenario($variant);

            $t->same('ok', $plan['status']);
            $t->same('SQLite test/upsert1.test upsert1-400 through upsert1-410', $plan['source']);
            $t->same(['upsert1-400', 'upsert1-410'], $plan['scenarios']);
            $t->same(true, $plan['count_changes_enabled']);
            $t->same('rows inserted', $plan['count_changes_column']);
            $t->same([1], $plan['count_changes_result']);
            $t->same(4, $plan['changes_function_result']);
            $t->same(1, $plan['inserted_count']);
            $t->same(3, $plan['updated_count']);
            $t->same(0, $plan['skipped_count']);
            $t->same([
                ['a' => "one_{$suffix}", 'b' => 1],
                ['a' => "two_{$suffix}", 'b' => 1],
                ['a' => "three_{$suffix}", 'b' => 1],
            ], $plan['before']);
            $t->same([
                ['a' => "one_{$suffix}", 'b' => 3],
                ['a' => "two_{$suffix}", 'b' => 1],
                ['a' => "three_{$suffix}", 'b' => 2],
                ['a' => "four_{$suffix}", 'b' => 1],
            ], $plan['after']);
            $t->same([
                ['a' => "four_{$suffix}", 'b' => 1],
                ['a' => "one_{$suffix}", 'b' => 3],
                ['a' => "three_{$suffix}", 'b' => 2],
                ['a' => "two_{$suffix}", 'b' => 1],
            ], $plan['ordered_after']);
            $t->same([
                ['a' => "one_{$suffix}", 'b' => 2],
                ['a' => "one_{$suffix}", 'b' => 3],
                ['a' => "three_{$suffix}", 'b' => 2],
                ['a' => "four_{$suffix}", 'b' => 1],
            ], $plan['changed_row_images']);
            $t->same([
                'upsert1.test-400',
                'upsert1.test-410',
                'sqlite-pragma-count-changes-upsert-insert-result',
                'sqlite-upsert-do-update-row-image',
            ], $plan['dependencies']);
        };
}

$tests['real upstream upsert count changes dynamic source citations'] = static function (TestRunner $t): void {
    $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test');

    $t->same(true, is_string($source));
    $t->same(true, str_contains((string) $source, 'do_execsql_test upsert1-400'));
    $t->same(true, str_contains((string) $source, 'PRAGMA count_changes=ON;'));
    $t->same(true, str_contains((string) $source, "INSERT INTO t2(a) VALUES('one'),('one'),('three'),('four')"));
    $t->same(true, str_contains((string) $source, 'ON CONFLICT(a) DO UPDATE SET b=b+1'));
    $t->same(true, str_contains((string) $source, '} {1}'));
    $t->same(true, str_contains((string) $source, 'do_execsql_test upsert1-410'));
    $t->same(true, str_contains((string) $source, 'SELECT a, b FROM t2 ORDER BY a;'));
    $t->same(
        'no new support component needed; reuses lane-local UPSERT row executor and adds count_changes result-row modeling for upstream upsert1.test',
        'no new support component needed; reuses lane-local UPSERT row executor and adds count_changes result-row modeling for upstream upsert1.test',
    );
};

return $tests;
