<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$databasePath = '/srv/www/wp-content/database/wp-next205.sqlite';
$hash = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-next205-current-source', 'epoch' => 205];
$checkpoint = [
    'database_path' => $databasePath,
    'wal_path' => $databasePath . '-wal',
    'journal_path' => $databasePath . '-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 31,
    'checkpoint_cookie' => 20577,
    'schema_cookie' => 20543,
    'wal_salt' => 'next205-wal-salt',
    'hot_journal_generation' => 17,
    'savepoint_generation' => 19,
    'cache_generation' => 23,
    'page_digests' => [
        1 => $hash('next205 schema page after checkpoint'),
        2 => $hash('next205 wp_options root after checkpoint'),
        3 => $hash('next205 autoload index after checkpoint'),
        4 => $hash('next205 transient option after checkpoint'),
    ],
    'checkpoint_published' => true,
    'journal_removed' => true,
    'operation_names' => ['admit_current_reader_retry_next195'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next195'],
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
        'image_sha256' => $checkpoint['page_digests'][$page] ?? hash('sha256', 'unknown page'),
    ];

    return array_replace($row, $override ?? []);
};
$readers = [
    $reader('schema-current-reader', 1),
    $reader('options-current-reader', 2),
    $reader('index-current-reader', 3),
    $reader('stale-source-reader', 2, ['source_id' => 'old-source']),
    $reader('stale-epoch-reader', 2, ['epoch' => 204]),
    $reader('stale-frame-reader', 2, ['observed_checkpoint_frame' => 30]),
    $reader('stale-checkpoint-cookie-reader', 2, ['observed_checkpoint_cookie' => 20576]),
    $reader('stale-schema-cookie-reader', 2, ['observed_schema_cookie' => 20542]),
    $reader('stale-wal-salt-reader', 2, ['observed_wal_salt' => 'old-salt']),
    $reader('stale-hot-generation-reader', 2, ['observed_hot_journal_generation' => 16]),
    $reader('stale-savepoint-generation-reader', 2, ['observed_savepoint_generation' => 18]),
    $reader('stale-cache-generation-reader', 2, ['observed_cache_generation' => 22]),
    $reader('stale-page-image-reader', 2, ['image_sha256' => $hash('next205 old wp_options root')]),
    $reader('unknown-page-reader', 9),
    $reader('dirty-page-reader', 2, ['dirty' => true]),
    $reader('closed-page-reader', 2, ['closed' => true]),
];

