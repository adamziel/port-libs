<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://old.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'no', 'status' => 'staged', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$ignoreSql = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value) = (1, 'siteurl', option_name || ':ignored', option_value || ':ignored') WHERE option_id IN (8, 9) RETURNING option_id, blog_id, option_name, status, option_value ORDER BY option_id";
$replaceSql = "UPDATE OR REPLACE wp_options SET (blog_id, option_name, status, bytes) = (2, 'siteurl', option_name || ':replace', bytes + 100) WHERE option_id IN (7, 8) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$cleanupSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name ORDER BY option_id";
$rollbackSql = "UPDATE OR ROLLBACK wp_options SET (blog_id, option_name, status) = (1, 'siteurl', 'abort') WHERE option_id = 6 RETURNING option_id, blog_id, option_name, status";

$ignoreOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($ignoreSql, $tables, 'option_id', $unique);
$replaceOnly = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($replaceSql, $tables, 'option_id', $unique);
$commit = static fn (): array => SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan::execute($tables, [$ignoreSql, $replaceSql, $cleanupSql], $unique);
$rollback = static fn (): array => SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan::execute($tables, [$ignoreSql, $replaceSql, $rollbackSql], $unique);
$parsedIgnore = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($ignoreSql);
$parsedReplace = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($replaceSql);
$parsedRollback = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($rollbackSql);

