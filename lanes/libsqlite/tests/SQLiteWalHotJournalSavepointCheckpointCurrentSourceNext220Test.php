<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$next219 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next219',
    'database_path' => '/srv/www/wp-content/database/wp-next220.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next220.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next220.sqlite-journal',
    'current_source_token' => ['id' => 'wp-next220-current-source', 'epoch' => 219],
    'checkpoint_frame' => 44,
    'checkpoint_cookie' => 22044,
    'schema_cookie' => 22017,
    'admitted_reader_names' => ['wp-schema-reader', 'wp-options-reader'],
    'reopen_reader_names' => ['wp-old-plugin-reader'],
    'checkpoint_next_source_published' => true,
    'next_source_epoch' => 220,
    'operation_names' => ['publish_checkpoint_next_source_after_savepoint_finalization_next219'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219'],
];

$receipt = static function (string $name, array $overrides = []) use ($next219): array {
    return array_merge([
        'name' => $name,
        'source_id' => $next219['current_source_token']['id'],
        'observed_epoch' => 220,
        'checkpoint_frame' => 44,
        'checkpoint_cookie' => 22044,
        'schema_cookie' => 22017,
        'cache_reopened' => true,
        'hot_journal_visible' => false,
        'savepoint_depth' => 0,
    ], $overrides);
};

$receipts = [
    $receipt('wp-schema-reader'),
    $receipt('wp-options-reader'),
    $receipt('wp-old-plugin-reader'),
];
$plan = static fn (?array $base = null, ?array $input = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next220ReaderReopenPlan($base ?? $next219, $input ?? $receipts);

$blockedReceipts = [
    $receipt('wp-schema-reader', ['source_id' => 'stale']),
    $receipt('wp-options-reader', ['observed_epoch' => 219]),
    $receipt('wp-old-plugin-reader', ['checkpoint_frame' => 43, 'checkpoint_cookie' => 7, 'schema_cookie' => 6, 'cache_reopened' => false, 'hot_journal_visible' => true, 'savepoint_depth' => 1]),
    $receipt('wp-missing-reader'),
];
$missingReopen = array_slice($receipts, 0, 2);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next220'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'reader_reopen_receipts_observe_checkpoint_next_source'],
    'paths' => [static fn (): mixed => [$plan()['database_path'], $plan()['wal_path'], $plan()['journal_path']], [$next219['database_path'], $next219['wal_path'], $next219['journal_path']]],
    'token' => [static fn (): mixed => $plan()['current_source_token'], ['id' => 'wp-next220-current-source', 'epoch' => 219]],
    'frame cookie schema' => [static fn (): mixed => [$plan()['checkpoint_frame'], $plan()['checkpoint_cookie'], $plan()['schema_cookie']], [44, 22044, 22017]],
    'next epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 220],
    'reader rows' => [static fn (): mixed => count($plan()['reader_rows']), 3],
    'admitted readers' => [static fn (): mixed => $plan()['admitted_reader_names'], ['wp-schema-reader', 'wp-options-reader', 'wp-old-plugin-reader']],
    'blocked readers empty' => [static fn (): mixed => $plan()['blocked_reader_names'], []],
    'required reopen reader' => [static fn (): mixed => $plan()['required_reopen_reader_names'], ['wp-old-plugin-reader']],
    'missing reopen empty' => [static fn (): mixed => $plan()['missing_reopen_reader_names'], []],
    'reopen allowed' => [static fn (): mixed => $plan()['reader_reopen_allowed'], true],
    'cache action' => [static fn (): mixed => $plan()['reader_cache_action'], 'install_reopened_checkpoint_reader_cache_next220'],
    'transition' => [static fn (): mixed => $plan()['reader_rows'][2]['transition'], 'wp-old-plugin-reader>reopen-reader-cache:next220'],
    'guards' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => in_array('publish_checkpoint_next_source_after_savepoint_finalization_next219', $plan()['operation_names'], true), true],
    'operation next220' => [static fn (): mixed => in_array('publish_reopened_reader_cache_next220', $plan()['operation_names'], true), true],
    'digest length' => [static fn (): mixed => strlen($plan()['reader_reopen_digest']), 64],
    'dependency next219' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219', $plan()['dependencies'], true), true],
    'dependency next220' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next220', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-reader-cache-reopen', $plan()['dependencies'], true), true],
    'closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next219'), true],
    'blocked status' => [static fn (): mixed => $plan(null, $blockedReceipts)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next220'],
    'blocked readers' => [static fn (): mixed => $plan(null, $blockedReceipts)['blocked_reader_names'], ['wp-schema-reader', 'wp-options-reader', 'wp-old-plugin-reader', 'wp-missing-reader']],
    'blocked reasons' => [static fn (): mixed => $plan(null, $blockedReceipts)['reader_rows'][2]['blocked_reasons'], ['reader_checkpoint_frame_mismatch', 'reader_checkpoint_cookie_mismatch', 'reader_schema_cookie_mismatch', 'reader_hot_journal_still_visible', 'reader_cache_not_reopened', 'reader_savepoint_scope_not_closed']],
    'missing reopen reader' => [static fn (): mixed => $plan(null, $missingReopen)['missing_reopen_reader_names'], ['wp-old-plugin-reader']],
    'missing reopen guard' => [static fn (): mixed => $plan(null, $missingReopen)['blocked_guard_names'], ['required_reopen_readers_observed']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next220 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => $plan(array_merge($next219, ['status' => 'blocked'])),
    'unpublished rejected' => static fn () => $plan(array_merge($next219, ['checkpoint_next_source_published' => false])),
    'empty receipts rejected' => static fn () => $plan(null, []),
    'bad token rejected' => static fn () => $plan(array_merge($next219, ['current_source_token' => ['id' => '', 'epoch' => 0]])),
    'bad frame rejected' => static fn () => $plan(array_merge($next219, ['checkpoint_frame' => 0])),
    'empty reader rejected' => static fn () => $plan(array_merge($next219, ['admitted_reader_names' => ['']])),
    'missing receipt name rejected' => static fn () => $plan(null, [['source_id' => 'x']]),
    'malformed receipt rejected' => static fn () => $plan(null, [$receipt('bad', ['observed_epoch' => '220'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next220 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