$plan = static fn (?array $base = null, ?array $rows = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($base ?? $checkpoint, $rows ?? $readers);
$ok = static fn (): array => $plan();
$unpublished = $checkpoint;
$unpublished['checkpoint_published'] = false;
$journalPresent = $checkpoint;
$journalPresent['journal_removed'] = false;
$allCurrent = array_slice($readers, 0, 3);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next205'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'reader_page_images_match_hot_journal_checkpoint_current_source'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'token' => [static fn (): mixed => $ok()['current_source_token'], $token],
    'checkpoint frame' => [static fn (): mixed => $ok()['checkpoint_frame'], 31],
    'checkpoint cookie' => [static fn (): mixed => $ok()['checkpoint_cookie'], 20577],
    'schema cookie' => [static fn (): mixed => $ok()['schema_cookie'], 20543],
    'wal salt' => [static fn (): mixed => $ok()['wal_salt'], 'next205-wal-salt'],
    'hot generation' => [static fn (): mixed => $ok()['hot_journal_generation'], 17],
    'savepoint generation' => [static fn (): mixed => $ok()['savepoint_generation'], 19],
    'cache generation' => [static fn (): mixed => $ok()['cache_generation'], 23],
    'page numbers' => [static fn (): mixed => $ok()['page_numbers'], [1, 2, 3, 4]],
    'checkpoint published' => [static fn (): mixed => $ok()['checkpoint_published'], true],
    'journal removed' => [static fn (): mixed => $ok()['journal_removed'], true],
    'reader count' => [static fn (): mixed => count($ok()['reader_rows']), 16],
    'admitted readers' => [static fn (): mixed => $ok()['admitted_reader_names'], ['schema-current-reader', 'options-current-reader', 'index-current-reader']],
    'reopen readers' => [static fn (): mixed => $ok()['reopen_reader_names'], ['stale-source-reader', 'stale-epoch-reader', 'stale-frame-reader', 'stale-checkpoint-cookie-reader', 'stale-schema-cookie-reader', 'stale-wal-salt-reader', 'stale-hot-generation-reader', 'stale-savepoint-generation-reader', 'stale-cache-generation-reader', 'stale-page-image-reader', 'unknown-page-reader', 'dirty-page-reader', 'closed-page-reader']],
    'first admitted' => [static fn (): mixed => $ok()['reader_rows'][0]['admitted'], true],
    'first no reopen' => [static fn (): mixed => $ok()['reader_rows'][0]['requires_reopen'], false],
    'first reason' => [static fn (): mixed => $ok()['reader_rows'][0]['reason'], 'reader_cache_page_image_matches_hot_journal_checkpoint_source'],
    'first expected digest' => [static fn (): mixed => $ok()['reader_rows'][0]['expected_image_sha256'], $checkpoint['page_digests'][1]],
    'first observed digest' => [static fn (): mixed => $ok()['reader_rows'][0]['observed_image_sha256'], $checkpoint['page_digests'][1]],
    'source failure' => [static fn (): mixed => $ok()['reader_rows'][3]['failed_checks'], ['source_token']],
    'epoch failure' => [static fn (): mixed => $ok()['reader_rows'][4]['failed_checks'], ['source_epoch']],
    'frame failure' => [static fn (): mixed => $ok()['reader_rows'][5]['failed_checks'], ['checkpoint_frame']],
    'checkpoint cookie failure' => [static fn (): mixed => $ok()['reader_rows'][6]['failed_checks'], ['checkpoint_cookie']],
    'schema cookie failure' => [static fn (): mixed => $ok()['reader_rows'][7]['failed_checks'], ['schema_cookie']],
    'wal salt failure' => [static fn (): mixed => $ok()['reader_rows'][8]['failed_checks'], ['wal_salt']],
    'hot generation failure' => [static fn (): mixed => $ok()['reader_rows'][9]['failed_checks'], ['hot_journal_generation']],
    'savepoint generation failure' => [static fn (): mixed => $ok()['reader_rows'][10]['failed_checks'], ['savepoint_generation']],
    'cache generation failure' => [static fn (): mixed => $ok()['reader_rows'][11]['failed_checks'], ['cache_generation']],
    'page image failure' => [static fn (): mixed => $ok()['reader_rows'][12]['failed_checks'], ['page_image']],
    'unknown page failure' => [static fn (): mixed => $ok()['reader_rows'][13]['failed_checks'], ['page_known', 'page_image']],
    'dirty failure' => [static fn (): mixed => $ok()['reader_rows'][14]['failed_checks'], ['not_dirty']],
    'closed failure' => [static fn (): mixed => $ok()['reader_rows'][15]['failed_checks'], ['not_closed']],
    'reopen reason' => [static fn (): mixed => $ok()['reader_rows'][12]['reason'], 'reader_cache_page_image_requires_reopen_after_hot_journal_checkpoint'],
    'admit transition' => [static fn (): mixed => $ok()['reader_rows'][1]['transition'], 'options-current-reader>reuse-page-cache:next205'],
    'reopen transition' => [static fn (): mixed => $ok()['reader_rows'][12]['transition'], 'stale-page-image-reader>reopen-page-cache:next205'],
    'reopen reasons contain page image' => [static fn (): mixed => in_array('page_image', $ok()['reopen_reasons'], true), true],
    'reopen reasons contain cache generation' => [static fn (): mixed => in_array('cache_generation', $ok()['reopen_reasons'], true), true],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['checkpoint_published', 'hot_journal_removed', 'page_digest_map', 'reader_mix', 'current_source_token']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $ok()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => in_array('admit_current_reader_retry_next195', $ok()['operation_names'], true), true],
    'operation verify digest' => [static fn (): mixed => in_array('verify_reader_page_digest_next205', $ok()['operation_names'], true), true],
    'operation reopen cache' => [static fn (): mixed => in_array('reopen_stale_page_cache_next205', $ok()['operation_names'], true), true],
    'digest length' => [static fn (): mixed => strlen($ok()['reader_digest']), 64],
    'dependency previous' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next195', $ok()['dependencies'], true), true],
    'dependency next205' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next205', $ok()['dependencies'], true), true],
    'dependency page image' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-reader-page-image-current-source', $ok()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-reader-cache-reopen-after-hot-journal-checkpoint', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat next195 token-only reader retry admission'), true],
    'unpublished blocked' => [static fn (): mixed => $plan($unpublished)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next205'],
    'unpublished guards' => [static fn (): mixed => $plan($unpublished)['blocked_guard_names'], ['checkpoint_published', 'reader_mix']],
    'unpublished reader failure' => [static fn (): mixed => $plan($unpublished, [$readers[0]])['reader_rows'][0]['failed_checks'], ['checkpoint_published']],
    'journal present blocked' => [static fn (): mixed => $plan($journalPresent)['blocked_guard_names'], ['hot_journal_removed', 'reader_mix']],
    'journal present reader failure' => [static fn (): mixed => $plan($journalPresent, [$readers[0]])['reader_rows'][0]['failed_checks'], ['journal_removed']],
    'all current blocked' => [static fn (): mixed => $plan(null, $allCurrent)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next205'],
    'all current guard' => [static fn (): mixed => $plan(null, $allCurrent)['blocked_guard_names'], ['reader_mix']],
    'all current reopen empty' => [static fn (): mixed => $plan(null, $allCurrent)['reopen_reader_names'], []],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next205 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'missing page digests rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $checkpoint;
        unset($bad['page_digests']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($bad, $readers);
    },
    'bad digest rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $checkpoint;
        $bad['page_digests'][2] = 'not-a-sha';
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($bad, $readers);
    },
    'empty readers rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($checkpoint, []),
    'missing reader digest rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $readers;
        unset($bad[0]['image_sha256']);
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($checkpoint, $bad);
    },
    'bad reader digest rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $readers;
        $bad[0]['image_sha256'] = 'bad-digest';
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($checkpoint, $bad);
    },
    'bad reader page rejected' => static function () use ($checkpoint, $readers): void {
        $bad = $readers;
        $bad[0]['page'] = 0;
        SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next205Plan($checkpoint, $bad);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next205 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
