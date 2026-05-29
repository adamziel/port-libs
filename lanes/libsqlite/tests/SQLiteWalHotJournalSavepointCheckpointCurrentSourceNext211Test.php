<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next211.sqlite';
$hash = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-next211-current-source', 'epoch' => 211];
$checkpoint = [
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'journal_path' => $databasePath . '-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 37,
    'checkpoint_cookie' => 21177,
    'schema_cookie' => 21143,
    'wal_salt' => 'next211-wal-salt',
    'hot_journal_generation' => 27,
    'savepoint_generation' => 29,
    'cache_generation' => 31,
    'page_digests' => [
        1 => $hash('next211 schema page after checkpoint'),
        2 => $hash('next211 wp_options root after checkpoint'),
        3 => $hash('next211 autoload index after checkpoint'),
        4 => $hash('next211 transient option after checkpoint'),
    ],
    'checkpoint_published' => true,
    'journal_removed' => true,
    'operation_names' => ['verify_reader_page_digest_next205'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next205'],
];
$reader = static function (string $name, int $page, ?array $override = null) use ($token, $checkpoint): array {
    $row = [
        'name' => $name,
        'page' => $page,
        'source_id' => $token['id'],
        'epoch' => $token['epoch'],
        'observed_checkpoint_frame' => $checkpoint['checkpoint_frame'],
        'observed_checkpoint_cookie' => $checkpoint['checkpoint_cookie'],
        'observed_schema_cookie' => $checkpoint['schema_cookie'],
        'observed_wal_salt' => $checkpoint['wal_salt'],
        'observed_hot_journal_generation' => $checkpoint['hot_journal_generation'],
        'observed_savepoint_generation' => $checkpoint['savepoint_generation'],
        'observed_cache_generation' => $checkpoint['cache_generation'],
        'image_sha256' => $checkpoint['page_digests'][$page] ?? $checkpoint['page_digests'][4],
    ];

    return array_replace($row, $override ?? []);
};
$readers = [
    $reader('schema-reader', 1),
    $reader('options-reader', 2),
    $reader('autoload-index-reader', 3),
    $reader('old-source-reader', 2, ['source_id' => 'old-source']),
    $reader('dirty-transient-reader', 4, ['dirty' => true]),
    $reader('stale-image-reader', 2, ['image_sha256' => $hash('old next211 wp_options root')]),
    $reader('closed-reader', 3, ['closed' => true]),
];
$base205 = static fn (?array $rows = null, ?array $base = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($base ?? $checkpoint, $rows ?? $readers);
$basePlan = $base205();
$ack = static function (array $row, array $override = []) use ($basePlan): array {
    return array_replace([
        'source_id' => $basePlan['current_source_token']['id'],
        'epoch' => $basePlan['current_source_token']['epoch'],
        'checkpoint_frame' => $basePlan['checkpoint_frame'],
        'checkpoint_cookie' => $basePlan['checkpoint_cookie'],
        'schema_cookie' => $basePlan['schema_cookie'],
        'image_sha256' => $row['observed_image_sha256'],
        'acknowledged' => $row['admitted'],
        'reopen_fenced' => !$row['admitted'],
    ], $override);
};
$acks = [];
foreach ($basePlan['reader_rows'] as $row) {
    $acks[$row['name']] = $ack($row);
}
$plan = static fn (?array $readerPlan = null, ?array $ackRows = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next211Plan($readerPlan ?? $basePlan, $ackRows ?? $acks);
$ok = static fn (): array => $plan();

$missingAck = $acks;
$missingAck['options-reader']['acknowledged'] = false;
$missingFence = $acks;
$missingFence['old-source-reader']['reopen_fenced'] = false;
$unexpectedAck = $acks;
$unexpectedAck['old-source-reader']['acknowledged'] = true;
$staleDigest = $acks;
$staleDigest['schema-reader']['image_sha256'] = $hash('stale schema digest');
$staleToken = $acks;
$staleToken['schema-reader']['source_id'] = 'old-current-source';
$staleEpoch = $acks;
$staleEpoch['schema-reader']['epoch'] = 210;
$staleFrame = $acks;
$staleFrame['schema-reader']['checkpoint_frame'] = 36;
$staleCookie = $acks;
$staleCookie['schema-reader']['checkpoint_cookie'] = 21176;
$staleSchema = $acks;
$staleSchema['schema-reader']['schema_cookie'] = 21142;
$orphan = $acks;
$orphan['orphan-reader'] = $ack($basePlan['reader_rows'][0]);
$unpublished = $checkpoint;
$unpublished['checkpoint_published'] = false;
$blockedBase = $base205($readers, $unpublished);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next211'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'checkpoint_current_source_acknowledgements_admit_next_source'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'token' => [static fn (): mixed => $ok()['current_source_token'], $token],
    'checkpoint frame' => [static fn (): mixed => $ok()['checkpoint_frame'], 37],
    'checkpoint cookie' => [static fn (): mixed => $ok()['checkpoint_cookie'], 21177],
    'schema cookie' => [static fn (): mixed => $ok()['schema_cookie'], 21143],
    'page numbers' => [static fn (): mixed => $ok()['page_numbers'], [1, 2, 3, 4]],
    'row count' => [static fn (): mixed => count($ok()['reader_admission_rows']), 7],
    'admitted names' => [static fn (): mixed => $ok()['admitted_reader_names'], ['schema-reader', 'options-reader', 'autoload-index-reader']],
    'reopen names' => [static fn (): mixed => $ok()['reopen_reader_names'], ['old-source-reader', 'dirty-transient-reader', 'stale-image-reader', 'closed-reader']],
    'first row reader' => [static fn (): mixed => $ok()['reader_admission_rows'][0]['reader'], 'schema-reader'],
    'first expected retain' => [static fn (): mixed => $ok()['reader_admission_rows'][0]['expected_action'], 'retain-reader-cache'],
    'first acknowledged' => [static fn (): mixed => $ok()['reader_admission_rows'][0]['acknowledged'], true],
    'first not fenced' => [static fn (): mixed => $ok()['reader_admission_rows'][0]['reopen_fenced'], false],
    'first admitted' => [static fn (): mixed => $ok()['reader_admission_rows'][0]['checkpoint_admitted'], true],
    'first reason' => [static fn (): mixed => $ok()['reader_admission_rows'][0]['reason'], 'reader_acknowledged_checkpoint_current_source_digest'],
    'first transition' => [static fn (): mixed => $ok()['reader_admission_rows'][0]['transition'], 'schema-reader>admit-next-source:next211'],
    'stale source action' => [static fn (): mixed => $ok()['reader_admission_rows'][3]['expected_action'], 'reopen-reader-cache'],
    'stale source fenced' => [static fn (): mixed => $ok()['reader_admission_rows'][3]['reopen_fenced'], true],
    'stale source not admitted' => [static fn (): mixed => $ok()['reader_admission_rows'][3]['checkpoint_admitted'], false],
    'stale source transition' => [static fn (): mixed => $ok()['reader_admission_rows'][3]['transition'], 'old-source-reader>fence-reopen:next211'],
    'dirty fenced' => [static fn (): mixed => $ok()['reader_admission_rows'][4]['reopen_fenced'], true],
    'closed fenced' => [static fn (): mixed => $ok()['reader_admission_rows'][6]['reopen_fenced'], true],
    'missing acknowledgements empty' => [static fn (): mixed => $ok()['missing_acknowledgements'], []],
    'missing fences empty' => [static fn (): mixed => $ok()['missing_reopen_fences'], []],
    'unexpected acks empty' => [static fn (): mixed => $ok()['unexpected_acknowledgements'], []],
    'stale acks empty' => [static fn (): mixed => $ok()['stale_acknowledgements'], []],
    'orphan acks empty' => [static fn (): mixed => $ok()['orphan_acknowledgements'], []],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['next205_reader_plan_ready', 'checkpoint_published', 'journal_removed', 'all_retained_readers_acknowledged', 'all_reopened_readers_fenced', 'no_stale_acknowledgements', 'no_unexpected_stale_reader_acknowledgements', 'no_orphan_acknowledgements']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true, true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $ok()['blocked_guard_names'], []],
    'checkpoint admitted' => [static fn (): mixed => $ok()['checkpoint_admitted'], true],
    'next source epoch' => [static fn (): mixed => $ok()['next_source_epoch'], 212],
    'digest length' => [static fn (): mixed => strlen($ok()['checkpoint_admission_digest']), 64],
    'operation inherited' => [static fn (): mixed => in_array('verify_reader_page_digest_next205', $ok()['operation_names'], true), true],
    'operation ack' => [static fn (): mixed => in_array('acknowledge_reader_page_digest_next211', $ok()['operation_names'], true), true],
    'operation fence' => [static fn (): mixed => in_array('fence_reopened_reader_cache_next211', $ok()['operation_names'], true), true],
    'operation admit next' => [static fn (): mixed => in_array('admit_checkpoint_next_source_after_hot_journal_next211', $ok()['operation_names'], true), true],
    'dependency previous' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next205', $ok()['dependencies'], true), true],
    'dependency next211' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next211', $ok()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-checkpoint-reader-reopen-fence', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat next205 page-image validation'), true],
    'missing ack status' => [static fn (): mixed => $plan(null, $missingAck)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next211'],
    'missing ack reader' => [static fn (): mixed => $plan(null, $missingAck)['missing_acknowledgements'], ['options-reader']],
    'missing ack guard' => [static fn (): mixed => $plan(null, $missingAck)['blocked_guard_names'], ['all_retained_readers_acknowledged']],
    'missing fence reader' => [static fn (): mixed => $plan(null, $missingFence)['missing_reopen_fences'], ['old-source-reader']],
    'missing fence guard' => [static fn (): mixed => $plan(null, $missingFence)['blocked_guard_names'], ['all_reopened_readers_fenced']],
    'unexpected ack reader' => [static fn (): mixed => $plan(null, $unexpectedAck)['unexpected_acknowledgements'], ['old-source-reader']],
    'unexpected ack guard' => [static fn (): mixed => $plan(null, $unexpectedAck)['blocked_guard_names'], ['no_unexpected_stale_reader_acknowledgements']],
    'stale digest reader' => [static fn (): mixed => $plan(null, $staleDigest)['stale_acknowledgements'], ['schema-reader']],
    'stale token reader' => [static fn (): mixed => $plan(null, $staleToken)['stale_acknowledgements'], ['schema-reader']],
    'stale epoch reader' => [static fn (): mixed => $plan(null, $staleEpoch)['stale_acknowledgements'], ['schema-reader']],
    'stale frame reader' => [static fn (): mixed => $plan(null, $staleFrame)['stale_acknowledgements'], ['schema-reader']],
    'stale cookie reader' => [static fn (): mixed => $plan(null, $staleCookie)['stale_acknowledgements'], ['schema-reader']],
    'stale schema reader' => [static fn (): mixed => $plan(null, $staleSchema)['stale_acknowledgements'], ['schema-reader']],
    'stale ack guard' => [static fn (): mixed => $plan(null, $staleDigest)['blocked_guard_names'], ['no_stale_acknowledgements']],
    'orphan ack' => [static fn (): mixed => $plan(null, $orphan)['orphan_acknowledgements'], ['orphan-reader']],
    'orphan guard' => [static fn (): mixed => $plan(null, $orphan)['blocked_guard_names'], ['no_orphan_acknowledgements']],
    'blocked base status' => [static fn (): mixed => $plan($blockedBase)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next211'],
    'blocked base guard' => [static fn (): mixed => $plan($blockedBase)['blocked_guard_names'][0], 'next205_reader_plan_ready'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next211 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing status rejected' => static function () use ($basePlan, $acks): void {
        $bad = $basePlan;
        unset($bad['status']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next211Plan($bad, $acks);
    },
    'empty acknowledgements rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next211Plan($basePlan, []),
    'missing reader digest rejected' => static function () use ($basePlan, $acks): void {
        $bad = $basePlan;
        unset($bad['reader_rows'][0]['observed_image_sha256']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next211Plan($bad, $acks);
    },
    'bad reader page rejected' => static function () use ($basePlan, $acks): void {
        $bad = $basePlan;
        $bad['reader_rows'][0]['page'] = 0;
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next211Plan($bad, $acks);
    },
    'bad token rejected' => static function () use ($basePlan, $acks): void {
        $bad = $basePlan;
        $bad['current_source_token']['id'] = '';
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next211Plan($bad, $acks);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next211 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
