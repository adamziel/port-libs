<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$ignoreSql = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (blog_id, 'siteurl', option_name || ':ignored', option_value || ':ignored', bytes + 10) WHERE option_id IN (8, 7) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$replaceSql = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'home', option_name || ':replaced', option_value || ':replaced', bytes + 20) WHERE option_id = 9 RETURNING option_id, blog_id, option_name, status, option_value, bytes";
$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed'), (1, 'siteurl'), (3, 'siteurl')) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";
$releaseSql = "UPDATE wp_options SET (blog_id, option_name, status, option_value) = (blog_id, option_name || ':ok', 'ok', option_value || ':ok') WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";

$ignoreOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', $unique);
$replaceOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', $unique);
$releasePlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext156($tables, [$ignoreSql, $replaceSql, $deleteSql], $unique);
$rollbackPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext156($tables, [$releaseSql, $deleteSql], $unique, 'wp_options_retry', 'option_id', 1);
$simpleReleasePlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext156($tables, [$releaseSql, $deleteSql], $unique);

$cases = [
    'parser records ignore conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($ignoreSql)['conflict_action'], 'ignore'],
    'parser records replace conflict action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($replaceSql)['conflict_action'], 'replace'],
    'parser ignore row value columns' => [static fn (): mixed => array_keys(SQLiteUpdateDeleteReturningSql::parse($ignoreSql)['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'ignore selected ids use descending order' => [static fn (): mixed => $ignoreOnly()['plan']->selectedIds, [8, 7]],
    'ignore mutation ids apply in source order' => [static fn (): mixed => $ignoreOnly()['plan']->mutationIds, [7, 8]],
    'ignore returning yields only nonconflicting row' => [static fn (): mixed => array_column($ignoreOnly()['returning'], 'option_id'), [8]],
    'ignore row eight moves to siteurl key' => [static fn (): mixed => $ignoreOnly()['returning'][0]['option_name'], 'siteurl'],
    'ignore row eight returning status uses old name' => [static fn (): mixed => $ignoreOnly()['returning'][0]['status'], 'orphaned_cache:ignored'],
    'ignore row seven is ignored' => [static fn (): mixed => array_column($ignoreOnly()['ignored_rows'], 'option_id'), [7]],
    'ignore conflict is row seven against network siteurl' => [static fn (): mixed => $ignoreOnly()['conflicts'][0]['conflicting_row_ids'], [5]],
    'ignore current row seven restored' => [static fn (): mixed => array_column($ignoreOnly()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'ignore current row eight changed' => [static fn (): mixed => array_column($ignoreOnly()['tables']['wp_options'], 'status', 'option_id')[8], 'orphaned_cache:ignored'],
    'ignore deletes no conflict rows' => [static fn (): mixed => $ignoreOnly()['deleted_conflict_rows'], []],
    'replace selected row nine' => [static fn (): mixed => $replaceOnly()['plan']->selectedIds, [9]],
    'replace returning row nine' => [static fn (): mixed => array_column($replaceOnly()['returning'], 'option_id'), [9]],
    'replace returning row uses home key' => [static fn (): mixed => $replaceOnly()['returning'][0]['option_name'], 'home'],
    'replace deletes conflicting row two' => [static fn (): mixed => array_column($replaceOnly()['deleted_conflict_rows'], 'option_id'), [2]],
    'replace result omits row two' => [static fn (): mixed => in_array(2, array_column($replaceOnly()['tables']['wp_options'], 'option_id'), true), false],
    'replace result keeps row nine' => [static fn (): mixed => array_column($replaceOnly()['tables']['wp_options'], 'status', 'option_id')[9], 'rewrite_rules:replaced'],

    'release plan status released' => [static fn (): mixed => $releasePlan()['status'], 'released'],
    'release plan savepoint name' => [static fn (): mixed => $releasePlan()['savepoint'], 'wp_options_rowvalue_yield_batch'],
    'release plan not rolled back' => [static fn (): mixed => $releasePlan()['rolled_back_to_savepoint'], false],
    'release plan does not preserve savepoint' => [static fn (): mixed => $releasePlan()['savepoint_preserved'], false],
    'release plan executes three statements' => [static fn (): mixed => count($releasePlan()['executed_statements']), 3],
    'release plan actions update update delete' => [static fn (): mixed => array_column($releasePlan()['executed_statements'], 'action'), ['update', 'update', 'delete']],
    'release plan conflict actions ignore replace abort' => [static fn (): mixed => array_column($releasePlan()['executed_statements'], 'conflict_action'), ['ignore', 'replace', 'abort']],
    'release plan yielded actions match executed' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'], 'action'), ['update', 'update', 'delete']],
    'release plan ignore yielded row eight only' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][0]['rows'], 'option_id'), [8]],
    'release plan replace yielded row nine only' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][1]['rows'], 'option_id'), [9]],
    'release plan delete sees current source after ignore and replace' => [static fn (): mixed => array_column($releasePlan()['yielded_returning'][2]['rows'], 'option_id'), [1, 3, 4, 8]],
    'release plan delete includes moved orphaned cache as siteurl' => [static fn (): mixed => $releasePlan()['yielded_returning'][2]['rows'][3]['option_name'], 'siteurl'],
    'release plan ignored row count one' => [static fn (): mixed => $releasePlan()['ignored_row_count'], 1],
    'release plan deleted conflict row count one' => [static fn (): mixed => $releasePlan()['deleted_conflict_row_count'], 1],
    'release plan changes include yields plus conflict delete' => [static fn (): mixed => $releasePlan()['changes'], 7],
    'release plan attempted changes equals changes' => [static fn (): mixed => $releasePlan()['attempted_changes_before_rollback'], 7],
    'release plan discarded returning zero' => [static fn (): mixed => $releasePlan()['discarded_returning_count'], 0],
    'release plan final ids omit deleted original home and cleanup rows' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_id'), [5, 6, 7, 9]],
    'release plan final row seven stayed pending theme' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'release plan final row nine home replaced' => [static fn (): mixed => array_column($releasePlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[9], 'home'],
    'release plan final row count four' => [static fn (): mixed => $releasePlan()['row_counts']['wp_options'], 4],
    'release plan changed tables reports wp options' => [static fn (): mixed => $releasePlan()['savepoint_changed_tables'], ['wp_options']],
    'release plan next source equals current source' => [static fn (): mixed => $releasePlan()['next_source_tables'], $releasePlan()['current_source_tables']],
    'release plan source rows capture attempted ignore ids' => [static fn (): mixed => array_column($releasePlan()['executed_statements'][0]['source_rows'], 'option_id'), [7, 8]],
    'release plan conflicts record ignored tuple key' => [static fn (): mixed => $releasePlan()['executed_statements'][0]['conflicts'][0]['key'], '2|siteurl'],
    'release plan replace deleted conflict row captured' => [static fn (): mixed => $releasePlan()['executed_statements'][1]['deleted_conflict_rows'][0]['option_name'], 'home'],
    'release plan dependency records ignore yielding' => [static fn (): mixed => in_array('sqlite-update-or-ignore-rowvalue-returning-yields-successful-rows-only', $releasePlan()['dependencies'], true), true],
    'release plan dependency records replace delete before yield' => [static fn (): mixed => in_array('sqlite-update-or-replace-rowvalue-returning-deletes-conflict-before-yield', $releasePlan()['dependencies'], true), true],
    'release plan dependency records delete current source' => [static fn (): mixed => in_array('sqlite-delete-returning-uses-current-source-after-rowvalue-update', $releasePlan()['dependencies'], true), true],

    'simple release plan yields two update rows' => [static fn (): mixed => array_column($simpleReleasePlan()['yielded_returning'][0]['rows'], 'option_id'), [7, 8]],
    'simple release plan delete sees renamed ok rows excluded' => [static fn (): mixed => array_column($simpleReleasePlan()['yielded_returning'][1]['rows'], 'option_id'), [1, 3, 4]],
    'simple release plan final row seven ok' => [static fn (): mixed => array_column($simpleReleasePlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], 'ok'],
    'simple release plan final row count six' => [static fn (): mixed => $simpleReleasePlan()['row_counts']['wp_options'], 6],

    'rollback plan status rolled back' => [static fn (): mixed => $rollbackPlan()['status'], 'rolled-back-to-savepoint'],
    'rollback plan preserves savepoint' => [static fn (): mixed => $rollbackPlan()['savepoint_preserved'], true],
    'rollback plan custom savepoint name' => [static fn (): mixed => $rollbackPlan()['savepoint'], 'wp_options_retry'],
    'rollback plan executed statements discarded' => [static fn (): mixed => $rollbackPlan()['executed_statements'], []],
    'rollback plan yielded returning discarded' => [static fn (): mixed => $rollbackPlan()['yielded_returning'], []],
    'rollback plan attempted statements retained' => [static fn (): mixed => count($rollbackPlan()['attempted_statements_before_rollback']), 2],
    'rollback plan attempted returning streams retained' => [static fn (): mixed => array_column($rollbackPlan()['attempted_returning_before_rollback'], 'action'), ['update', 'delete']],
    'rollback plan discarded returning count five' => [static fn (): mixed => $rollbackPlan()['discarded_returning_count'], 5],
    'rollback plan attempted changes five' => [static fn (): mixed => $rollbackPlan()['attempted_changes_before_rollback'], 5],
    'rollback plan committed changes zero' => [static fn (): mixed => $rollbackPlan()['changes'], 0],
    'rollback plan current source restores original ids' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'rollback plan pre rollback source had deleted siteurl and transients' => [static fn (): mixed => array_column($rollbackPlan()['pre_rollback_current_source_tables']['wp_options'], 'option_id'), [2, 5, 6, 7, 8, 9]],
    'rollback plan pre rollback source had row seven ok' => [static fn (): mixed => array_column($rollbackPlan()['pre_rollback_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'ok'],
    'rollback plan current source restores row seven null status' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'rollback plan current source restores transient feed' => [static fn (): mixed => array_column($rollbackPlan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'rollback plan changed tables empty after rollback' => [static fn (): mixed => $rollbackPlan()['savepoint_changed_tables'], []],
    'rollback plan next source equals savepoint image' => [static fn (): mixed => $rollbackPlan()['next_source_tables'], $rollbackPlan()['savepoint_image_tables']],
    'rollback plan dependency records rollback discard' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-discards-rowvalue-returning-streams', $rollbackPlan()['dependencies'], true), true],

    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext156($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext156($tables, [$releaseSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext156(['wp_options' => ['bad']], [$releaseSql], $unique), InvalidArgumentException::class],
    'malformed negative rollback ordinal rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext156($tables, [$releaseSql], $unique, 'bad', 'option_id', -1), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next156 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
