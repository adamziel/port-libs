<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteLimitPlan.php';
require_once dirname(__DIR__) . '/src/SQLiteDatabase.php';
require_once dirname(__DIR__) . '/src/SQLiteSelectResult.php';
require_once dirname(__DIR__) . '/src/SQLiteUpdateDeleteReturningSql.php';
require_once dirname(__DIR__) . '/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan.php';

$rowstuple = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://two-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$metatuple = [
    ['meta_id' => 1, 'blog_id' => 2, 'option_name' => 'pending_theme', 'meta_key' => 'import_touch', 'priority' => 10],
    ['meta_id' => 2, 'blog_id' => 2, 'option_name' => 'pending_theme', 'meta_key' => 'import_touch', 'priority' => 20],
    ['meta_id' => 3, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'meta_key' => 'import_touch', 'priority' => 30],
    ['meta_id' => 4, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'meta_key' => 'import_touch', 'priority' => 40],
    ['meta_id' => 5, 'blog_id' => 1, 'option_name' => '_transient_feed', 'meta_key' => 'delete_touch', 'priority' => 50],
    ['meta_id' => 6, 'blog_id' => 1, 'option_name' => '_transient_feed', 'meta_key' => 'delete_touch', 'priority' => 60],
    ['meta_id' => 7, 'blog_id' => 4, 'option_name' => 'siteurl', 'meta_key' => 'delete_touch', 'priority' => 70],
    ['meta_id' => 8, 'blog_id' => 4, 'option_name' => 'siteurl', 'meta_key' => 'delete_touch', 'priority' => 80],
    ['meta_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'meta_key' => 'retry_touch', 'priority' => 90],
    ['meta_id' => 10, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'meta_key' => 'retry_touch', 'priority' => 100],
];

$tablestuple = ['wp_options' => $rowstuple, 'wp_optionmeta' => $metatuple];
$uniquetuple = [['blog_id', 'option_name']];

$attemptUpdatetuple = "UPDATE wp_options SET (status, option_value, bytes) = ('attempttuple', option_value || ':attempttuple', bytes + 2) WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'import_touch' ORDER BY priority LIMIT -1 OFFSET 1) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'import_touch') AS touched_tuple ORDER BY option_id";
$attemptDeletetuple = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'delete_touch' ORDER BY priority) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'delete_touch') AS deleted_tuple ORDER BY option_id DESC";
$retryUpdatetuple = "UPDATE wp_options SET (status, option_value, bytes) = ('retrytuple', option_value || ':retrytuple', bytes + 1) WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'import_touch' ORDER BY priority) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id DESC";
$retryDeletetuple = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (SELECT DISTINCT blog_id, option_name FROM wp_optionmeta WHERE meta_key = 'retry_touch' ORDER BY priority) RETURNING option_id, blog_id, option_name, status ORDER BY option_id";

$attemptUpdateResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptUpdatetuple, $tablestuple, 'option_id', $uniquetuple);
$attemptDeleteResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($attemptDeletetuple, $attemptUpdateResulttuple()['tables'], 'option_id', $uniquetuple);
$retryUpdateResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdatetuple, $tablestuple, 'option_id', $uniquetuple);
$retryDeleteResulttuple = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDeletetuple, $retryUpdateResulttuple()['tables'], 'option_id', $uniquetuple);
$plantuple = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctTupleSavepointRollback(
    $tablestuple,
    [$attemptUpdatetuple, $attemptDeletetuple],
    [$retryUpdatetuple, $retryDeletetuple],
    $uniquetuple,
);
$customPlantuple = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctTupleSavepointRollback(
    $tablestuple,
    [$attemptUpdatetuple],
    [$retryUpdatetuple],
    $uniquetuple,
    'wp_custom_distinct_tuple_tuple',
);

