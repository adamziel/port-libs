<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows224 = [
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

$tables224 = ['wp_options' => $rows224];
$unique224 = [['blog_id', 'option_name']];

$innerUpdate224 = "UPDATE wp_options SET (status, option_value, bytes) = ('inner224', option_value || ':inner224', bytes + 2) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (status, option_name) = ('inner224', 'pending_theme') AS inner_pending ORDER BY option_id DESC";
$innerDelete224 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IN (VALUES (1, '_transient_feed')) AS inner_deleted ORDER BY option_id";
$outerUpdate224 = "UPDATE wp_options SET (status, option_value, bytes) = ('outer224', option_value || ':outer224', bytes + 4) WHERE (status, option_name) IN (('inner224', 'pending_theme'), ('inner224', 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, option_value, bytes, (option_value, status) IS (option_value, 'outer224') AS stable_outer ORDER BY option_id";
$outerDelete224 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) NOT IN ((3, 'plugin_batch')) AS keep_plugin ORDER BY option_id DESC";
$retryUpdate224 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry224', option_value || ':retry224', bytes + 1) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, option_value, bytes ORDER BY option_id";
$retryDelete224 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN (VALUES (1, '_transient_feed'), (4, 'siteurl')) RETURNING option_id, blog_id, option_name, status, (blog_id, option_name) IS DISTINCT FROM (4, 'home') AS not_home ORDER BY option_id";

$innerUpdateResult224 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerUpdate224, $tables224, 'option_id', $unique224);
$innerDeleteResult224 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($innerDelete224, $innerUpdateResult224()['tables'], 'option_id', $unique224);
$outerUpdateResult224 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerUpdate224, $innerDeleteResult224()['tables'], 'option_id', $unique224);
$outerDeleteResult224 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($outerDelete224, $outerUpdateResult224()['tables'], 'option_id', $unique224);
$retryUpdateResult224 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryUpdate224, $tables224, 'option_id', $unique224);
$retryDeleteResult224 = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($retryDelete224, $retryUpdateResult224()['tables'], 'option_id', $unique224);
$plan224 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224(
    $tables224,
    [$innerUpdate224, $innerDelete224],
    [$outerUpdate224, $outerDelete224],
    [$retryUpdate224, $retryDelete224],
    $unique224,
);
$customPlan224 = static fn (): array => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224(
    $tables224,
    [$innerUpdate224],
    [$outerUpdate224],
    [$retryUpdate224],
    $unique224,
    'outer_custom224',
    'inner_custom224',
);

