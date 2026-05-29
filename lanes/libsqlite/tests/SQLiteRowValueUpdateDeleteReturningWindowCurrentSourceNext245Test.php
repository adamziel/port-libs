<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows245 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 19, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];
$tables245 = ['wp_options' => $rows245];
$unique245 = [['blog_id', 'option_name']];

$yieldUpdate245 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield245', option_value || ':yield245', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete245 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate245 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt245', option_value || ':attempt245', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete245 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate245 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry245', option_value || ':retry245', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete245 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan245 = static fn (?array $ack = null): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext245(
    $tables245,
    [$yieldUpdate245, $yieldDelete245],
    [$attemptUpdate245, $attemptDelete245],
    [$retryUpdate245, $retryDelete245],
    $unique245,
    'wp_options_rowvalue_window_current_next245',
    'option_id',
    $ack,
);
$customPlan245 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext245(
    $tables245,
    [$yieldUpdate245],
    [$attemptUpdate245],
    [$retryUpdate245],
    $unique245,
    'custom_window_245',
);

$required245 = static fn (): array => $plan245()['required_yield_tickets_next245'];
$missingAck245 = static fn (): array => array_slice($required245(), 0, 2);
$unexpectedAck245 = static fn (): array => [...$required245(), 'unexpected:ticket:next245'];

