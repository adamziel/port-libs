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
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];

$literalUpdateSql = "UPDATE wp_options SET (status, option_value) = ('draft WHERE literal', option_value || ' RETURNING literal') WHERE option_id IN (7, 8) RETURNING option_id, status, option_value || ' ORDER BY literal' AS marker, (status, option_name) IS ('draft WHERE literal', option_name) AS tuple_ok ORDER BY option_id";
$literalDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name || ' LIMIT literal' AS marker ORDER BY option_id LIMIT 1";
$failSql = "UPDATE OR FAIL wp_options SET (blog_id, option_name, status, option_value) = (4, 'siteurl', option_name || ' WHERE failed', option_value || ' RETURNING failed') WHERE option_id IN (8, 9) RETURNING option_id, status, option_value || ' ORDER BY failed' AS marker ORDER BY option_id DESC";
$retrySql = "UPDATE wp_options SET (status, option_value) = ('retry LIMIT literal', option_value || ' WHERE retry') WHERE option_id IN (8, 9) RETURNING option_id, status, option_value || ' RETURNING retry' AS marker ORDER BY option_id";
$retryDeleteSql = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (1, '_transient_timeout_feed')) RETURNING option_id, option_name || ' ORDER BY retry' AS marker ORDER BY option_id";

$parsedLiteralUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($literalUpdateSql);
$parsedLiteralDelete = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($literalDeleteSql);
$literalUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($literalUpdateSql, $tables, 'option_id', $unique);
$literalDeleteAfterUpdate = static function () use ($literalUpdateSql, $literalDeleteSql, $tables, $unique): array {
    $updated = SQLiteUpdateDeleteReturningSql::execute($literalUpdateSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($literalDeleteSql, $updated['tables'], 'option_id', $unique);
};
$plan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeConflictRetrySavepointBatch(
    $tables,
    [$literalUpdateSql, $literalDeleteSql],
    [$retrySql, $retryDeleteSql],
    $unique,
    'wp_options_rowvalue_literal_clause_retry_next167',
);
$failPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeConflictRetrySavepointBatch(
    $tables,
    [$failSql, $literalDeleteSql],
    [$retrySql],
    $unique,
    'wp_options_rowvalue_literal_fail_retry_next167',
);

$cases = [
    'parser ignores WHERE inside row-value assignment literal' => [static fn (): mixed => $parsedLiteralUpdate()['assignments']['status'], "'draft WHERE literal'"],
    'parser ignores RETURNING inside row-value assignment literal' => [static fn (): mixed => $parsedLiteralUpdate()['assignments']['option_value'], "option_value || ' RETURNING literal'"],
    'parser keeps real where clause' => [static fn (): mixed => $parsedLiteralUpdate()['where'], 'option_id IN (7, 8)'],
    'parser keeps full returning text with order literal' => [static fn (): mixed => str_contains($parsedLiteralUpdate()['returning'], "' ORDER BY literal'"), true],
    'parser cuts real order by after returning literal' => [static fn (): mixed => $parsedLiteralUpdate()['order_by'][0]['column'], 'option_id'],
    'parser update has no limit' => [static fn (): mixed => $parsedLiteralUpdate()['limit'], null],
    'parser delete keeps returning literal before real order by' => [static fn (): mixed => $parsedLiteralDelete()['returning'], "option_id, option_name || ' LIMIT literal' AS marker"],
    'parser delete order by survives limit literal' => [static fn (): mixed => $parsedLiteralDelete()['order_by'][0]['column'], 'option_id'],
    'parser delete real limit one' => [static fn (): mixed => $parsedLiteralDelete()['limit'], 1],
    'parser delete offset zero' => [static fn (): mixed => $parsedLiteralDelete()['offset'], 0],

    'literal update selected ids' => [static fn (): mixed => $literalUpdate()['plan']->selectedIds, [7, 8]],
    'literal update mutation ids table order' => [static fn (): mixed => $literalUpdate()['plan']->mutationIds, [7, 8]],
    'literal update returning ids' => [static fn (): mixed => array_column($literalUpdate()['returning'], 'option_id'), [7, 8]],
    'literal update returning statuses include where word' => [static fn (): mixed => array_column($literalUpdate()['returning'], 'status'), ['draft WHERE literal', 'draft WHERE literal']],
    'literal update returning marker preserves order text' => [static fn (): mixed => array_column($literalUpdate()['returning'], 'marker'), ['theme RETURNING literal ORDER BY literal', 'rules RETURNING literal ORDER BY literal']],
    'literal update tuple expression true for both rows' => [static fn (): mixed => array_column($literalUpdate()['returning'], 'tuple_ok'), [1, 1]],
    'literal update final row seven status' => [static fn (): mixed => array_column($literalUpdate()['tables']['wp_options'], 'status', 'option_id')[7], 'draft WHERE literal'],
    'literal update final row eight option value' => [static fn (): mixed => array_column($literalUpdate()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules RETURNING literal'],
    'literal update unrelated siteurl preserved' => [static fn (): mixed => array_column($literalUpdate()['tables']['wp_options'], 'option_name', 'option_id')[1], 'siteurl'],
    'literal update no conflicts' => [static fn (): mixed => $literalUpdate()['conflicts'], []],

    'literal delete after update selected limited id' => [static fn (): mixed => $literalDeleteAfterUpdate()['plan']->selectedIds, [3]],
    'literal delete returning marker keeps limit text' => [static fn (): mixed => $literalDeleteAfterUpdate()['returning'][0]['marker'], '_transient_feed LIMIT literal'],
    'literal delete removes only first transient due real limit' => [static fn (): mixed => array_column($literalDeleteAfterUpdate()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],
    'literal delete leaves timeout transient' => [static fn (): mixed => array_column($literalDeleteAfterUpdate()['tables']['wp_options'], 'option_name', 'option_id')[4], '_transient_timeout_feed'],

    'plan status clean retry' => [static fn (): mixed => $plan()['status'], 'released-after-clean-retry'],
    'plan savepoint name' => [static fn (): mixed => $plan()['savepoint'], 'wp_options_rowvalue_literal_clause_retry_next167'],
    'plan pre rollback actions update delete' => [static fn (): mixed => array_column($plan()['pre_rollback_statements'], 'action'), ['update', 'delete']],
    'plan pre update returning markers keep literal clauses' => [static fn (): mixed => array_column($plan()['discarded_returning'][0]['rows'], 'marker'), ['theme RETURNING literal ORDER BY literal', 'rules RETURNING literal ORDER BY literal']],
    'plan pre delete returning marker keeps literal limit' => [static fn (): mixed => $plan()['discarded_returning'][1]['rows'][0]['marker'], '_transient_feed LIMIT literal'],
    'plan discarded returning count three' => [static fn (): mixed => $plan()['discarded_returning_count'], 3],
    'plan rollback source restores row seven' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[7], null],
    'plan rollback source restores transient feed' => [static fn (): mixed => array_column($plan()['rollback_to_current_source_tables']['wp_options'], 'option_name', 'option_id')[3], '_transient_feed'],
    'plan retry update source rows restored' => [static fn (): mixed => array_column($plan()['retry_statements'][0]['source_rows'], 'option_value'), ['rules', 'plugin']],
    'plan retry update returning markers keep returning text' => [static fn (): mixed => array_column($plan()['yielded_returning'][0]['rows'], 'marker'), ['rules WHERE retry RETURNING retry', 'plugin WHERE retry RETURNING retry']],
    'plan retry delete returning markers keep order text' => [static fn (): mixed => array_column($plan()['yielded_returning'][1]['rows'], 'marker'), ['_transient_feed ORDER BY retry', '_transient_timeout_feed ORDER BY retry']],
    'plan yielded returning count four' => [static fn (): mixed => $plan()['yielded_returning_count'], 4],
    'plan final row ids omit transients' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_id'), [1, 2, 5, 6, 7, 8, 9]],
    'plan final row eight retry status has limit word' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry LIMIT literal'],
    'plan final row nine retry value has where word' => [static fn (): mixed => array_column($plan()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin WHERE retry'],
    'plan next source equals current source' => [static fn (): mixed => $plan()['next_source_tables'], $plan()['current_source_tables']],
    'plan dependency retry restored source' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-retry-reads-restored-current-source-next161', $plan()['dependencies'], true), true],

    'fail plan status rollback retry' => [static fn (): mixed => $failPlan()['status'], 'failed-rolled-back-to-savepoint-retried'],
    'fail plan stops before delete' => [static fn (): mixed => array_column($failPlan()['pre_rollback_statements'], 'action'), ['update']],
    'fail plan failed conflict row nine' => [static fn (): mixed => $failPlan()['failed_conflict']['row_id'], 9],
    'fail plan failed conflict peer row eight' => [static fn (): mixed => $failPlan()['failed_conflict']['conflicting_row_ids'], [8]],
    'fail plan discarded returning row eight only' => [static fn (): mixed => array_column($failPlan()['discarded_returning'][0]['rows'], 'option_id'), [8]],
    'fail plan discarded marker keeps failed order text' => [static fn (): mixed => $failPlan()['discarded_returning'][0]['rows'][0]['marker'], 'rules RETURNING failed ORDER BY failed'],
    'fail plan failed source row eight changed before rollback' => [static fn (): mixed => array_column($failPlan()['failed_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'rewrite_rules WHERE failed'],
    'fail plan failed source row nine restored after conflict' => [static fn (): mixed => array_column($failPlan()['failed_current_source_tables']['wp_options'], 'status', 'option_id')[9], 'queued'],
    'fail plan rollback source restores row eight' => [static fn (): mixed => array_column($failPlan()['rollback_to_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'fail plan retry returns rows eight nine' => [static fn (): mixed => array_column($failPlan()['yielded_returning'][0]['rows'], 'option_id'), [8, 9]],
    'fail plan retry starts from original plugin value' => [static fn (): mixed => $failPlan()['retry_statements'][0]['source_rows'][1]['option_value'], 'plugin'],
    'fail plan yielded returning count two' => [static fn (): mixed => $failPlan()['yielded_returning_count'], 2],
    'fail plan failed changes before rollback one' => [static fn (): mixed => $failPlan()['failed_changes_before_rollback_to'], 1],
    'fail plan changes after release two' => [static fn (): mixed => $failPlan()['changes_after_release'], 2],
    'fail plan dependency records fail discard' => [static fn (): mixed => in_array('sqlite-rollback-to-savepoint-discards-fail-returning-stream', $failPlan()['dependencies'], true), true],

    'malformed unterminated string still rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse("UPDATE wp_options SET status = 'draft WHERE broken WHERE option_id = 1 RETURNING option_id"), InvalidArgumentException::class],
    'malformed empty before rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeConflictRetrySavepointBatch($tables, [], [$retrySql], $unique), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeConflictRetrySavepointBatch($tables, [$literalUpdateSql], [], $unique), InvalidArgumentException::class],
    'malformed unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeConflictRetrySavepointBatch($tables, [$literalUpdateSql], [$retrySql], []), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next167 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
