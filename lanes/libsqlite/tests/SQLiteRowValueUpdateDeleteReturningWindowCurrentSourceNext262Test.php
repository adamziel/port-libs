<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows262 = [
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
$tables262 = ['wp_options' => $rows262];
$unique262 = [['blog_id', 'option_name']];

$yieldUpdate262 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield262', option_value || ':yield262', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete262 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate262 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt262', option_value || ':attempt262', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete262 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate262 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry262', option_value || ':retry262', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete262 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan262 = static fn (?array $peerAck = null, array $peerColumns = ['status'], ?array $boundaryAck = null): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext262(
    $tables262,
    [$yieldUpdate262, $yieldDelete262],
    [$attemptUpdate262, $attemptDelete262],
    [$retryUpdate262, $retryDelete262],
    $unique262,
    'wp_options_rowvalue_window_current_next262',
    'option_id',
    null,
    null,
    null,
    $boundaryAck,
    $peerAck,
    $peerColumns,
);

$requiredPeer262 = static fn (): array => $plan262()['required_peer_tokens_next262'];
$missingPeer262 = static fn (): array => [];
$unexpectedPeer262 = static fn (): array => [...$requiredPeer262(), str_repeat('c', 64)];
$firstBoundary262 = static fn (): array => array_slice($plan262()['boundary_ready_tickets_next260'], 0, 1);

