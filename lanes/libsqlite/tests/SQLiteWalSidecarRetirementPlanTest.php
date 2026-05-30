<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'hot-journal-checkpoint-source:221', 'epoch' => 221];
$next217 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next217',
    'database_path' => '/srv/www/wp-content/database/wp-next221.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next221.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next221.sqlite-journal',
    'shm_path' => '/srv/www/wp-content/database/wp-next221.sqlite-shm',
    'current_source_token' => $token,
    'checkpoint_frame' => 41,
    'checkpoint_cookie' => 91221,
    'checkpoint_admitted' => true,
    'next_source_epoch' => 222,
    'blocked_reader_names' => [],
    'operation_names' => ['admit_checkpoint_next_source_after_durable_receipts_next217'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next217'],
];
$receipt = static function (string $name, string $kind, string $action, string $path, array $overrides = []) use ($digest, $token): array {
    return array_merge([
        'name' => $name,
        'kind' => $kind,
        'path' => $path,
        'action' => $action,
        'source_id' => $token['id'],
        'next_epoch' => 222,
        'checkpoint_frame' => 41,
        'checkpoint_cookie' => 91221,
        'receipt_sha256' => $digest($name . ':' . $kind . ':' . $action),
        'synced' => true,
        'directory_synced' => true,
        'savepoint_closed' => true,
        'exclusive_lock_receipt' => true,
    ], $overrides);
};
$receipts = [
    $receipt('delete-hot-journal', 'hot-journal', 'delete', '/srv/www/wp-content/database/wp-next221.sqlite-journal'),
    $receipt('restart-wal-generation', 'wal', 'restart-header', '/srv/www/wp-content/database/wp-next221.sqlite-wal'),
    $receipt('reset-shm-readmarks', 'shm', 'reset-read-marks', '/srv/www/wp-content/database/wp-next221.sqlite-shm'),
];
$plan = static fn (?array $input = null, ?array $receiptInput = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::sidecarRetirementPlan($input ?? $next217, $receiptInput ?? $receipts);

$missingShm = [$receipts[0], $receipts[1]];
$badSource = $receipts;
$badSource[1]['source_id'] = 'older-source';
$badEpoch = $receipts;
$badEpoch[1]['next_epoch'] = 221;
$badFrame = $receipts;
$badFrame[1]['checkpoint_frame'] = 40;
$badCookie = $receipts;
$badCookie[1]['checkpoint_cookie'] = 7;
$badSync = $receipts;
$badSync[1]['synced'] = false;
$badDir = $receipts;
$badDir[1]['directory_synced'] = false;
$badSavepoint = $receipts;
$badSavepoint[1]['savepoint_closed'] = false;
$badLock = $receipts;
$badLock[1]['exclusive_lock_receipt'] = false;
$badAction = $receipts;
$badAction[1]['action'] = 'preserve';
$blockedNext217 = $next217;
$blockedNext217['blocked_reader_names'] = ['old-plugin-reader'];
$notAdmittedNext217 = $next217;
$notAdmittedNext217['checkpoint_admitted'] = false;
$notAdmittedNext217['status'] = 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next217';

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next221'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'sidecar_retirement_receipts_publish_next_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], '/srv/www/wp-content/database/wp-next221.sqlite'],
    'wal path' => [static fn (): mixed => $plan()['wal_path'], '/srv/www/wp-content/database/wp-next221.sqlite-wal'],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], '/srv/www/wp-content/database/wp-next221.sqlite-journal'],
    'shm path' => [static fn (): mixed => $plan()['shm_path'], '/srv/www/wp-content/database/wp-next221.sqlite-shm'],
    'token id' => [static fn (): mixed => $plan()['current_source_token']['id'], 'hot-journal-checkpoint-source:221'],
    'token epoch' => [static fn (): mixed => $plan()['current_source_token']['epoch'], 221],
    'next token id' => [static fn (): mixed => $plan()['next_source_token']['id'], 'hot-journal-checkpoint-source:221:next221'],
    'next token epoch' => [static fn (): mixed => $plan()['next_source_token']['epoch'], 222],
    'next token frame' => [static fn (): mixed => $plan()['next_source_token']['checkpoint_frame'], 41],
    'next token cookie' => [static fn (): mixed => $plan()['next_source_token']['checkpoint_cookie'], 91221],
    'checkpoint frame' => [static fn (): mixed => $plan()['checkpoint_frame'], 41],
    'checkpoint cookie' => [static fn (): mixed => $plan()['checkpoint_cookie'], 91221],
    'sidecar kinds' => [static fn (): mixed => $plan()['sidecar_kinds'], ['hot-journal', 'wal', 'shm']],
    'missing sidecars empty' => [static fn (): mixed => $plan()['missing_sidecar_kinds'], []],
    'admitted sidecars' => [static fn (): mixed => $plan()['admitted_sidecar_names'], ['delete-hot-journal', 'restart-wal-generation', 'reset-shm-readmarks']],
    'blocked sidecars empty' => [static fn (): mixed => $plan()['blocked_sidecar_names'], []],
    'guard count' => [static fn (): mixed => count($plan()['guard_names']), 8],
    'guard matches' => [static fn (): mixed => $plan()['guard_matches'], array_fill(0, 8, true)],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'checkpoint admitted' => [static fn (): mixed => $plan()['checkpoint_admitted'], true],
    'retirement digest length' => [static fn (): mixed => strlen($plan()['retirement_digest']), 64],
    'first row name' => [static fn (): mixed => $plan()['sidecar_rows'][0]['name'], 'delete-hot-journal'],
    'first row kind' => [static fn (): mixed => $plan()['sidecar_rows'][0]['kind'], 'hot-journal'],
    'first row action' => [static fn (): mixed => $plan()['sidecar_rows'][0]['action'], 'delete'],
    'first row admitted' => [static fn (): mixed => $plan()['sidecar_rows'][0]['admitted'], true],
    'first row transition' => [static fn (): mixed => $plan()['sidecar_rows'][0]['transition'], 'delete-hot-journal>retire-sidecar:next221'],
    'wal row transition' => [static fn (): mixed => $plan()['sidecar_rows'][1]['transition'], 'restart-wal-generation>retire-sidecar:next221'],
    'shm row transition' => [static fn (): mixed => $plan()['sidecar_rows'][2]['transition'], 'reset-shm-readmarks>retire-sidecar:next221'],
    'operation next217 carried' => [static fn (): mixed => in_array('admit_checkpoint_next_source_after_durable_receipts_next217', $plan()['operation_names'], true), true],
    'operation hot journal' => [static fn (): mixed => in_array('verify_hot_journal_retirement_receipt_next221', $plan()['operation_names'], true), true],
    'operation wal restart' => [static fn (): mixed => in_array('verify_restarted_wal_generation_receipt_next221', $plan()['operation_names'], true), true],
    'operation shm reset' => [static fn (): mixed => in_array('verify_shm_read_mark_reset_receipt_next221', $plan()['operation_names'], true), true],
    'dependency next217 carried' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next217', $plan()['dependencies'], true), true],
    'dependency next221' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next221', $plan()['dependencies'], true), true],
    'dependency application' => [static fn (): mixed => in_array('application-import-hot-journal-checkpoint-sidecar-retirement', $plan()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next217'), true],
    'missing shm status' => [static fn (): mixed => $plan(null, $missingShm)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next221'],
    'missing shm kind' => [static fn (): mixed => $plan(null, $missingShm)['missing_sidecar_kinds'], ['shm']],
    'missing shm guard' => [static fn (): mixed => in_array('required_sidecars_observed', $plan(null, $missingShm)['blocked_guard_names'], true), true],
    'bad source blocked' => [static fn (): mixed => $plan(null, $badSource)['blocked_sidecar_names'], ['restart-wal-generation']],
    'bad epoch blocked' => [static fn (): mixed => $plan(null, $badEpoch)['blocked_sidecar_names'], ['restart-wal-generation']],
    'bad frame blocked' => [static fn (): mixed => $plan(null, $badFrame)['blocked_sidecar_names'], ['restart-wal-generation']],
    'bad cookie blocked' => [static fn (): mixed => $plan(null, $badCookie)['blocked_sidecar_names'], ['restart-wal-generation']],
    'bad sync guard' => [static fn (): mixed => in_array('sidecar_receipts_match_next_source', $plan(null, $badSync)['blocked_guard_names'], true), true],
    'bad directory guard' => [static fn (): mixed => in_array('directory_sync_after_retirement', $plan(null, $badDir)['blocked_guard_names'], true), true],
    'bad savepoint blocked' => [static fn (): mixed => $plan(null, $badSavepoint)['blocked_sidecar_names'], ['restart-wal-generation']],
    'bad lock blocked' => [static fn (): mixed => $plan(null, $badLock)['blocked_sidecar_names'], ['restart-wal-generation']],
    'bad action guard' => [static fn (): mixed => in_array('next_wal_generation_durable', $plan(null, $badAction)['blocked_guard_names'], true), true],
    'blocked next217 guard' => [static fn (): mixed => in_array('next217_has_no_blocked_readers', $plan($blockedNext217)['blocked_guard_names'], true), true],
    'not admitted next217 guard' => [static fn (): mixed => in_array('next217_checkpoint_admitted', $plan($notAdmittedNext217)['blocked_guard_names'], true), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next221 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing plan key rejected' => static function () use ($plan, $next217): void {
        $bad = $next217;
        unset($bad['wal_path']);
        $plan($bad);
    },
    'empty receipts rejected' => static fn () => $plan(null, []),
    'empty token rejected' => static function () use ($plan, $next217): void {
        $bad = $next217;
        $bad['current_source_token']['id'] = '';
        $plan($bad);
    },
    'bad checkpoint frame rejected' => static function () use ($plan, $next217): void {
        $bad = $next217;
        $bad['checkpoint_frame'] = 0;
        $plan($bad);
    },
    'missing sidecar key rejected' => static function () use ($plan, $receipts): void {
        $bad = $receipts;
        unset($bad[0]['path']);
        $plan(null, $bad);
    },
    'bad sidecar kind rejected' => static function () use ($plan, $receipts): void {
        $bad = $receipts;
        $bad[0]['kind'] = 'journal';
        $plan(null, $bad);
    },
    'bad sidecar action rejected' => static function () use ($plan, $receipts): void {
        $bad = $receipts;
        $bad[0]['action'] = 'unlink';
        $plan(null, $bad);
    },
    'bad receipt digest rejected' => static function () use ($plan, $receipts): void {
        $bad = $receipts;
        $bad[0]['receipt_sha256'] = 'short';
        $plan(null, $bad);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next221 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
