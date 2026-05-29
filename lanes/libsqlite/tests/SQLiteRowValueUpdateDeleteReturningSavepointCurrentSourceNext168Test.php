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
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$outerSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':outer168', 'outer', option_value || ':outer168', bytes + 2) WHERE option_id IN (7, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('outer', 'pending_theme:outer168') AS pending_outer ORDER BY option_id";
$ignoreSql = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'ignored', option_value || ':ignored', bytes + 100) WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$replaceSql = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, option_value, bytes) = (4, 'siteurl', 'replace', option_value || ':replace', bytes + 50) WHERE option_id = 7 RETURNING option_id, blog_id, option_name, status, option_value, bytes";
$deleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";
$retryUpdateSql = "UPDATE wp_options SET (option_name, status, option_value, bytes) = (option_name || ':retry168', 'retry', option_value || ':retry168', bytes + 10) WHERE option_id IN (7, 8, 9) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('retry', 'pending_theme:outer168:retry168') AS pending_retry ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name, status ORDER BY option_id";

$parsedOuter = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($outerSql);
$parsedIgnore = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($ignoreSql);
$parsedReplace = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($replaceSql);
$outerOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerSql, $tables, 'option_id', $unique);
$ignoreAfterOuter = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $outerOnly()['tables'], 'option_id', $unique);
$replaceAfterIgnore = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $ignoreAfterOuter()['tables'], 'option_id', $unique);
$deleteAfterReplace = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($deleteSql, $replaceAfterIgnore()['tables'], 'option_id', $unique);
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext168(
    $tables,
    [$outerSql],
    [$ignoreSql, $replaceSql, $deleteSql],
    [$retryUpdateSql, $retryDeleteSql],
    $unique,
);

