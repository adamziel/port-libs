<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 24, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'network_siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'network-stale', 'bytes' => 14, 'option_value' => 'network-feed'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$replaceLaterSql = "UPDATE OR REPLACE wp_options SET (option_name, status, option_value, bytes) = ('_transient_feed', option_name || ':replace', option_value || ':next', bytes + blog_id) WHERE option_id IN (2, 3, 5) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) = (1, '_transient_feed') AS tuple_hit ORDER BY option_id";
$ignoreLaterSql = "UPDATE OR IGNORE wp_options SET (option_name, status, option_value) = ('_transient_feed', option_name || ':ignore', option_value || ':next') WHERE option_id IN (2, 3, 5) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$replaceNetworkSql = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status) = (2, '_transient_feed', option_name || ':network') WHERE option_id IN (2, 5, 6) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$replaceLater = static fn (): array => SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute($tables, $replaceLaterSql, $unique);
$ignoreLater = static fn (): array => SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute($tables, $ignoreLaterSql, $unique);
$replaceNetwork = static fn (): array => SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute($tables, $replaceNetworkSql, $unique);

$cases = [
    'replace later status ready' => [static fn (): mixed => $replaceLater()['status'], 'rowvalue-update-returning-conflict-current-source-next137-ready'],
    'replace later action update' => [static fn (): mixed => $replaceLater()['action'], 'update'],
    'replace later conflict action replace' => [static fn (): mixed => $replaceLater()['conflict_action'], 'replace'],
    'replace later selected ids include deleted later row' => [static fn (): mixed => $replaceLater()['selected_ids'], [2, 3, 5]],
    'replace later mutation ids include original selected order' => [static fn (): mixed => $replaceLater()['mutation_ids'], [2, 3, 5]],
    'replace later returns row two and row five only' => [static fn (): mixed => $replaceLater()['returning_ids'], [2, 5]],
    'replace later suppresses selected row three after row two deletes it' => [static fn (): mixed => $replaceLater()['suppressed_selected_ids'], [3]],
    'replace later deletes row three and row six conflict peers' => [static fn (): mixed => $replaceLater()['deleted_conflict_ids'], [3, 6]],
    'replace later row two returning uses assigned unique tuple' => [static fn (): mixed => $replaceLater()['returning'][0]['option_name'], '_transient_feed'],
    'replace later row two status uses old option name' => [static fn (): mixed => $replaceLater()['returning'][0]['status'], 'home:replace'],
    'replace later row two value uses old option value' => [static fn (): mixed => $replaceLater()['returning'][0]['option_value'], 'https://old.test:next'],
    'replace later row two bytes use old blog id arithmetic' => [static fn (): mixed => $replaceLater()['returning'][0]['bytes'], 25],
    'replace later row two tuple returning sees next image' => [static fn (): mixed => $replaceLater()['returning'][0]['tuple_hit'], 1],
    'replace later row five returned after no conflict' => [static fn (): mixed => $replaceLater()['returning'][1]['option_id'], 5],
    'replace later row five keeps blog two tuple outside unique conflict' => [static fn (): mixed => $replaceLater()['returning'][1]['blog_id'], 2],
    'replace later row five tuple hit false' => [static fn (): mixed => $replaceLater()['returning'][1]['tuple_hit'], 0],
    'replace later current source row ids omit deleted rows three and six' => [static fn (): mixed => $replaceLater()['current_source_row_ids'], [1, 2, 4, 5, 7]],
    'replace later current row two is transient feed' => [static fn (): mixed => array_column($replaceLater()['current_source_tables']['wp_options'], 'option_name', 'option_id')[2], '_transient_feed'],
    'replace later current row three absent' => [static fn (): mixed => array_key_exists(3, array_column($replaceLater()['current_source_tables']['wp_options'], 'option_name', 'option_id')), false],
    'replace later current row five updated independently' => [static fn (): mixed => array_column($replaceLater()['current_source_tables']['wp_options'], 'status', 'option_id')[5], 'network_siteurl:replace'],
    'replace later conflict records row two' => [static fn (): mixed => $replaceLater()['conflicts'][0]['row_id'], 2],
    'replace later conflict records peer row three' => [static fn (): mixed => $replaceLater()['conflicts'][0]['conflicting_row_ids'], [3]],
    'replace later conflict key is composite row value' => [static fn (): mixed => $replaceLater()['conflicts'][0]['key'], '1|_transient_feed'],
    'replace later conflict columns are composite unique' => [static fn (): mixed => $replaceLater()['conflicts'][0]['columns'], ['blog_id', 'option_name']],
    'replace later changed row count excludes suppressed row' => [static fn (): mixed => $replaceLater()['changed_row_count'], 2],
    'replace later deleted conflict count two' => [static fn (): mixed => $replaceLater()['deleted_conflict_count'], 2],
    'replace later changed flag true' => [static fn (): mixed => $replaceLater()['current_source_changed'], true],
    'replace later dependency includes selected-row admission' => [static fn (): mixed => in_array('sqlite-row-value-conflict-selected-row-admission', $replaceLater()['dependencies'], true), true],

    'ignore later conflict action ignore' => [static fn (): mixed => $ignoreLater()['conflict_action'], 'ignore'],
    'ignore later selected ids preserved' => [static fn (): mixed => $ignoreLater()['selected_ids'], [2, 3, 5]],
    'ignore later returns row three only' => [static fn (): mixed => $ignoreLater()['returning_ids'], [3]],
    'ignore later ignores row two and row five conflicts' => [static fn (): mixed => $ignoreLater()['ignored_ids'], [2, 5]],
    'ignore later suppresses no selected rows' => [static fn (): mixed => $ignoreLater()['suppressed_selected_ids'], []],
    'ignore later deletes no conflict rows' => [static fn (): mixed => $ignoreLater()['deleted_conflict_ids'], []],
    'ignore later conflict peer row three remains current source' => [static fn (): mixed => array_column($ignoreLater()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'ignore later ignored row two original name restored' => [static fn (): mixed => array_column($ignoreLater()['current_source_tables']['wp_options'], 'option_name', 'option_id')[2], 'home'],
    'ignore later row three returning keeps stale tuple' => [static fn (): mixed => $ignoreLater()['returning'][0]['status'], '_transient_feed:ignore'],
    'ignore later row five original name restored' => [static fn (): mixed => array_column($ignoreLater()['current_source_tables']['wp_options'], 'option_name', 'option_id')[5], 'network_siteurl'],
    'ignore later conflict records row two' => [static fn (): mixed => $ignoreLater()['conflicts'][0]['row_id'], 2],
    'ignore later conflict peer is row three' => [static fn (): mixed => $ignoreLater()['conflicts'][0]['conflicting_row_ids'], [3]],
    'ignore later current source ids unchanged' => [static fn (): mixed => $ignoreLater()['current_source_row_ids'], [1, 2, 3, 4, 5, 6, 7]],
    'ignore later second conflict peer is row six' => [static fn (): mixed => $ignoreLater()['conflicts'][1]['conflicting_row_ids'], [6]],
    'ignore later changed row count one' => [static fn (): mixed => $ignoreLater()['changed_row_count'], 1],

    'replace network returns row two and row five' => [static fn (): mixed => $replaceNetwork()['returning_ids'], [2, 5]],
    'replace network suppresses row six after row two deletes it' => [static fn (): mixed => $replaceNetwork()['suppressed_selected_ids'], [6]],
    'replace network deletes row six then row two' => [static fn (): mixed => $replaceNetwork()['deleted_conflict_ids'], [6, 2]],
    'replace network row five survives with network siteurl' => [static fn (): mixed => array_column($replaceNetwork()['current_source_tables']['wp_options'], 'option_name', 'option_id')[5], '_transient_feed'],
    'replace network row six absent' => [static fn (): mixed => array_key_exists(6, array_column($replaceNetwork()['current_source_tables']['wp_options'], 'option_name', 'option_id')), false],
    'replace network current row ids omit six and superseded row two' => [static fn (): mixed => $replaceNetwork()['current_source_row_ids'], [1, 3, 4, 5, 7]],
    'replace network first conflict uses row six' => [static fn (): mixed => $replaceNetwork()['conflicts'][0]['conflicting_row_ids'], [6]],
    'replace network final conflict list has two conflicts' => [static fn (): mixed => count($replaceNetwork()['conflicts']), 2],
    'replace network row two status uses old name' => [static fn (): mixed => $replaceNetwork()['returning'][0]['status'], 'home:network'],
    'replace network row five status uses old name' => [static fn (): mixed => $replaceNetwork()['returning'][1]['status'], 'network_siteurl:network'],

    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute($tables, $replaceLaterSql, []), InvalidArgumentException::class],
    'malformed delete sql rejected by update-only plan' => [static fn (): mixed => SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute($tables, "DELETE FROM wp_options WHERE option_id = 1 RETURNING option_id", $unique), InvalidArgumentException::class],
    'malformed missing row id in returned rows rejected' => [static fn (): mixed => SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute(['wp_options' => [['blog_id' => 1, 'option_name' => 'x']]], "UPDATE OR REPLACE wp_options SET (blog_id, option_name) = (1, 'y') WHERE option_name = 'x' RETURNING blog_id, option_name", $unique), InvalidArgumentException::class],
    'malformed row id type rejected' => [static fn (): mixed => SQLiteRowValueUpdateReturningConflictCurrentSourceNextPlan::execute(['wp_options' => [['option_id' => ['bad'], 'blog_id' => 1, 'option_name' => 'x']]], "UPDATE OR REPLACE wp_options SET (blog_id, option_name) = (1, 'y') WHERE option_name = 'x' RETURNING option_id, blog_id, option_name", $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update returning conflict current source next137 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
