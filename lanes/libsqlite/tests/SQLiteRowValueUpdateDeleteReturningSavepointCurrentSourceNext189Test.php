<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows189 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://network.test'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 26, 'option_value' => 'https://network-home.test'],
    ['option_id' => 7, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 9, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 30, 'option_value' => 'https://four.test'],
];

$tables189 = ['wp_options' => $rows189];
$unique189 = [['blog_id', 'option_name']];

$outerUpdate189 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer189', option_value || ':outer189', bytes + 1) WHERE (blog_id, option_name) NOT BETWEEN (2, 'a') AND (3, 'zzzz') RETURNING * ORDER BY option_id";
$innerIgnore189 = "UPDATE OR IGNORE wp_options SET (blog_id, option_name, status, option_value, bytes) = (1, 'siteurl', 'ignored189', option_value || ':ignored189', bytes + 9) WHERE option_id = 7 RETURNING *";
$innerDelete189 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'pending_theme'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT BETWEEN (2, 'a') AND (3, 'zzzz') AS outside_network ORDER BY option_id LIMIT 4";
$retryUpdate189 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry189', option_value || ':retry189', bytes + 5) WHERE (blog_id, option_name) NOT BETWEEN (1, 'a') AND (2, 'zzzz') RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete189 = "DELETE FROM wp_options WHERE (blog_id, option_name) NOT IN (VALUES (1, 'siteurl'), (2, 'siteurl'), (2, 'home'), (2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch'), (4, 'siteurl')) RETURNING * ORDER BY option_id";

$outerUpdateResult189 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate189, $tables189, 'option_id', $unique189);
$innerIgnoreResult189 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerIgnore189, $outerUpdateResult189()['tables'], 'option_id', $unique189);
$innerDeleteResult189 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete189, $innerIgnoreResult189()['tables'], 'option_id', $unique189);
$retryUpdateResult189 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate189, $outerUpdateResult189()['tables'], 'option_id', $unique189);
$retryDeleteResult189 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete189, $retryUpdateResult189()['tables'], 'option_id', $unique189);
$plan189 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint(
    $tables189,
    [$outerUpdate189],
    [$innerIgnore189, $innerDelete189],
    [$retryUpdate189, $retryDelete189],
    $unique189,
);
$customPlan189 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint(
    $tables189,
    [$outerUpdate189],
    [$innerDelete189],
    [$retryUpdate189],
    $unique189,
    'wp_outer_custom189',
    'wp_inner_custom189',
);