$cases = [
    'ignore parser conflict action' => [static fn (): mixed => $parsedIgnore()['conflict_action'], 'ignore'],
    'ignore parser row-value assignment columns' => [static fn (): mixed => array_keys($parsedIgnore()['assignments']), ['blog_id', 'option_name', 'status', 'option_value']],
    'ignore parser row-value where' => [static fn (): mixed => $parsedIgnore()['where'], 'option_id IN (8, 9)'],
    'ignore parser returning list' => [static fn (): mixed => $parsedIgnore()['returning'], 'option_id, blog_id, option_name, status, option_value'],
    'ignore parser order column' => [static fn (): mixed => $parsedIgnore()['order_by'][0]['column'], 'option_id'],
    'replace parser conflict action' => [static fn (): mixed => $parsedReplace()['conflict_action'], 'replace'],
    'replace parser assignment bytes expression' => [static fn (): mixed => $parsedReplace()['assignments']['bytes'], 'bytes + 100'],
    'replace parser selected row-value constant' => [static fn (): mixed => $parsedReplace()['assignments']['option_name'], "'siteurl'"],
    'rollback parser conflict action' => [static fn (): mixed => $parsedRollback()['conflict_action'], 'rollback'],
    'rollback parser returning status' => [static fn (): mixed => $parsedRollback()['returning'], 'option_id, blog_id, option_name, status'],

    'ignore selected two rows' => [static fn (): mixed => $ignoreOnly()['plan']->selectedIds, [8, 9]],
    'ignore mutation ids include attempted rows' => [static fn (): mixed => $ignoreOnly()['plan']->mutationIds, [8, 9]],
    'ignore returning has no rows for skipped conflicts' => [static fn (): mixed => $ignoreOnly()['returning'], []],
    'ignore rows list records both skipped attempted rows' => [static fn (): mixed => array_column($ignoreOnly()['ignored_rows'], 'option_id'), [8, 9]],
    'ignore conflict keys both siteurl' => [static fn (): mixed => array_column($ignoreOnly()['conflicts'], 'key'), ['1|siteurl', '1|siteurl']],
    'ignore conflict peer is row one' => [static fn (): mixed => array_column($ignoreOnly()['conflicts'], 'conflicting_row_ids'), [[1], [1]]],
    'ignore current row eight restored' => [static fn (): mixed => array_column($ignoreOnly()['tables']['wp_options'], 'option_name', 'option_id')[8], 'orphaned_cache'],
    'ignore current row nine restored' => [static fn (): mixed => array_column($ignoreOnly()['tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'ignore does not delete conflict rows' => [static fn (): mixed => $ignoreOnly()['deleted_conflict_rows'], []],

    'replace selected row ids' => [static fn (): mixed => $replaceOnly()['plan']->selectedIds, [7, 8]],
    'replace returning has both updated rows' => [static fn (): mixed => array_column($replaceOnly()['returning'], 'option_id'), [7, 8]],
    'replace first row status uses source option name' => [static fn (): mixed => $replaceOnly()['returning'][0]['status'], 'pending_theme:replace'],
    'replace second row status uses source option name' => [static fn (): mixed => $replaceOnly()['returning'][1]['status'], 'orphaned_cache:replace'],
    'replace bytes add per source row' => [static fn (): mixed => array_column($replaceOnly()['returning'], 'bytes'), [107, 105]],
    'replace deletes original siteurl then prior replacement' => [static fn (): mixed => array_column($replaceOnly()['deleted_conflict_rows'], 'option_id'), [5, 7]],
    'replace conflicts include two collisions' => [static fn (): mixed => count($replaceOnly()['conflicts']), 2],
    'replace conflict peer ids chain through current source' => [static fn (): mixed => array_column($replaceOnly()['conflicts'], 'conflicting_row_ids'), [[5], [7]]],
    'replace final row ids remove row five and seven' => [static fn (): mixed => array_column($replaceOnly()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 6, 8, 9]],
    'replace final row eight becomes network siteurl' => [static fn (): mixed => array_column($replaceOnly()['tables']['wp_options'], 'option_name', 'option_id')[8], 'siteurl'],

    'commit status released' => [static fn (): mixed => $commit()['status'], 'released'],
    'commit not rolled back' => [static fn (): mixed => $commit()['rolled_back'], false],
    'commit transaction not aborted' => [static fn (): mixed => $commit()['transaction_aborted'], false],
    'commit executed three statements' => [static fn (): mixed => count($commit()['executed_statements']), 3],
    'commit conflict actions recorded' => [static fn (): mixed => array_column($commit()['executed_statements'], 'conflict_action'), ['ignore', 'replace', 'abort']],
    'commit ignore yielding empty row stream preserved' => [static fn (): mixed => $commit()['yielded_returning'][0]['rows'], []],
    'commit replace yielding row eight only after ignore preserved rows' => [static fn (): mixed => array_column($commit()['yielded_returning'][1]['rows'], 'option_id'), [7, 8]],
    'commit cleanup yielding transients' => [static fn (): mixed => array_column($commit()['yielded_returning'][2]['rows'], 'option_id'), [3, 4]],
    'commit ignored diagnostics include row ids' => [static fn (): mixed => array_column(array_column($commit()['ignored_rows'], 'row'), 'option_id'), [8, 9]],
    'commit deleted conflict diagnostics include row five and seven' => [static fn (): mixed => array_column(array_column($commit()['deleted_conflict_rows'], 'row'), 'option_id'), [5, 7]],
    'commit conflicts include ignore and replace ordinals' => [static fn (): mixed => array_column($commit()['conflicts'], 'ordinal'), [0, 0, 1, 1]],
    'commit final row ids' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 6, 8, 9]],
    'commit final row eight is replacement siteurl' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'orphaned_cache:replace'],
    'commit final row nine ignored remains queued' => [static fn (): mixed => array_column($commit()['current_source_tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'commit savepoint image preserves original row five' => [static fn (): mixed => array_column($commit()['savepoint_image_tables']['wp_options'], 'option_name', 'option_id')[5], 'siteurl'],
    'commit next source equals current source' => [static fn (): mixed => $commit()['next_source_tables'], $commit()['current_source_tables']],
    'commit changes count includes returning rows and replacement deletes' => [static fn (): mixed => $commit()['changes'], 6],
    'commit attempted changes equals changes' => [static fn (): mixed => $commit()['attempted_changes'], 6],
    'commit row count after release' => [static fn (): mixed => $commit()['row_counts']['wp_options'], 5],
    'commit dependency marks ignore' => [static fn (): mixed => in_array('sqlite-update-or-ignore-skips-returning-row', $commit()['dependencies'], true), true],
    'commit dependency marks replace' => [static fn (): mixed => in_array('sqlite-update-or-replace-conflict-delete-before-returning', $commit()['dependencies'], true), true],

    'rollback status transaction rolled back' => [static fn (): mixed => $rollback()['status'], 'transaction-rolled-back'],
    'rollback flag true' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback transaction aborted true' => [static fn (): mixed => $rollback()['transaction_aborted'], true],
    'rollback statement ordinal is rollback statement' => [static fn (): mixed => $rollback()['rollback_statement_ordinal'], 2],
    'rollback reason records OR ROLLBACK' => [static fn (): mixed => $rollback()['rollback_reason'], 'SQLite UPDATE RETURNING unique constraint failed: blog_id,option_name=1|siteurl using OR ROLLBACK'],
    'rollback current source restores original ids' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 9]],
    'rollback current source restores row five' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[5], 'siteurl'],
    'rollback current source restores row seven pending' => [static fn (): mixed => array_column($rollback()['current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'rollback yielded returning cleared by transaction abort' => [static fn (): mixed => $rollback()['yielded_returning'], []],
    'rollback ignored rows cleared by transaction abort' => [static fn (): mixed => $rollback()['ignored_rows'], []],
    'rollback deleted conflicts cleared by transaction abort' => [static fn (): mixed => $rollback()['deleted_conflict_rows'], []],
    'rollback conflicts cleared by transaction abort' => [static fn (): mixed => $rollback()['conflicts'], []],
    'rollback next source still records attempted prior replace' => [static fn (): mixed => array_column($rollback()['next_source_tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 6, 8, 9]],
    'rollback attempted changes from prior successful statements' => [static fn (): mixed => $rollback()['attempted_changes'], 4],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback dependency marks transaction abort' => [static fn (): mixed => in_array('sqlite-update-or-rollback-aborts-savepoint-transaction', $rollback()['dependencies'], true), true],

    'malformed empty statements rejected' => [static fn (): mixed => SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan::execute($tables, [], $unique), InvalidArgumentException::class],
    'malformed empty unique constraints rejected' => [static fn (): mixed => SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan::execute($tables, [$ignoreSql], []), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueConflictReturningSavepointCurrentSourceNextPlan::execute(['wp_options' => ['bad']], [$ignoreSql], $unique), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue conflict returning savepoint current source next138 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