$cases = [
    'parser outer conflict action abort' => [static fn (): mixed => $parsedOuter()['conflict_action'], 'abort'],
    'parser outer row value assignment columns' => [static fn (): mixed => array_keys($parsedOuter()['assignments']), ['option_name', 'status', 'option_value', 'bytes']],
    'parser outer returning keeps row value predicate' => [static fn (): mixed => str_contains($parsedOuter()['returning'], 'pending_outer'), true],
    'parser ignore conflict action' => [static fn (): mixed => $parsedIgnore()['conflict_action'], 'ignore'],
    'parser ignore assignment columns' => [static fn (): mixed => array_keys($parsedIgnore()['assignments']), ['blog_id', 'option_name', 'status', 'option_value', 'bytes']],
    'parser replace conflict action' => [static fn (): mixed => $parsedReplace()['conflict_action'], 'replace'],
    'parser replace where id predicate' => [static fn (): mixed => $parsedReplace()['where'], 'option_id = 7'],

    'outer selected ids' => [static fn (): mixed => $outerOnly()['plan']->selectedIds, [7, 9]],
    'outer returning ids' => [static fn (): mixed => array_column($outerOnly()['returning'], 'option_id'), [7, 9]],
    'outer row seven predicate true' => [static fn (): mixed => $outerOnly()['returning'][0]['pending_outer'], 1],
    'outer row nine value updated' => [static fn (): mixed => array_column($outerOnly()['tables']['wp_options'], 'option_value', 'option_id')[9], 'rules:outer168'],
    'ignore selected ids' => [static fn (): mixed => $ignoreAfterOuter()['plan']->selectedIds, [8, 9]],
    'ignore mutation ids source order' => [static fn (): mixed => $ignoreAfterOuter()['plan']->mutationIds, [8, 9]],
    'ignore returns no rows because conflicts skipped' => [static fn (): mixed => $ignoreAfterOuter()['returning'], []],
    'ignore records two ignored rows' => [static fn (): mixed => array_column($ignoreAfterOuter()['ignored_rows'], 'option_id'), [8, 9]],
    'ignore restores row eight original name' => [static fn (): mixed => array_column($ignoreAfterOuter()['tables']['wp_options'], 'option_name', 'option_id')[8], 'orphaned_cache'],
    'ignore keeps outer row nine name' => [static fn (): mixed => array_column($ignoreAfterOuter()['tables']['wp_options'], 'option_name', 'option_id')[9], 'rewrite_rules:outer168'],
    'replace returns row seven' => [static fn (): mixed => array_column($replaceAfterIgnore()['returning'], 'option_id'), [7]],
    'replace deletes conflicting row ten' => [static fn (): mixed => array_column($replaceAfterIgnore()['deleted_conflict_rows'], 'option_id'), [10]],
    'replace row seven takes siteurl key' => [static fn (): mixed => array_column($replaceAfterIgnore()['tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'delete after replace returns transients' => [static fn (): mixed => array_column($deleteAfterReplace()['returning'], 'option_id'), [3, 4]],

    'plan status' => [static fn (): mixed => $plan()['status'], 'outer-released-after-inner-rollback-to-retry-current-source-next168'],
    'plan outer savepoint name' => [static fn (): mixed => $plan()['outer_savepoint'], 'wp_options_outer_rowvalue_next168'],
    'plan inner savepoint name' => [static fn (): mixed => $plan()['inner_savepoint'], 'wp_options_inner_rowvalue_next168'],
    'plan rolled back to inner' => [static fn (): mixed => $plan()['rolled_back_to_inner_savepoint'], true],
    'plan inner savepoint preserved' => [static fn (): mixed => $plan()['inner_savepoint_preserved_after_rollback_to'], true],
    'plan inner released after retry' => [static fn (): mixed => $plan()['inner_released_after_retry'], true],
    'plan outer released' => [static fn (): mixed => $plan()['outer_released'], true],
    'plan outer image original' => [static fn (): mixed => $plan()['outer_savepoint_image_tables'], $tables],
    'plan inner image keeps outer row seven' => [static fn (): mixed => array_column($plan()['inner_savepoint_image_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:outer168'],
    'plan inner image keeps outer row nine' => [static fn (): mixed => array_column($plan()['inner_savepoint_image_tables']['wp_options'], 'status', 'option_id')[9], 'outer'],
    'plan attempted inner row seven replaced' => [static fn (): mixed => array_column($plan()['attempted_inner_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'siteurl'],
    'plan attempted inner row ten removed by replace' => [static fn (): mixed => in_array(10, array_column($plan()['attempted_inner_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempted inner transients deleted' => [static fn (): mixed => in_array(3, array_column($plan()['attempted_inner_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row seven outer value' => [static fn (): mixed => array_column($plan()['rollback_to_inner_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:outer168'],
    'plan rollback restores row ten' => [static fn (): mixed => array_column($plan()['rollback_to_inner_current_source_tables']['wp_options'], 'option_name', 'option_id')[10], 'siteurl'],
    'plan rollback restores transient ids' => [static fn (): mixed => array_column($plan()['rollback_to_inner_current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]],
    'plan outer statement phase' => [static fn (): mixed => $plan()['outer_statements'][0]['phase'], 'outer-before-inner'],
    'plan outer statement selected ids' => [static fn (): mixed => $plan()['outer_statements'][0]['selected_ids'], [7, 9]],
    'plan outer source rows original' => [static fn (): mixed => array_column($plan()['outer_statements'][0]['source_rows'], 'option_name'), ['pending_theme', 'rewrite_rules']],
    'plan yielded outer returning ids' => [static fn (): mixed => array_column($plan()['yielded_outer_returning'][0]['rows'], 'option_id'), [7, 9]],
    'plan yielded outer returning count' => [static fn (): mixed => $plan()['yielded_outer_returning_count'], 2],
    'plan inner attempt actions' => [static fn (): mixed => array_column($plan()['inner_attempt_statements'], 'action'), ['update', 'update', 'delete']],
    'plan inner attempt conflict actions' => [static fn (): mixed => array_column($plan()['inner_attempt_statements'], 'conflict_action'), ['ignore', 'replace', 'abort']],
    'plan inner ignore rows recorded' => [static fn (): mixed => array_column($plan()['inner_attempt_statements'][0]['ignored_rows'], 'option_id'), [8, 9]],
    'plan inner replace deleted row ten' => [static fn (): mixed => array_column($plan()['inner_attempt_statements'][1]['deleted_conflict_rows'], 'option_id'), [10]],
    'plan inner delete source rows stale' => [static fn (): mixed => array_column($plan()['inner_attempt_statements'][2]['source_rows'], 'option_name'), ['_transient_feed', '_transient_timeout_feed']],
    'plan discarded inner stream actions' => [static fn (): mixed => array_column($plan()['discarded_inner_returning'], 'action'), ['update', 'update', 'delete']],
    'plan discarded ignore stream empty' => [static fn (): mixed => $plan()['discarded_inner_returning'][0]['rows'], []],
    'plan discarded replace stream row seven' => [static fn (): mixed => array_column($plan()['discarded_inner_returning'][1]['rows'], 'option_id'), [7]],
    'plan discarded delete stream transients' => [static fn (): mixed => array_column($plan()['discarded_inner_returning'][2]['rows'], 'option_id'), [3, 4]],
    'plan discarded inner returning count' => [static fn (): mixed => $plan()['discarded_inner_returning_count'], 3],
    'plan discarded inner changes include replace delete conflict' => [static fn (): mixed => $plan()['discarded_inner_changes'], 4],
    'plan retry actions' => [static fn (): mixed => array_column($plan()['inner_retry_statements'], 'action'), ['update', 'delete']],
    'plan retry phases' => [static fn (): mixed => array_column($plan()['inner_retry_statements'], 'phase'), ['inner-after-rollback-to', 'inner-after-rollback-to']],
    'plan retry update source rows from inner image' => [static fn (): mixed => array_column($plan()['inner_retry_statements'][0]['source_rows'], 'option_name'), ['pending_theme:outer168', 'orphaned_cache', 'rewrite_rules:outer168']],
    'plan retry update returning ids' => [static fn (): mixed => array_column($plan()['yielded_inner_retry_returning'][0]['rows'], 'option_id'), [7, 8, 9]],
    'plan retry row seven predicate true' => [static fn (): mixed => $plan()['yielded_inner_retry_returning'][0]['rows'][0]['pending_retry'], 1],
    'plan retry row eight value from original cache' => [static fn (): mixed => $plan()['yielded_inner_retry_returning'][0]['rows'][1]['option_value'], 'cache:retry168'],
    'plan retry row nine value includes outer then retry' => [static fn (): mixed => $plan()['yielded_inner_retry_returning'][0]['rows'][2]['option_value'], 'rules:outer168:retry168'],
    'plan retry delete returns transients' => [static fn (): mixed => array_column($plan()['yielded_inner_retry_returning'][1]['rows'], 'option_id'), [3, 4]],
    'plan retry returning count' => [static fn (): mixed => $plan()['yielded_inner_retry_returning_count'], 5],
    'plan outer changes count' => [static fn (): mixed => $plan()['outer_changes'], 2],
    'plan changes after retry' => [static fn (): mixed => $plan()['changes_after_inner_retry'], 5],
    'plan total released changes' => [static fn (): mixed => $plan()['total_released_changes'], 7],
    'plan final ids omit deleted transients only' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9, 10]],
    'plan final row seven outer plus retry' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme:outer168:retry168'],
    'plan final row eight retry name' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_name', 'option_id')[8], 'orphaned_cache:retry168'],
    'plan final row nine outer plus retry status' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'retry'],
    'plan final row ten preserved after rollback' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'https://four.test'],
    'plan next source equals current' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan changed tables' => [static fn (): mixed => $plan()['changed_tables_after_release'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan()['row_counts']['wp_options'], 8],
    'plan dependency outer preserved' => [static fn (): mixed => in_array('sqlite-nested-savepoint-rollback-to-preserves-outer-rowvalue-returning-next168', $plan()['dependencies'], true), true],
    'plan dependency inner stream discarded' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-discards-inner-rollback-stream-next168', $plan()['dependencies'], true), true],
    'plan dependency retry reads image' => [static fn (): mixed => in_array('sqlite-rowvalue-retry-after-inner-rollback-reads-inner-savepoint-image-next168', $plan()['dependencies'], true), true],

    'malformed empty outer statements rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext168($tables, [], [$ignoreSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty inner attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext168($tables, [$outerSql], [], [$retryUpdateSql], $unique), InvalidArgumentException::class],
    'malformed empty inner retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext168($tables, [$outerSql], [$ignoreSql], [], $unique), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext168($tables, [$outerSql], [$ignoreSql], [$retryUpdateSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext168(['wp_options' => ['bad']], [$outerSql], [$ignoreSql], [$retryUpdateSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next168 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