$cases189 = [
    'parser outer not between where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($outerUpdate189)['where'], "(blog_id, option_name) NOT BETWEEN (2, 'a') AND (3, 'zzzz')"],
    'parser outer returning star' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($outerUpdate189)['returning'], '*'],
    'parser inner ignore action' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerIgnore189)['conflict_action'], 'ignore'],
    'parser inner delete values list' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($innerDelete189)['where'] ?? '', 'VALUES'), true],
    'parser inner delete limit' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerDelete189)['limit'], 4],
    'parser retry not between where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate189)['where'], "(blog_id, option_name) NOT BETWEEN (1, 'a') AND (2, 'zzzz')"],
    'outer update selected ids outside network range' => [static fn (): mixed => $outerUpdateResult189()['plan']->selectedIds, [1, 2, 3, 4, 10]],
    'outer update returning star columns' => [static fn (): mixed => array_keys($outerUpdateResult189()['returning'][0]), ['option_id', 'blog_id', 'option_name', 'autoload', 'status', 'bytes', 'option_value']],
    'outer update returning ids' => [static fn (): mixed => array_column($outerUpdateResult189()['returning'], 'option_id'), [1, 2, 3, 4, 10]],
    'outer update row one value' => [static fn (): mixed => array_column($outerUpdateResult189()['tables']['wp_options'], 'option_value', 'option_id')[1], 'https://old.test:outer189'],
    'outer update leaves row seven null status' => [static fn (): mixed => array_column($outerUpdateResult189()['tables']['wp_options'], 'status', 'option_id')[7], null],
    'inner ignore selected row seven' => [static fn (): mixed => $innerIgnoreResult189()['plan']->selectedIds, [7]],
    'inner ignore returns no rows' => [static fn (): mixed => $innerIgnoreResult189()['returning'], []],
    'inner ignore records ignored row seven' => [static fn (): mixed => array_column($innerIgnoreResult189()['ignored_rows'], 'option_id'), [7]],
    'inner ignore keeps row seven key' => [static fn (): mixed => array_column($innerIgnoreResult189()['tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'inner delete selected first four non kept ids' => [static fn (): mixed => $innerDeleteResult189()['plan']->selectedIds, [2, 3, 4, 5]],
    'inner delete returning ids' => [static fn (): mixed => array_column($innerDeleteResult189()['returning'], 'option_id'), [2, 3, 4, 5]],
    'inner delete outside flags' => [static fn (): mixed => array_column($innerDeleteResult189()['returning'], 'outside_network'), [1, 1, 1, 0]],
    'inner delete removes home row two' => [static fn (): mixed => in_array(2, array_column($innerDeleteResult189()['tables']['wp_options'], 'option_id'), true), false],
    'retry update selected ids after rollback' => [static fn (): mixed => $retryUpdateResult189()['plan']->selectedIds, [3, 4, 8, 9, 10]],
    'retry update source starts from outer value row ten' => [static fn (): mixed => $retryUpdateResult189()['returning'][4]['option_value'], 'https://four.test:outer189:retry189'],
    'retry delete selected stale ids after retry' => [static fn (): mixed => $retryDeleteResult189()['plan']->selectedIds, [2, 3, 4]],
    'retry delete returning star has bytes' => [static fn (): mixed => array_key_exists('bytes', $retryDeleteResult189()['returning'][0]), true],

    'plan status' => [static fn (): mixed => $plan189()['status'], 'rowvalue-not-between-returning-star-rollback-retry-next189'],
    'plan outer savepoint' => [static fn (): mixed => $plan189()['outer_savepoint'], 'wp_options_rowvalue_not_between_outer_next189'],
    'plan inner savepoint' => [static fn (): mixed => $plan189()['inner_savepoint'], 'wp_options_rowvalue_not_between_inner_next189'],
    'plan rolled back to inner' => [static fn (): mixed => $plan189()['rolled_back_to_inner_savepoint'], true],
    'plan outer preserved after rollback' => [static fn (): mixed => $plan189()['outer_savepoint_preserved_after_inner_rollback_to'], true],
    'plan inner preserved after rollback' => [static fn (): mixed => $plan189()['inner_savepoint_preserved_after_rollback_to'], true],
    'plan savepoints released' => [static fn (): mixed => [$plan189()['inner_released_after_retry'], $plan189()['outer_released_after_inner_retry']], [true, true]],
    'plan outer image original rows' => [static fn (): mixed => $plan189()['outer_savepoint_image_tables'], $tables189],
    'plan outer current row one outer' => [static fn (): mixed => array_column($plan189()['outer_current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer189'],
    'plan inner image equals outer current' => [static fn (): mixed => $plan189()['inner_savepoint_image_tables'], $plan189()['outer_current_source_tables']],
    'plan inner attempt row two deleted' => [static fn (): mixed => in_array(2, array_column($plan189()['inner_attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan inner attempt row seven still pending' => [static fn (): mixed => array_column($plan189()['inner_attempt_current_source_tables']['wp_options'], 'option_name', 'option_id')[7], 'pending_theme'],
    'plan rollback restores row two outer' => [static fn (): mixed => array_column($plan189()['rollback_to_inner_current_source_tables']['wp_options'], 'status', 'option_id')[2], 'outer189'],
    'plan rollback restores row five' => [static fn (): mixed => array_column($plan189()['rollback_to_inner_current_source_tables']['wp_options'], 'option_name', 'option_id')[5], 'siteurl'],
    'plan current ids final' => [static fn (): mixed => array_column($plan189()['current_source_tables']['wp_options'], 'option_id'), [1, 5, 6, 7, 8, 9, 10]],
    'plan final row one outer preserved' => [static fn (): mixed => array_column($plan189()['current_source_tables']['wp_options'], 'status', 'option_id')[1], 'outer189'],
    'plan final row eight retry' => [static fn (): mixed => array_column($plan189()['current_source_tables']['wp_options'], 'status', 'option_id')[8], 'retry189'],
    'plan final row ten retry after outer' => [static fn (): mixed => array_column($plan189()['current_source_tables']['wp_options'], 'option_value', 'option_id')[10], 'https://four.test:outer189:retry189'],
    'plan next source equals current' => [static fn (): mixed => $plan189()['next_source_tables'], $plan189()['current_source_tables']],
    'plan outer statement phase' => [static fn (): mixed => $plan189()['outer_statements'][0]['phase'], 'outer-not-between-before-inner'],
    'plan inner phases' => [static fn (): mixed => array_column($plan189()['inner_attempt_statements'], 'phase'), ['inner-not-in-values-before-rollback', 'inner-not-in-values-before-rollback']],
    'plan retry phases' => [static fn (): mixed => array_column($plan189()['retry_statements'], 'phase'), ['retry-not-between-after-rollback', 'retry-not-between-after-rollback']],
    'plan inner ignored rows' => [static fn (): mixed => array_column($plan189()['inner_attempt_statements'][0]['ignored_rows'], 'option_id'), [7]],
    'plan inner delete source rows include outer home' => [static fn (): mixed => array_column($plan189()['inner_attempt_statements'][1]['source_rows'], 'status'), ['outer189', 'outer189', 'outer189', 'live']],
    'plan suppressed stream ids' => [static fn (): mixed => [array_column($plan189()['suppressed_by_rollback_returning'][0]['rows'], 'option_id'), array_column($plan189()['suppressed_by_rollback_returning'][1]['rows'], 'option_id')], [[], [2, 3, 4, 5]]],
    'plan retry update source rows restored' => [static fn (): mixed => array_column($plan189()['retry_statements'][0]['source_rows'], 'option_id'), [3, 4, 8, 9, 10]],
    'plan retry delete source rows restored stale' => [static fn (): mixed => array_column($plan189()['retry_statements'][1]['source_rows'], 'option_id'), [2, 3, 4]],
    'plan outer returning count' => [static fn (): mixed => $plan189()['outer_yielded_returning_count'], 5],
    'plan inner attempt returning count' => [static fn (): mixed => $plan189()['inner_attempt_returning_count'], 4],
    'plan suppressed count' => [static fn (): mixed => $plan189()['suppressed_by_rollback_count'], 4],
    'plan retry count' => [static fn (): mixed => $plan189()['yielded_after_retry_count'], 8],
    'plan outer changes preserved' => [static fn (): mixed => $plan189()['outer_changes_preserved'], 5],
    'plan inner attempted changes' => [static fn (): mixed => $plan189()['inner_attempted_changes_before_rollback_to'], 4],
    'plan retry changes' => [static fn (): mixed => $plan189()['retry_changes_after_release'], 8],
    'plan changed tables' => [static fn (): mixed => $plan189()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan189()['row_counts']['wp_options'], 7],
    'plan dependency not between' => [static fn (): mixed => in_array('sqlite-rowvalue-not-between-update-returning-star-next189', $plan189()['dependencies'], true), true],
    'plan dependency not in values rollback' => [static fn (): mixed => in_array('sqlite-rowvalue-not-in-values-delete-returning-rollback-next189', $plan189()['dependencies'], true), true],
    'plan dependency retry source' => [static fn (): mixed => in_array('sqlite-rowvalue-retry-after-inner-rollback-current-source-next189', $plan189()['dependencies'], true), true],
    'custom plan savepoints' => [static fn (): mixed => [$customPlan189()['outer_savepoint'], $customPlan189()['inner_savepoint']], ['wp_outer_custom189', 'wp_inner_custom189']],
    'custom plan retry count' => [static fn (): mixed => $customPlan189()['yielded_after_retry_count'], 5],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint($tables189, [], [$innerDelete189], [$retryUpdate189], $unique189), InvalidArgumentException::class],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint($tables189, [$outerUpdate189], [], [$retryUpdate189], $unique189), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint($tables189, [$outerUpdate189], [$innerDelete189], [], $unique189), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint($tables189, [$outerUpdate189], [$innerDelete189], [$retryUpdate189], []), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint($tables189, [$outerUpdate189], [$innerDelete189], [$retryUpdate189], $unique189, 'bad-name'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeNotBetweenRollbackRetrySavepoint(['wp_options' => ['bad']], [$outerUpdate189], [$innerDelete189], [$retryUpdate189], $unique189), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases189 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next189 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
