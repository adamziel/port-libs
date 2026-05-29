<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$hash = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-next219-current-source', 'epoch' => 219];
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next219.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next219.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next219.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 42,
    'checkpoint_cookie' => 21942,
    'schema_cookie' => 21917,
    'admitted_reader_names' => ['wp-schema-reader', 'wp-options-reader', 'wp-cron-reader'],
    'reopen_reader_names' => ['wp-old-plugin-reader', 'wp-dirty-index-reader'],
    'checkpoint_admitted' => true,
    'next_source_epoch' => 220,
    'operation_names' => ['admit_checkpoint_next_source_after_hot_journal_next211'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211'],
];
$scope = static function (string $name, array $override = []) use ($admission, $token, $hash): array {
    return array_replace([
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => $token['epoch'],
        'checkpoint_frame' => $admission['checkpoint_frame'],
        'checkpoint_cookie' => $admission['checkpoint_cookie'],
        'schema_cookie' => $admission['schema_cookie'],
        'journal_delete_receipt' => true,
        'wal_reset_frame' => $admission['checkpoint_frame'],
        'reader_names' => ['wp-schema-reader', 'wp-options-reader'],
        'page_digests' => [
            1 => $hash($name . ':schema'),
            2 => $hash($name . ':options'),
        ],
    ], $override);
};
$scopes = [
    $scope('outer-import'),
    $scope('plugin-options'),
    $scope('cron-flush', ['reader_names' => ['wp-cron-reader'], 'page_digests' => [3 => $hash('cron-flush:cron')]]),
];
$blockedScopes = [
    $scope('open-depth', ['savepoint_depth' => 1]),
    $scope('not-released', ['released' => false]),
    $scope('future-rollback', ['rollback_generation' => 221]),
    $scope('wrong-frame', ['checkpoint_frame' => 41]),
    $scope('wrong-cookie', ['checkpoint_cookie' => 21941]),
    $scope('wrong-schema', ['schema_cookie' => 21916]),
    $scope('missing-delete', ['journal_delete_receipt' => false]),
    $scope('early-reset', ['wal_reset_frame' => 41]),
    $scope('hot-journal', ['hot_journal_present' => true]),
    $scope('unknown-reader', ['reader_names' => ['wp-missing-reader']]),
    $scope('reopen-reader', ['reader_names' => ['wp-old-plugin-reader']]),
];

