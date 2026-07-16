<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'live', 'bytes' => 20, 'expected_bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'staged', 'bytes' => 21, 'expected_bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'expected_status' => 'stale', 'bytes' => 12, 'expected_bytes' => 13, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'expected_status' => null, 'bytes' => 13, 'expected_bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'expected_status' => 'live', 'bytes' => 25, 'expected_bytes' => 25.0, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => null, 'expected_status' => null, 'bytes' => 26, 'expected_bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'expected_status' => 'queued', 'bytes' => 7, 'expected_bytes' => '7', 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'expected_status' => 'staged', 'bytes' => 5, 'expected_bytes' => null, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'expected_status' => 'queued', 'bytes' => 9, 'expected_bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];

$deleteDriftSql = "DELETE FROM wp_options WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) RETURNING option_id, status, expected_status, bytes, expected_bytes, (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) AS tuple_clean ORDER BY option_id";
$updateCleanSql = "UPDATE wp_options SET status = 'verified' WHERE (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) RETURNING option_id, status, expected_status, (status, bytes) IS DISTINCT FROM ('verified', expected_bytes) AS now_drifted ORDER BY option_id";
$deleteNullSql = "DELETE FROM wp_options WHERE (status, expected_status) IS NOT DISTINCT FROM (NULL, NULL) RETURNING option_id, (status, expected_status) IS DISTINCT FROM (NULL, NULL) AS null_pair_distinct";
$updateStorageSql = "UPDATE wp_options SET option_value = option_value || ':typed' WHERE (bytes, expected_bytes) IS DISTINCT FROM (bytes, bytes) RETURNING option_id, option_value, bytes, expected_bytes, (bytes, expected_bytes) IS DISTINCT FROM (bytes, bytes) AS typed_drift ORDER BY option_id";

$deleteDrift = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteDriftSql, $tables);
$updateClean = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateCleanSql, $tables);
$deleteNull = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteNullSql, $tables);
$updateStorage = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateStorageSql, $tables);
$parsedDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($deleteDriftSql);
$parsedUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($updateCleanSql);