$casestuple = [
    'parser attempt update distinct subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdatetuple)['where'] ?? '', 'SELECT DISTINCT'), true],
    'parser attempt update offset retained' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptUpdatetuple)['where'] ?? '', 'OFFSET 1'), true],
    'parser attempt delete distinct subquery' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($attemptDeletetuple)['where'] ?? '', 'SELECT DISTINCT'), true],
    'parser retry update order desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdatetuple)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry delete returning' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDeletetuple)['returning'], 'option_id, blog_id, option_name, status'],
    'attempt update selected after distinct offset' => [static fn (): mixed => $attemptUpdateResulttuple()['plan']->selectedIds, [8]],
    'attempt update returning id' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['returning'], 'option_id'), [8]],
    'attempt update tuple flag' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['returning'], 'touched_tuple'), [1]],
    'attempt update row eight changed' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['tables']['wp_options'], 'status', 'option_id')[8], 'attempttuple'],
    'attempt update row seven skipped by offset distinct' => [static fn (): mixed => array_column($attemptUpdateResulttuple()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'attempt delete selected distinct ids' => [static fn (): mixed => $attemptDeleteResulttuple()['plan']->selectedIds, [10, 3]],
    'attempt delete returning order' => [static fn (): mixed => array_column($attemptDeleteResulttuple()['returning'], 'option_id'), [3, 10]],
    'attempt delete tuple flags' => [static fn (): mixed => array_column($attemptDeleteResulttuple()['returning'], 'deleted_tuple'), [1, 1]],
    'attempt delete removes transient and site four' => [static fn (): mixed => array_intersect([3, 10], array_column($attemptDeleteResulttuple()['tables']['wp_options'], 'option_id')), []],
    'retry update selected distinct ids' => [static fn (): mixed => $retryUpdateResulttuple()['plan']->selectedIds, [8, 7]],
    'retry update returning order' => [static fn (): mixed => array_column($retryUpdateResulttuple()['returning'], 'option_id'), [7, 8]],
    'retry update row seven restored then changed' => [static fn (): mixed => array_column($retryUpdateResulttuple()['tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retrytuple'],
    'retry update row eight retry only' => [static fn (): mixed => array_column($retryUpdateResulttuple()['tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retrytuple'],
    'retry delete selected distinct id' => [static fn (): mixed => $retryDeleteResulttuple()['plan']->selectedIds, [9]],
    'retry delete returning id' => [static fn (): mixed => array_column($retryDeleteResulttuple()['returning'], 'option_id'), [9]],
    'retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResulttuple()['tables']['wp_options'], 'option_id'), [1, 2, 3, 4, 5, 6, 7, 8, 10]],

    'plan status' => [static fn (): mixed => $plantuple()['status'], 'rowvalue-update-delete-returning-distinct-tuple-savepoint-current-source'],
    'plan savepoint' => [static fn (): mixed => $plantuple()['savepoint'], 'wp_options_rowvalue_distinct_tuple'],
    'plan distinct flag' => [static fn (): mixed => $plantuple()['distinct_tuple_subquery_deduped'], true],
    'plan rollback flag' => [static fn (): mixed => $plantuple()['rollback_to_savepoint_restores_distinct_tuple_source'], true],
    'plan retry image flag' => [static fn (): mixed => $plantuple()['retry_reads_savepoint_image'], true],
    'plan savepoint active flag' => [static fn (): mixed => $plantuple()['savepoint_remains_active'], true],
    'plan savepoint image original rows' => [static fn (): mixed => $plantuple()['savepoint_image_tables'], $tablestuple],
    'plan attempt row eight changed' => [static fn (): mixed => array_column($plantuple()['attempt_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'attempttuple'],
    'plan attempt row three deleted' => [static fn (): mixed => in_array(3, array_column($plantuple()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan attempt row ten deleted' => [static fn (): mixed => in_array(10, array_column($plantuple()['attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores row three' => [static fn (): mixed => in_array(3, array_column($plantuple()['rollback_current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan rollback restores row eight status' => [static fn (): mixed => array_column($plantuple()['rollback_current_source_tables']['wp_options'], 'status', 'option_id')[8], 'queued'],
    'plan current row seven retry only' => [static fn (): mixed => array_column($plantuple()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retrytuple'],
    'plan current row eight retry only' => [static fn (): mixed => array_column($plantuple()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retrytuple'],
    'plan current row nine deleted by retry' => [static fn (): mixed => in_array(9, array_column($plantuple()['current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan current row three restored after attempt rollback' => [static fn (): mixed => in_array(3, array_column($plantuple()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plantuple()['next_source_tables'], $plantuple()['current_source_tables']],
    'plan attempt phases' => [static fn (): mixed => array_column($plantuple()['attempt_statements'], 'phase'), ['distinct-tuple-attempt-before-rollback', 'distinct-tuple-attempt-before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plantuple()['retry_statements'], 'phase'), ['distinct-tuple-retry-after-rollback', 'distinct-tuple-retry-after-rollback']],
    'plan attempt selected ids' => [static fn (): mixed => array_column($plantuple()['attempt_statements'], 'selected_ids'), [[8], [10, 3]]],
    'plan retry selected ids' => [static fn (): mixed => array_column($plantuple()['retry_statements'], 'selected_ids'), [[8, 7], [9]]],
    'plan attempt source row eight original' => [static fn (): mixed => $plantuple()['attempt_statements'][0]['source_rows'][0]['option_value'], 'rules'],
    'plan retry source rows original' => [static fn (): mixed => array_column($plantuple()['retry_statements'][0]['source_rows'], 'option_value'), ['theme', 'rules']],
    'plan suppressed rows ids' => [static fn (): mixed => array_column($plantuple()['suppressed_attempt_rows'], 'option_id'), [8, 3, 10]],
    'plan retry rows ids' => [static fn (): mixed => array_column($plantuple()['retry_rows'], 'option_id'), [7, 8, 9]],
    'plan suppressed count' => [static fn (): mixed => $plantuple()['suppressed_returning_count'], 3],
    'plan retry count' => [static fn (): mixed => $plantuple()['retry_returning_count'], 3],
    'plan attempt changes' => [static fn (): mixed => $plantuple()['attempt_change_count'], 3],
    'plan retry changes' => [static fn (): mixed => $plantuple()['retry_change_count'], 3],
    'plan changed tables' => [static fn (): mixed => $plantuple()['changed_tables_after_retry'], ['wp_options']],
    'plan options row count' => [static fn (): mixed => $plantuple()['row_counts']['wp_options'], 9],
    'plan meta row count' => [static fn (): mixed => $plantuple()['row_counts']['wp_optionmeta'], 10],
    'plan receipt suppressed ids' => [static fn (): mixed => $plantuple()['tuple_source_receipt']['suppressed_ids'], [8, 3, 10]],
    'plan receipt retry ids' => [static fn (): mixed => $plantuple()['tuple_source_receipt']['retry_ids'], [7, 8, 9]],
    'plan dependency distinct' => [static fn (): mixed => in_array('sqlite-rowvalue-distinct-subquery-tuples', $plantuple()['dependencies'], true), true],
    'plan dependency wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-distinct-optionmeta-savepoint', $plantuple()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plantuple()['dependency_closure'], 'no new support component'), true],
    'plan non overlap mentions limit offset' => [static fn (): mixed => str_contains($plantuple()['non_overlap'], 'LIMIT -1 OFFSET'), true],
    'custom savepoint' => [static fn (): mixed => $customPlantuple()['savepoint'], 'wp_custom_distinct_tuple_tuple'],
    'custom suppressed count' => [static fn (): mixed => $customPlantuple()['suppressed_returning_count'], 1],
    'custom retry count' => [static fn (): mixed => $customPlantuple()['retry_returning_count'], 2],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctTupleSavepointRollback($tablestuple, [], [$retryUpdatetuple], $uniquetuple), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctTupleSavepointRollback($tablestuple, [$attemptUpdatetuple], [], $uniquetuple), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctTupleSavepointRollback($tablestuple, [$attemptUpdatetuple], [$retryUpdatetuple], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctTupleSavepointRollback($tablestuple, [$attemptUpdatetuple], [$retryUpdatetuple], $uniquetuple, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeDistinctTupleSavepointRollback(['wp_options' => ['bad']], [$attemptUpdatetuple], [$retryUpdatetuple], $uniquetuple), InvalidArgumentException::class],
];

$tests = [];
foreach ($casestuple as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source distinct tuple ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
