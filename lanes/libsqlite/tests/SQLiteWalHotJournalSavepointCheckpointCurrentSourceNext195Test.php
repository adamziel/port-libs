<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next195.sqlite';
$token = ['id' => 'wp-next195-current-source', 'epoch' => 195];
$checkpoint = [
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'journal_path' => $databasePath . '-journal',
    'current_source_token' => $token,
    'checkpoint_cookie' => 19577,
    'schema_cookie' => 43,
    'wal_salt' => 'next195-wal-salt',
    'hot_journal_generation' => 7,
    'savepoint_generation' => 11,
    'journal_removed' => true,
    'checkpoint_published' => true,
    'operation_names' => ['publish_hot_journal_checkpoint_current_source_next188'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188'],
];
$hash = static fn (string $label): string => hash('sha256', $label);
$readers = [
    ['name' => 'options-current-reader', 'page' => 2, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11, 'image_sha256' => $hash('options-current')],
    ['name' => 'schema-current-reader', 'page' => 1, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11],
    ['name' => 'stale-source-reader', 'page' => 3, 'source_id' => 'old-source', 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11],
    ['name' => 'stale-epoch-reader', 'page' => 4, 'source_id' => $token['id'], 'epoch' => 194, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11],
    ['name' => 'stale-checkpoint-reader', 'page' => 5, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19576, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11],
    ['name' => 'stale-schema-reader', 'page' => 6, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 42, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11],
    ['name' => 'stale-wal-salt-reader', 'page' => 7, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'old-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11],
    ['name' => 'stale-hot-generation-reader', 'page' => 8, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 6, 'observed_savepoint_generation' => 11],
    ['name' => 'stale-savepoint-reader', 'page' => 9, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 10],
    ['name' => 'dirty-reader', 'page' => 10, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11, 'dirty' => true],
    ['name' => 'closed-reader', 'page' => 11, 'source_id' => $token['id'], 'epoch' => 195, 'observed_checkpoint_cookie' => 19577, 'observed_schema_cookie' => 43, 'observed_wal_salt' => 'next195-wal-salt', 'observed_hot_journal_generation' => 7, 'observed_savepoint_generation' => 11, 'closed' => true],
];

$plan = static fn (?array $base = null, ?array $rows = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next195Plan($base ?? $checkpoint, $rows ?? $readers);
$ok = static fn (): array => $plan();
$unpublished = $checkpoint;
$unpublished['checkpoint_published'] = false;
$journalPresent = $checkpoint;
$journalPresent['journal_removed'] = false;
$allCurrent = array_slice($readers, 0, 2);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next195'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'reader_retry_handles_match_hot_journal_savepoint_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'token' => [static fn (): mixed => $ok()['current_source_token'], $token],
    'checkpoint cookie' => [static fn (): mixed => $ok()['checkpoint_cookie'], 19577],
    'schema cookie' => [static fn (): mixed => $ok()['schema_cookie'], 43],
    'wal salt' => [static fn (): mixed => $ok()['wal_salt'], 'next195-wal-salt'],
    'hot generation' => [static fn (): mixed => $ok()['hot_journal_generation'], 7],
    'savepoint generation' => [static fn (): mixed => $ok()['savepoint_generation'], 11],
    'journal removed' => [static fn (): mixed => $ok()['journal_removed'], true],
    'checkpoint published' => [static fn (): mixed => $ok()['checkpoint_published'], true],
    'reader count' => [static fn (): mixed => count($ok()['reader_rows']), 11],
    'admitted readers' => [static fn (): mixed => $ok()['admitted_reader_names'], ['options-current-reader', 'schema-current-reader']],
    'reopen readers' => [static fn (): mixed => $ok()['reopen_reader_names'], ['stale-source-reader', 'stale-epoch-reader', 'stale-checkpoint-reader', 'stale-schema-reader', 'stale-wal-salt-reader', 'stale-hot-generation-reader', 'stale-savepoint-reader', 'dirty-reader', 'closed-reader']],
    'first admitted' => [static fn (): mixed => $ok()['reader_rows'][0]['admitted'], true],
    'first no reopen' => [static fn (): mixed => $ok()['reader_rows'][0]['requires_reopen'], false],
    'first reason' => [static fn (): mixed => $ok()['reader_rows'][0]['reason'], 'reader_retry_matches_current_hot_journal_checkpoint_source'],
    'first image' => [static fn (): mixed => $ok()['reader_rows'][0]['image_sha256'], $hash('options-current')],
    'source failure' => [static fn (): mixed => $ok()['reader_rows'][2]['failed_checks'], ['source_token']],
    'epoch failure' => [static fn (): mixed => $ok()['reader_rows'][3]['failed_checks'], ['source_epoch']],
    'checkpoint failure' => [static fn (): mixed => $ok()['reader_rows'][4]['failed_checks'], ['checkpoint_cookie']],
    'schema failure' => [static fn (): mixed => $ok()['reader_rows'][5]['failed_checks'], ['schema_cookie']],
    'wal salt failure' => [static fn (): mixed => $ok()['reader_rows'][6]['failed_checks'], ['wal_salt']],
    'hot generation failure' => [static fn (): mixed => $ok()['reader_rows'][7]['failed_checks'], ['hot_journal_generation']],
    'savepoint generation failure' => [static fn (): mixed => $ok()['reader_rows'][8]['failed_checks'], ['savepoint_generation']],
    'dirty failure' => [static fn (): mixed => $ok()['reader_rows'][9]['failed_checks'], ['not_dirty']],
    'closed failure' => [static fn (): mixed => $ok()['reader_rows'][10]['failed_checks'], ['not_closed']],
    'reopen reason' => [static fn (): mixed => $ok()['reader_rows'][2]['reason'], 'reader_retry_must_reopen_after_hot_journal_checkpoint'],
    'admit transition' => [static fn (): mixed => $ok()['reader_rows'][0]['transition'], 'options-current-reader>admit-current-reader:next195'],
    'reopen transition' => [static fn (): mixed => $ok()['reader_rows'][2]['transition'], 'stale-source-reader>reopen-current-reader:next195'],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['checkpoint_published', 'hot_journal_removed', 'reader_mix', 'current_source_token']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $ok()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => in_array('publish_hot_journal_checkpoint_current_source_next188', $ok()['operation_names'], true), true],
    'operation admit' => [static fn (): mixed => in_array('admit_current_reader_retry_next195', $ok()['operation_names'], true), true],
    'operation reopen' => [static fn (): mixed => in_array('force_reopen_stale_reader_retry_next195', $ok()['operation_names'], true), true],
    'digest length' => [static fn (): mixed => strlen($ok()['reader_digest']), 64],
    'dependency previous' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188', $ok()['dependencies'], true), true],
    'dependency next195' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next195', $ok()['dependencies'], true), true],
    'dependency reader retry' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-retry-current-source', $ok()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-reader-reopen-after-hot-journal-checkpoint', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'unpublished blocked' => [static fn (): mixed => $plan($unpublished)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next195'],
    'unpublished guard' => [static fn (): mixed => $plan($unpublished)['blocked_guard_names'], ['checkpoint_published', 'reader_mix']],
    'journal present blocked' => [static fn (): mixed => $plan($journalPresent)['blocked_guard_names'], ['hot_journal_removed', 'reader_mix']],
    'journal present reader failures include journal' => [static fn (): mixed => $plan($journalPresent)['reader_rows'][0]['failed_checks'], ['journal_removed']],
    'all current blocked' => [static fn (): mixed => $plan(null, $allCurrent)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next195'],
    'all current guard' => [static fn (): mixed => $plan(null, $allCurrent)['blocked_guard_names'], ['reader_mix']],
    'all current reopen empty' => [static fn (): mixed => $plan(null, $allCurrent)['reopen_reader_names'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next195 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing checkpoint token rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $checkpoint;
        unset($bad['current_source_token']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next195Plan($bad, $readers);
    },
    'bad token rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $checkpoint;
        $bad['current_source_token'] = ['id' => '', 'epoch' => 0];
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next195Plan($bad, $readers);
    },
    'empty readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next195Plan($checkpoint, []),
    'missing reader name rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $readers;
        unset($bad[0]['name']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next195Plan($checkpoint, $bad);
    },
    'bad reader page rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $readers;
        $bad[0]['page'] = 0;
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next195Plan($checkpoint, $bad);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next195 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