$cases224 = [
    'parser inner row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($innerUpdate224)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser inner delete values where' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($innerDelete224)['where'] ?? '', 'VALUES'), true],
    'parser outer update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($outerUpdate224)['where'], "(status, option_name) IN (('inner224', 'pending_theme'), ('inner224', 'rewrite_rules'))"],
    'parser outer delete desc' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($outerDelete224)['order_by'], [['column' => 'option_id', 'direction' => 'DESC']]],
    'parser retry update row value where' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryUpdate224)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (3, 'plugin_batch'))"],
    'parser retry delete returning flag' => [static fn (): mixed => str_contains(SQLiteUpdateDeleteReturningSql::parse($retryDelete224)['returning'], 'not_home'), true],
    'direct inner update selected ids' => [static fn (): mixed => $innerUpdateResult224()['plan']->selectedIds, [8, 7]],
    'direct inner update returning order table' => [static fn (): mixed => array_column($innerUpdateResult224()['returning'], 'option_id'), [7, 8]],
    'direct inner update pending flag' => [static fn (): mixed => array_column($innerUpdateResult224()['returning'], 'inner_pending'), [1, 0]],
    'direct inner delete selected id' => [static fn (): mixed => $innerDeleteResult224()['plan']->selectedIds, [3]],
    'direct inner delete removes feed' => [static fn (): mixed => in_array(3, array_column($innerDeleteResult224()['tables']['wp_options'], 'option_id'), true), false],
    'direct outer update sees released inner rows' => [static fn (): mixed => $outerUpdateResult224()['plan']->selectedIds, [7, 8]],
    'direct outer update row seven chained' => [static fn (): mixed => array_column($outerUpdateResult224()['returning'], 'option_value', 'option_id')[7], 'theme:inner224:outer224'],
    'direct outer update stable flags' => [static fn (): mixed => array_column($outerUpdateResult224()['returning'], 'stable_outer'), [1, 1]],
    'direct outer delete selected ids' => [static fn (): mixed => $outerDeleteResult224()['plan']->selectedIds, [9, 4]],
    'direct outer delete returning table order' => [static fn (): mixed => array_column($outerDeleteResult224()['returning'], 'option_id'), [4, 9]],
    'direct outer delete removes plugin' => [static fn (): mixed => in_array(9, array_column($outerDeleteResult224()['tables']['wp_options'], 'option_id'), true), false],
    'direct retry update selected from outer image' => [static fn (): mixed => $retryUpdateResult224()['plan']->selectedIds, [7, 8, 9]],
    'direct retry update row seven no inner prefix' => [static fn (): mixed => array_column($retryUpdateResult224()['returning'], 'option_value', 'option_id')[7], 'theme:retry224'],
    'direct retry update row nine restored' => [static fn (): mixed => array_column($retryUpdateResult224()['returning'], 'option_value', 'option_id')[9], 'plugin:retry224'],
    'direct retry delete selected restored ids' => [static fn (): mixed => $retryDeleteResult224()['plan']->selectedIds, [3, 10]],
    'direct retry delete returning flags' => [static fn (): mixed => array_column($retryDeleteResult224()['returning'], 'not_home'), [1, 1]],
    'direct retry delete final ids' => [static fn (): mixed => array_column($retryDeleteResult224()['tables']['wp_options'], 'option_id'), [1, 2, 4, 5, 6, 7, 8, 9]],

    'plan status' => [static fn (): mixed => $plan224()['status'], 'rowvalue-update-delete-returning-nested-release-rollback-current-source-next224'],
    'plan savepoint names' => [static fn (): mixed => [$plan224()['outer_savepoint'], $plan224()['inner_savepoint']], ['wp_options_outer_rowvalue_next224', 'wp_options_inner_rowvalue_next224']],
    'plan inner release merged' => [static fn (): mixed => $plan224()['inner_release_merged_into_outer_next224'], true],
    'plan outer rollback discards inner' => [static fn (): mixed => $plan224()['outer_rollback_discards_released_inner_next224'], true],
    'plan released returning suppressed' => [static fn (): mixed => $plan224()['released_inner_returning_suppressed_by_outer_rollback_next224'], true],
    'plan outer attempt suppressed' => [static fn (): mixed => $plan224()['outer_attempt_returning_suppressed_by_rollback_next224'], true],
    'plan retry reads image' => [static fn (): mixed => $plan224()['retry_reads_outer_savepoint_image_next224'], true],
    'plan outer remains active' => [static fn (): mixed => $plan224()['outer_savepoint_remains_active_next224'], true],
    'plan outer image original' => [static fn (): mixed => $plan224()['outer_savepoint_image_tables'], $tables224],
    'plan after inner row seven changed' => [static fn (): mixed => array_column($plan224()['after_inner_release_tables']['wp_options'], 'status', 'option_id')[7], 'inner224'],
    'plan after inner row three deleted' => [static fn (): mixed => in_array(3, array_column($plan224()['after_inner_release_tables']['wp_options'], 'option_id'), true), false],
    'plan outer attempt row seven changed' => [static fn (): mixed => array_column($plan224()['outer_attempt_current_source_tables']['wp_options'], 'status', 'option_id')[7], 'outer224'],
    'plan outer attempt row nine deleted' => [static fn (): mixed => in_array(9, array_column($plan224()['outer_attempt_current_source_tables']['wp_options'], 'option_id'), true), false],
    'plan rollback restores image' => [static fn (): mixed => $plan224()['after_outer_rollback_tables'], $tables224],
    'plan retry row seven retry only' => [static fn (): mixed => array_column($plan224()['current_source_tables']['wp_options'], 'option_value', 'option_id')[7], 'theme:retry224'],
    'plan retry row eight retry only' => [static fn (): mixed => array_column($plan224()['current_source_tables']['wp_options'], 'option_value', 'option_id')[8], 'rules:retry224'],
    'plan retry row nine retry only' => [static fn (): mixed => array_column($plan224()['current_source_tables']['wp_options'], 'option_value', 'option_id')[9], 'plugin:retry224'],
    'plan retry deleted feed and site four' => [static fn (): mixed => array_intersect([3, 10], array_column($plan224()['current_source_tables']['wp_options'], 'option_id')), []],
    'plan restored timeout survives' => [static fn (): mixed => in_array(4, array_column($plan224()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'plan next source equals current' => [static fn (): mixed => $plan224()['next_source_tables'], $plan224()['current_source_tables']],
    'plan inner phases' => [static fn (): mixed => array_column($plan224()['inner_statements'], 'phase'), ['inner-released-before-outer-rollback-next224', 'inner-released-before-outer-rollback-next224']],
    'plan outer attempt phases' => [static fn (): mixed => array_column($plan224()['outer_attempt_statements'], 'phase'), ['outer-attempt-before-rollback-next224', 'outer-attempt-before-rollback-next224']],
    'plan retry phases' => [static fn (): mixed => array_column($plan224()['retry_statements'], 'phase'), ['retry-after-outer-rollback-next224', 'retry-after-outer-rollback-next224']],
    'plan inner source rows original' => [static fn (): mixed => array_column($plan224()['inner_statements'][0]['source_rows'], 'status'), ['queued', 'queued']],
    'plan outer source rows include inner changes' => [static fn (): mixed => array_column($plan224()['outer_attempt_statements'][0]['source_rows'], 'status'), ['inner224', 'inner224']],
    'plan retry source rows original' => [static fn (): mixed => array_column($plan224()['retry_statements'][0]['source_rows'], 'status'), ['queued', 'queued', 'queued']],
    'plan retry delete source rows restored' => [static fn (): mixed => array_column($plan224()['retry_statements'][1]['source_rows'], 'option_id'), [3, 10]],
    'plan released inner count' => [static fn (): mixed => $plan224()['released_inner_returning_count'], 3],
    'plan outer attempt count' => [static fn (): mixed => $plan224()['outer_attempt_returning_count'], 4],
    'plan suppressed count' => [static fn (): mixed => $plan224()['suppressed_returning_count'], 7],
    'plan retry returning count' => [static fn (): mixed => $plan224()['retry_returning_count'], 5],
    'plan released change count' => [static fn (): mixed => $plan224()['released_inner_change_count'], 3],
    'plan outer change count' => [static fn (): mixed => $plan224()['outer_attempt_change_count'], 4],
    'plan retry change count' => [static fn (): mixed => $plan224()['retry_change_count'], 5],
    'plan changed tables' => [static fn (): mixed => $plan224()['changed_tables_after_retry'], ['wp_options']],
    'plan row count' => [static fn (): mixed => $plan224()['row_counts']['wp_options'], 8],
    'plan receipt suppressed' => [static fn (): mixed => $plan224()['rollback_receipt_next224']['suppressed_returning_count'], 7],
    'plan receipt restored tables' => [static fn (): mixed => $plan224()['rollback_receipt_next224']['restored_tables'], ['wp_options']],
    'plan dependency release rollback' => [static fn (): mixed => in_array('sqlite-rowvalue-nested-release-rolled-back-by-outer-savepoint-next224', $plan224()['dependencies'], true), true],
    'plan dependency returning suppressed' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-suppressed-after-outer-rollback-next224', $plan224()['dependencies'], true), true],
    'plan dependency wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-nested-savepoint-retry-current-source-next224', $plan224()['dependencies'], true), true],
    'plan dependency closure' => [static fn (): mixed => str_contains($plan224()['dependency_closure_next224'], 'no new support component needed'), true],
    'plan non overlap mentions next218' => [static fn (): mixed => str_contains($plan224()['non_overlap_next224'], 'next218'), true],
    'custom savepoints' => [static fn (): mixed => [$customPlan224()['outer_savepoint'], $customPlan224()['inner_savepoint']], ['outer_custom224', 'inner_custom224']],
    'custom suppressed count' => [static fn (): mixed => $customPlan224()['suppressed_returning_count'], 4],
    'custom retry count' => [static fn (): mixed => $customPlan224()['retry_returning_count'], 3],
    'malformed empty inner rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224($tables224, [], [$outerUpdate224], [$retryUpdate224], $unique224), InvalidArgumentException::class],
    'malformed empty outer rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224($tables224, [$innerUpdate224], [], [$retryUpdate224], $unique224), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224($tables224, [$innerUpdate224], [$outerUpdate224], [], $unique224), InvalidArgumentException::class],
    'malformed empty unique rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224($tables224, [$innerUpdate224], [$outerUpdate224], [$retryUpdate224], []), InvalidArgumentException::class],
    'malformed outer savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224($tables224, [$innerUpdate224], [$outerUpdate224], [$retryUpdate224], $unique224, 'bad-name'), InvalidArgumentException::class],
    'malformed inner savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224($tables224, [$innerUpdate224], [$outerUpdate224], [$retryUpdate224], $unique224, 'outer_good', 'bad-name'), InvalidArgumentException::class],
    'malformed same savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224($tables224, [$innerUpdate224], [$outerUpdate224], [$retryUpdate224], $unique224, 'same224', 'same224'), InvalidArgumentException::class],
    'malformed row list rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNextPlan::executeNext224(['wp_options' => ['bad']], [$innerUpdate224], [$outerUpdate224], [$retryUpdate224], $unique224), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases224 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next224 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