$cases = [
    'parse delete distinct where preserved' => [static fn (): mixed => $parsedDelete()['where'], '(status, bytes) IS DISTINCT FROM (expected_status, expected_bytes)'],
    'parse delete returning not distinct expression preserved' => [static fn (): mixed => str_contains($parsedDelete()['returning'], 'tuple_clean'), true],
    'parse update not distinct where preserved' => [static fn (): mixed => $parsedUpdate()['where'], '(status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes)'],
    'parse update returning distinct expression preserved' => [static fn (): mixed => str_contains($parsedUpdate()['returning'], 'now_drifted'), true],
    'parse update assignment status verified' => [static fn (): mixed => $parsedUpdate()['assignments']['status'], "'verified'"],
    'parse delete order column' => [static fn (): mixed => $parsedDelete()['order_by'][0]['column'], 'option_id'],

    'delete distinct selected drift rows' => [static fn (): mixed => $deleteDrift()['plan']->selectedIds, [2, 3, 4, 7, 8]],
    'delete distinct returning row ids' => [static fn (): mixed => array_column($deleteDrift()['returning'], 'option_id'), [2, 3, 4, 7, 8]],
    'delete distinct string drift row two' => [static fn (): mixed => $deleteDrift()['returning'][0]['status'], 'live'],
    'delete distinct numeric drift row three bytes' => [static fn (): mixed => $deleteDrift()['returning'][1]['bytes'], 12],
    'delete distinct null versus stale row four' => [static fn (): mixed => $deleteDrift()['returning'][2]['expected_status'], null],
    'delete distinct null versus queued row seven' => [static fn (): mixed => $deleteDrift()['returning'][3]['status'], null],
    'delete distinct null expected bytes row eight' => [static fn (): mixed => $deleteDrift()['returning'][4]['expected_bytes'], null],
    'delete distinct returning not clean zeros' => [static fn (): mixed => array_column($deleteDrift()['returning'], 'tuple_clean'), [0, 0, 0, 0, 0]],
    'delete distinct keeps clean row one' => [static fn (): mixed => in_array(1, array_column($deleteDrift()['tables']['wp_options'], 'option_id'), true), true],
    'delete distinct keeps numeric int real not distinct row five' => [static fn (): mixed => in_array(5, array_column($deleteDrift()['tables']['wp_options'], 'option_id'), true), true],
    'delete distinct keeps aligned null pair row six' => [static fn (): mixed => in_array(6, array_column($deleteDrift()['tables']['wp_options'], 'option_id'), true), true],
    'delete distinct removes five drift rows' => [static fn (): mixed => array_column($deleteDrift()['tables']['wp_options'], 'option_id'), [1, 5, 6, 9]],
    'delete distinct returning count five' => [static fn (): mixed => count($deleteDrift()['returning']), 5],
    'delete distinct plan action delete' => [static fn (): mixed => $deleteDrift()['action'], 'delete'],

    'update not distinct selected clean rows' => [static fn (): mixed => $updateClean()['plan']->selectedIds, [1, 5, 6, 9]],
    'update not distinct returning row ids' => [static fn (): mixed => array_column($updateClean()['returning'], 'option_id'), [1, 5, 6, 9]],
    'update not distinct all statuses verified' => [static fn (): mixed => array_column($updateClean()['returning'], 'status'), ['verified', 'verified', 'verified', 'verified']],
    'update not distinct row one expected live retained' => [static fn (): mixed => $updateClean()['returning'][0]['expected_status'], 'live'],
    'update not distinct row five int real bytes match' => [static fn (): mixed => $updateClean()['returning'][1]['expected_status'], 'live'],
    'update not distinct row six nulls matched before update' => [static fn (): mixed => $updateClean()['returning'][2]['expected_status'], null],
    'update not distinct returning after update sees row drifted from verified tuple' => [static fn (): mixed => array_column($updateClean()['returning'], 'now_drifted'), [0, 0, 0, 0]],
    'update not distinct final row one verified' => [static fn (): mixed => array_column($updateClean()['tables']['wp_options'], 'status', 'option_id')[1], 'verified'],
    'update not distinct leaves drift row two live' => [static fn (): mixed => array_column($updateClean()['tables']['wp_options'], 'status', 'option_id')[2], 'live'],
    'update not distinct leaves null drift row seven null' => [static fn (): mixed => array_column($updateClean()['tables']['wp_options'], 'status', 'option_id')[7], null],
    'update not distinct mutation ids source order' => [static fn (): mixed => $updateClean()['plan']->mutationIds, [1, 5, 6, 9]],
    'update not distinct returning count four' => [static fn (): mixed => count($updateClean()['returning']), 4],

    'delete null not distinct selected aligned null pair' => [static fn (): mixed => $deleteNull()['plan']->selectedIds, [6]],
    'delete null not distinct returning distinct false' => [static fn (): mixed => $deleteNull()['returning'][0]['null_pair_distinct'], 0],
    'delete null not distinct removes only row six' => [static fn (): mixed => array_column($deleteNull()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 7, 8, 9]],
    'delete null not distinct leaves one-sided null row seven' => [static fn (): mixed => in_array(7, array_column($deleteNull()['tables']['wp_options'], 'option_id'), true), true],

    'update storage distinct selected string numeric drift' => [static fn (): mixed => $updateStorage()['plan']->selectedIds, [3, 7, 8]],
    'update storage distinct row three numeric mismatch' => [static fn (): mixed => $updateStorage()['returning'][0]['expected_bytes'], 13],
    'update storage distinct row seven text storage class differs' => [static fn (): mixed => $updateStorage()['returning'][1]['expected_bytes'], '7'],
    'update storage distinct row eight null differs' => [static fn (): mixed => $updateStorage()['returning'][2]['expected_bytes'], null],
    'update storage distinct returns true flags' => [static fn (): mixed => array_column($updateStorage()['returning'], 'typed_drift'), [1, 1, 1]],
    'update storage distinct mutates only three values' => [static fn (): mixed => array_column($updateStorage()['returning'], 'option_value'), ['feed:typed', 'theme:typed', 'cache:typed']],
    'update storage distinct leaves numeric int real row five unchanged' => [static fn (): mixed => array_column($updateStorage()['tables']['wp_options'], 'option_value', 'option_id')[5], 'https://network.test'],
    'update storage distinct final row seven typed' => [static fn (): mixed => array_column($updateStorage()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:typed'],

    'where distinct can combine with scalar predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AND autoload = 'no' RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [3, 4, 7, 8]],
    'where not distinct can combine with scalar predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'clean' WHERE (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) AND autoload = 'yes' RETURNING option_id ORDER BY option_id", $tables)['plan']->selectedIds, [1, 5, 6, 9]],
    'returning distinct false for aligned tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes) AS drift", $tables)['returning'][0]['drift'], 0],
    'returning not distinct true for aligned tuple' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING (status, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) AS clean", $tables)['returning'][0]['clean'], 1],
    'returning distinct true for one-sided null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 7 RETURNING (status, expected_status) IS DISTINCT FROM (NULL, NULL) AS drift", $tables)['returning'][0]['drift'], 1],
    'returning not distinct false for one-sided null' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 7 RETURNING (status, expected_status) IS NOT DISTINCT FROM (NULL, NULL) AS clean", $tables)['returning'][0]['clean'], 0],
    'returning distinct requires alias' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE option_id = 1 RETURNING (status, bytes) IS DISTINCT FROM (expected_status, expected_bytes)", $tables), InvalidArgumentException::class],
    'malformed distinct arity rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE (status, bytes) IS DISTINCT FROM (expected_status) RETURNING option_id", $tables), InvalidArgumentException::class],
    'malformed not distinct missing column rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("UPDATE wp_options SET status = 'x' WHERE (missing, bytes) IS NOT DISTINCT FROM (expected_status, expected_bytes) RETURNING option_id", $tables), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning distinct current source next142 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