$plan = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, $scopes);
$blocked = static fn (): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, $blockedScopes);
$notAdmitted = $admission;
$notAdmitted['checkpoint_admitted'] = false;
$badStatus = $admission;
$badStatus['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next211';

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next219'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'savepoint_scopes_finalized_before_checkpoint_next_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next219.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next219.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next219.sqlite-journal'],
    'token id' => [static fn (): mixed => $plan()['current_source_token']['id'], 'wp-next219-current-source'],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 42],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 21942],
    'schema cookie' => [static fn (): mixed => $plan()['schema_cookie'], 21917],
    'next source epoch' => [static fn (): mixed => $plan()['next_source_epoch'], 220],
    'published flag' => [static fn (): mixed => $plan()['checkpoint_next_source_published'], true],
    'finalized scopes' => [static fn (): mixed => $plan()['finalized_scope_names'], ['outer-import', 'plugin-options', 'cron-flush']],
    'blocked scopes empty' => [static fn (): mixed => $plan()['blocked_scope_names'], []],
    'scope count' => [static fn (): mixed => count($plan()['scope_rows']), 3],
    'first scope finalized' => [static fn (): mixed => $plan()['scope_rows'][0]['finalized'], true],
    'first scope reason' => [static fn (): mixed => $plan()['scope_rows'][0]['scope_reason'], 'savepoint_scope_finalized_for_checkpoint_next_source'],
    'first scope transition' => [static fn (): mixed => $plan()['scope_rows'][0]['scope_transition'], 'outer-import>publish-checkpoint-next-source:next219'],
    'first page numbers' => [static fn (): mixed => $plan()['scope_rows'][0]['page_numbers'], [1, 2]],
    'first page digest count' => [static fn (): mixed => $plan()['scope_rows'][0]['page_digest_count'], 2],
    'cron reader names' => [static fn (): mixed => $plan()['scope_rows'][2]['reader_names'], ['wp-cron-reader']],
    'guard names' => [static fn (): mixed => $plan()['guard_names'], ['next211_checkpoint_admitted', 'all_savepoint_scopes_finalized', 'at_least_one_scope_finalized', 'no_reader_reopen_overlap']],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'digest length' => [static fn (): mixed => strlen($plan()['savepoint_scope_digest']), 64],
    'inherited operation' => [static fn (): mixed => $plan()['operation_names'][0], 'admit_checkpoint_next_source_after_hot_journal_next211'],
    'finalize operation present' => [static fn (): mixed => in_array('finalize_hot_journal_savepoint_scope_next219', $plan()['operation_names'], true), true],
    'publish operation present' => [static fn (): mixed => in_array('publish_checkpoint_next_source_after_savepoint_finalization_next219', $plan()['operation_names'], true), true],
    'dependency inherited' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211', $plan()['dependencies'], true), true],
    'dependency next219' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-savepoint-finalization', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next211 reader acknowledgements'), true],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next219'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'savepoint_scopes_block_checkpoint_next_source'],
    'blocked published flag' => [static fn (): mixed => $blocked()['checkpoint_next_source_published'], false],
    'blocked scope names' => [static fn (): mixed => $blocked()['blocked_scope_names'], ['open-depth', 'not-released', 'future-rollback', 'wrong-frame', 'wrong-cookie', 'wrong-schema', 'missing-delete', 'early-reset', 'hot-journal', 'unknown-reader', 'reopen-reader']],
    'open depth reason' => [static fn (): mixed => $blocked()['scope_rows'][0]['scope_reason'], 'savepoint_scope_depth_open'],
    'not released reason' => [static fn (): mixed => $blocked()['scope_rows'][1]['scope_reason'], 'savepoint_scope_not_released'],
    'future rollback reason' => [static fn (): mixed => $blocked()['scope_rows'][2]['scope_reason'], 'savepoint_rollback_generation_after_current_source'],
    'wrong frame reason' => [static fn (): mixed => $blocked()['scope_rows'][3]['scope_reason'], 'savepoint_checkpoint_frame_mismatch'],
    'wrong cookie reason' => [static fn (): mixed => $blocked()['scope_rows'][4]['scope_reason'], 'savepoint_checkpoint_cookie_mismatch'],
    'wrong schema reason' => [static fn (): mixed => $blocked()['scope_rows'][5]['scope_reason'], 'savepoint_schema_cookie_mismatch'],
    'missing delete reason' => [static fn (): mixed => $blocked()['scope_rows'][6]['scope_reason'], 'savepoint_hot_journal_delete_receipt_missing'],
    'early reset reason' => [static fn (): mixed => $blocked()['scope_rows'][7]['scope_reason'], 'savepoint_wal_reset_before_checkpoint_frame'],
    'hot journal reason' => [static fn (): mixed => $blocked()['scope_rows'][8]['scope_reason'], 'savepoint_hot_journal_still_present'],
    'unknown reader reason' => [static fn (): mixed => $blocked()['scope_rows'][9]['scope_reason'], 'savepoint_reader_not_in_checkpoint_admission'],
    'unknown reader list' => [static fn (): mixed => $blocked()['scope_rows'][9]['unknown_readers'], ['wp-missing-reader']],
    'reopen reader reason' => [static fn (): mixed => $blocked()['scope_rows'][10]['scope_reason'], 'savepoint_reader_waits_for_reopen_fence'],
    'reopen reader overlap' => [static fn (): mixed => $blocked()['scope_rows'][10]['reopen_reader_overlap'], ['wp-old-plugin-reader']],
    'blocked guard names' => [static fn (): mixed => $blocked()['blocked_guard_names'], ['all_savepoint_scopes_finalized', 'at_least_one_scope_finalized', 'no_reader_reopen_overlap']],
    'not admitted guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($notAdmitted, $scopes)['blocked_guard_names'], ['next211_checkpoint_admitted']],
    'bad status guard' => [static fn (): mixed => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($badStatus, $scopes)['blocked_guard_names'], ['next211_checkpoint_admitted']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next219 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing admission rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan(['status' => 'x'], $scopes),
    'bad token rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan(array_merge($admission, ['current_source_token' => ['id' => '', 'epoch' => 0]]), $scopes),
    'bad next epoch rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan(array_merge($admission, ['next_source_epoch' => 219]), $scopes),
    'empty scopes rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, []),
    'missing scope field rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [['name' => 'bad']]),
    'empty scope name rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [$scope('', [])]),
    'negative depth rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [$scope('bad-depth', ['savepoint_depth' => -1])]),
    'bad reader names rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [$scope('bad-reader', ['reader_names' => ['']])]),
    'missing page digests rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [$scope('missing-pages', ['page_digests' => []])]),
    'bad page digest rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [$scope('bad-page', ['page_digests' => [1 => 'short']])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next219 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