$cases262 = [
    'status' => [static fn (): mixed => $plan262()['status'], 'rowvalue-update-delete-returning-window-current-source-next262'],
    'inherits next260 boundary admission' => [static fn (): mixed => $plan262()['boundary_admission_next260'], true],
    'peer columns' => [static fn (): mixed => $plan262()['peer_group_columns_next262'], ['status']],
    'peer state complete' => [static fn (): mixed => $plan262()['peer_state_next262'], 'current-source-peer-groups-complete-next-source-visible-next262'],
    'source boundary released' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['source_boundary_released_next260'], true],
    'peer column count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['peer_column_count'], 1],
    'peer group count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['peer_group_count'], 4],
    'crossing peer group count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['crossing_peer_group_count'], 1],
    'required token count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['required_peer_token_count'], 1],
    'ack token count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['acknowledged_peer_token_count'], 1],
    'missing tokens empty' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['missing_peer_tokens'], []],
    'unexpected tokens empty' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['unexpected_peer_tokens'], []],
    'ready peer group count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['ready_peer_group_count'], 1],
    'row count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['row_count'], 8],
    'ready row count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['ready_row_count'], 8],
    'blocked row count' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['blocked_row_count'], 0],
    'peer groups complete' => [static fn (): mixed => $plan262()['peer_group_admission_next262']['peer_groups_complete'], true],
    'peer digest length' => [static fn (): mixed => strlen($plan262()['peer_group_admission_next262']['peer_digest']), 64],
    'required peer token length' => [static fn (): mixed => array_unique(array_map('strlen', $requiredPeer262())), [64]],
    'required peer token is crossing stale group' => [static fn (): mixed => $plan262()['crossing_peer_groups_next262'][0]['peer_key_next262'], 'status=stale'],
    'crossing rowids' => [static fn (): mixed => $plan262()['crossing_peer_groups_next262'][0]['rowids_next262'], [3, 4]],
    'crossing epochs count' => [static fn (): mixed => count($plan262()['crossing_peer_groups_next262'][0]['epochs_next262']), 2],
    'crossing peer row count' => [static fn (): mixed => $plan262()['crossing_peer_groups_next262'][0]['peer_row_count_next262'], 2],
    'ready rowids' => [static fn (): mixed => $plan262()['peer_ready_rowids_next262'], [7, 5, 3, 9, 10, 7, 5, 4]],
    'blocked rowids empty' => [static fn (): mixed => $plan262()['peer_blocked_rowids_next262'], []],
    'peer ordinals' => [static fn (): mixed => array_column($plan262()['peer_rows_next262'], 'peer_ordinal_next262'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'peer keys' => [static fn (): mixed => array_column($plan262()['peer_rows_next262'], 'peer_key_next262'), ['status=yield262', 'status=yield262', 'status=stale', 'status=retry262', 'status=live', 'status=retry262', 'status=retry262', 'status=stale']],
    'crossing flags' => [static fn (): mixed => array_column($plan262()['peer_rows_next262'], 'peer_group_crosses_source_next262'), [false, false, true, false, false, false, false, true]],
    'ack flags' => [static fn (): mixed => array_unique(array_column($plan262()['peer_rows_next262'], 'peer_group_acknowledged_next262')), [true]],
    'boundary ready flags' => [static fn (): mixed => array_unique(array_column($plan262()['peer_rows_next262'], 'peer_boundary_ready_next262')), [true]],
    'ready flags' => [static fn (): mixed => array_unique(array_column($plan262()['peer_rows_next262'], 'peer_ready_next262')), [true]],
    'blocked reasons empty' => [static fn (): mixed => array_unique(array_map('count', array_column($plan262()['peer_rows_next262'], 'peer_blocked_reasons_next262'))), [0]],
    'receipt lengths' => [static fn (): mixed => array_unique(array_map('strlen', array_column($plan262()['peer_rows_next262'], 'peer_receipt_next262'))), [64]],
    'receipt tokens unique' => [static fn (): mixed => count(array_unique(array_column($plan262()['peer_rows_next262'], 'peer_receipt_next262'))), 8],
    'missing peer state held' => [static fn (): mixed => $plan262($missingPeer262())['peer_state_next262'], 'next-source-peer-groups-held-for-current-source-next262'],
    'missing peer missing token recorded' => [static fn (): mixed => $plan262($missingPeer262())['peer_group_admission_next262']['missing_peer_tokens'], $requiredPeer262()],
    'missing peer ready count' => [static fn (): mixed => $plan262($missingPeer262())['peer_group_admission_next262']['ready_row_count'], 6],
    'missing peer blocked count' => [static fn (): mixed => $plan262($missingPeer262())['peer_group_admission_next262']['blocked_row_count'], 2],
    'missing peer blocked rowids' => [static fn (): mixed => $plan262($missingPeer262())['peer_blocked_rowids_next262'], [3, 4]],
    'missing peer blocked reasons' => [static fn (): mixed => array_unique(array_merge(...array_column($plan262($missingPeer262())['peer_blocked_rows_next262'], 'peer_blocked_reasons_next262'))), ['source-crossing-peer-group-not-acknowledged-next262']],
    'missing peer non crossing still ready' => [static fn (): mixed => array_column($plan262($missingPeer262())['peer_ready_rows_next262'], 'peer_rowid_next262'), [7, 5, 9, 10, 7, 5]],
    'unexpected peer state held' => [static fn (): mixed => $plan262($unexpectedPeer262())['peer_state_next262'], 'next-source-peer-groups-held-for-current-source-next262'],
    'unexpected peer token recorded' => [static fn (): mixed => $plan262($unexpectedPeer262())['peer_group_admission_next262']['unexpected_peer_tokens'], [str_repeat('c', 64)]],
    'unexpected peer rows ready' => [static fn (): mixed => $plan262($unexpectedPeer262())['peer_group_admission_next262']['ready_row_count'], 8],
    'live peer group rowids' => [static fn (): mixed => array_values(array_column(array_filter($plan262()['peer_groups_next262'], static fn (array $group): bool => $group['peer_key_next262'] === 'status=live'), 'rowids_next262')[0]), [10]],
    'retry peer group rowids' => [static fn (): mixed => array_values(array_column(array_filter($plan262()['peer_groups_next262'], static fn (array $group): bool => $group['peer_key_next262'] === 'status=retry262'), 'rowids_next262')[0]), [9, 7, 5]],
    'yield peer group rowids' => [static fn (): mixed => array_values(array_column(array_filter($plan262()['peer_groups_next262'], static fn (array $group): bool => $group['peer_key_next262'] === 'status=yield262'), 'rowids_next262')[0]), [7, 5]],
    'stale peer group token required' => [static fn (): mixed => $plan262()['crossing_peer_groups_next262'][0]['peer_token_next262'], $requiredPeer262()[0]],
    'boundary missing blocks frame and peer' => [static fn (): mixed => $plan262(null, ['status'], $firstBoundary262())['peer_group_admission_next262']['blocked_row_count'], 2],
    'boundary missing frame reason' => [static fn (): mixed => in_array('frame-boundary-not-ready-next262', array_merge(...array_column($plan262(null, ['status'], $firstBoundary262())['peer_blocked_rows_next262'], 'peer_blocked_reasons_next262')), true), true],
    'boundary missing keeps crossing tokens required' => [static fn (): mixed => count($plan262(null, ['status'], $firstBoundary262())['required_peer_tokens_next262']), 1],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-rowvalue-returning-window-peer-groups-next262', $plan262()['dependencies_next262'], true), true],
    'wordpress marker' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-peer-groups-next262', $plan262()['dependencies_next262'], true), true],
    'dependency closure no support' => [static fn (): mixed => str_contains($plan262()['dependency_closure_next262'], 'no new support component needed'), true],
    'non overlap mentions next260' => [static fn (): mixed => str_contains($plan262()['non_overlap_next262'], 'next260'), true],
    'non overlap mentions next256' => [static fn (): mixed => str_contains($plan262()['non_overlap_next262'], 'next256'), true],
    'non overlap mentions suite' => [static fn (): mixed => str_contains($plan262()['non_overlap_next262'], 'suite-runner'), true],
    'empty peer columns rejected' => [static fn (): mixed => $plan262(null, []), InvalidArgumentException::class],
    'bad peer column rejected' => [static fn (): mixed => $plan262(null, ['']), InvalidArgumentException::class],
    'missing peer column rejected' => [static fn (): mixed => $plan262(null, ['missing_column']), InvalidArgumentException::class],
    'missing blog peer column rejected' => [static fn (): mixed => $plan262(null, ['blog_id', 'status']), InvalidArgumentException::class],
    'bad peer token rejected' => [static fn (): mixed => $plan262(['']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases262 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next262 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