$cases245 = [
    'parser yield update row value predicate' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($yieldUpdate245)['where'], "(blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'))"],
    'parser retry delete returning preserved' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::parse($retryDelete245)['returning'], 'option_id, blog_id, option_name, status, bytes'],
    'plan status' => [static fn (): mixed => $plan245()['status'], 'rowvalue-update-delete-returning-window-current-source-next245'],
    'plan savepoint' => [static fn (): mixed => $plan245()['savepoint'], 'wp_options_rowvalue_window_current_next245'],
    'inherits next236 retry frames' => [static fn (): mixed => $plan245()['retry_current_row_frame_ids_next236'], [9, 10, 7, 5, 4]],
    'yield ticket ids' => [static fn (): mixed => array_column($plan245()['yield_phase_tickets_next245'], 'option_id'), [7, 5, 3]],
    'yield ticket statuses' => [static fn (): mixed => array_column($plan245()['yield_phase_tickets_next245'], 'status'), ['yield245', 'yield245', 'stale']],
    'yield ticket running bytes' => [static fn (): mixed => array_column($plan245()['yield_phase_tickets_next245'], 'running_bytes'), [39, 76, 88]],
    'yield ticket following bytes' => [static fn (): mixed => array_column($plan245()['yield_phase_tickets_next245'], 'following_bytes'), [49, 12, 0]],
    'yield ticket phases' => [static fn (): mixed => array_unique(array_column($plan245()['yield_phase_tickets_next245'], 'phase')), ['yield']],
    'yield ticket ordinals' => [static fn (): mixed => array_column($plan245()['yield_phase_tickets_next245'], 'ordinal'), [1, 2, 3]],
    'yield ticket frame tokens' => [static fn (): mixed => array_column($plan245()['yield_phase_tickets_next245'], 'frame_token'), ['rewrite_rules:39:39', 'pending_theme:37:76', '_transient_feed:12:88']],
    'required tickets equal yield tickets' => [static fn (): mixed => $plan245()['required_yield_tickets_next245'], array_column($plan245()['yield_phase_tickets_next245'], 'ticket')],
    'required tickets exact' => [static fn (): mixed => $required245(), ['yield:1:7:rewrite_rules:rewrite_rules:39:39', 'yield:2:5:pending_theme:pending_theme:37:76', 'yield:3:3:_transient_feed:_transient_feed:12:88']],
    'acknowledged tickets exact' => [static fn (): mixed => $plan245()['acknowledged_yield_tickets_next245'], $required245()],
    'gate required count' => [static fn (): mixed => $plan245()['yield_current_source_gate_next245']['required_count'], 3],
    'gate acknowledged count' => [static fn (): mixed => $plan245()['yield_current_source_gate_next245']['acknowledged_count'], 3],
    'gate missing empty' => [static fn (): mixed => $plan245()['yield_current_source_gate_next245']['missing_tickets'], []],
    'gate unexpected empty' => [static fn (): mixed => $plan245()['yield_current_source_gate_next245']['unexpected_tickets'], []],
    'gate current source complete' => [static fn (): mixed => $plan245()['current_source_before_next245'], true],
    'gate next source exposed' => [static fn (): mixed => $plan245()['next_source_exposed_next245'], true],
    'gate boundary label' => [static fn (): mixed => $plan245()['yield_current_source_gate_next245']['yield_boundary'], 'current-source-yield-before-next-source-next245'],
    'missing ack holds next source' => [static fn (): mixed => $plan245($missingAck245())['next_source_exposed_next245'], false],
    'missing ack records ticket' => [static fn (): mixed => $plan245($missingAck245())['yield_current_source_gate_next245']['missing_tickets'], ['yield:3:3:_transient_feed:_transient_feed:12:88']],
    'missing ack has no unexpected tickets' => [static fn (): mixed => $plan245($missingAck245())['yield_current_source_gate_next245']['unexpected_tickets'], []],
    'unexpected ack holds next source' => [static fn (): mixed => $plan245($unexpectedAck245())['next_source_exposed_next245'], false],
    'unexpected ack records ticket' => [static fn (): mixed => $plan245($unexpectedAck245())['yield_current_source_gate_next245']['unexpected_tickets'], ['unexpected:ticket:next245']],
    'unexpected ack has no missing tickets' => [static fn (): mixed => $plan245($unexpectedAck245())['yield_current_source_gate_next245']['missing_tickets'], []],
    'suppressed ticket ids' => [static fn (): mixed => array_column($plan245()['suppressed_phase_tickets_next245'], 'option_id'), [7, 5, 8]],
    'suppressed ticket phases' => [static fn (): mixed => array_unique(array_column($plan245()['suppressed_phase_tickets_next245'], 'phase')), ['suppressed-attempt']],
    'suppressed ticket statuses' => [static fn (): mixed => array_column($plan245()['suppressed_phase_tickets_next245'], 'status'), ['attempt245', 'attempt245', 'orphaned']],
    'suppressed ticket running bytes' => [static fn (): mixed => array_column($plan245()['suppressed_phase_tickets_next245'], 'running_bytes'), [44, 86, 91]],
    'retry ticket ids' => [static fn (): mixed => array_column($plan245()['retry_phase_tickets_next245'], 'option_id'), [9, 10, 7, 5, 4]],
    'retry ticket phases' => [static fn (): mixed => array_unique(array_column($plan245()['retry_phase_tickets_next245'], 'phase')), ['retry-release']],
    'retry ticket statuses' => [static fn (): mixed => array_column($plan245()['retry_phase_tickets_next245'], 'status'), ['retry245', 'live', 'retry245', 'retry245', 'stale']],
    'retry ticket running bytes' => [static fn (): mixed => array_column($plan245()['retry_phase_tickets_next245'], 'running_bytes'), [31, 62, 91, 118, 131]],
    'retry ticket following bytes' => [static fn (): mixed => array_column($plan245()['retry_phase_tickets_next245'], 'following_bytes'), [100, 69, 40, 13, 0]],
    'yield retry order starts with yield' => [static fn (): mixed => array_slice($plan245()['yield_retry_order_next245'], 0, 3), $required245()],
    'yield retry order ends with retry' => [static fn (): mixed => array_slice($plan245()['yield_retry_order_next245'], 3), array_column($plan245()['retry_phase_tickets_next245'], 'ticket')],
    'receipt savepoint' => [static fn (): mixed => $plan245()['yield_window_receipt_next245']['savepoint'], 'wp_options_rowvalue_window_current_next245'],
    'receipt yield ids' => [static fn (): mixed => $plan245()['yield_window_receipt_next245']['yield_ids'], [7, 5, 3]],
    'receipt retry ids' => [static fn (): mixed => $plan245()['yield_window_receipt_next245']['retry_ids'], [9, 10, 7, 5, 4]],
    'receipt gate status exposed' => [static fn (): mixed => $plan245()['yield_window_receipt_next245']['gate_status'], 'next-source-exposed-after-current-yield'],
    'receipt suppressed ids' => [static fn (): mixed => $plan245()['yield_window_receipt_next245']['suppressed_attempt_ids'], [5, 7, 8]],
    'receipt row count' => [static fn (): mixed => $plan245()['yield_window_receipt_next245']['current_source_row_count'], 8],
    'receipt retry final' => [static fn (): mixed => $plan245()['yield_window_receipt_next245']['retry_running_final'], 131],
    'retry deletes timeout and home' => [static fn (): mixed => array_values(array_intersect([4, 10], array_column($plan245()['current_source_tables']['wp_options'], 'option_id'))), []],
    'retry preserves rolled back feed delete' => [static fn (): mixed => in_array(3, array_column($plan245()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'retry preserves suppressed orphan delete' => [static fn (): mixed => in_array(8, array_column($plan245()['current_source_tables']['wp_options'], 'option_id'), true), true],
    'dependency ticket gate' => [static fn (): mixed => in_array('sqlite-returning-current-source-ticket-gate-next245', $plan245()['dependencies_next245'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-yield-gate-next245', $plan245()['dependencies_next245'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan245()['dependency_closure_next245'], 'no new support component needed'), true],
    'non overlap mentions next236' => [static fn (): mixed => str_contains($plan245()['non_overlap_next245'], 'next236'), true],
    'non overlap mentions next242' => [static fn (): mixed => str_contains($plan245()['non_overlap_next245'], 'next242'), true],
    'custom savepoint' => [static fn (): mixed => $customPlan245()['savepoint'], 'custom_window_245'],
    'custom yield ticket ids' => [static fn (): mixed => array_column($customPlan245()['yield_phase_tickets_next245'], 'option_id'), [7, 5]],
    'custom retry ticket ids' => [static fn (): mixed => array_column($customPlan245()['retry_phase_tickets_next245'], 'option_id'), [9, 7, 5]],
    'custom gate exposed' => [static fn (): mixed => $customPlan245()['next_source_exposed_next245'], true],
    'malformed empty yield rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext245($tables245, [], [$attemptUpdate245], [$retryUpdate245], $unique245), InvalidArgumentException::class],
    'malformed empty attempt rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext245($tables245, [$yieldUpdate245], [], [$retryUpdate245], $unique245), InvalidArgumentException::class],
    'malformed empty retry rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext245($tables245, [$yieldUpdate245], [$attemptUpdate245], [], $unique245), InvalidArgumentException::class],
    'malformed savepoint rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext245($tables245, [$yieldUpdate245], [$attemptUpdate245], [$retryUpdate245], $unique245, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases245 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next245 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
