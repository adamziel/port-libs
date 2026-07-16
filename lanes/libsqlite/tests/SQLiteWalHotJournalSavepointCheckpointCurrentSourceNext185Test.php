<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next185.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('next185 dirty schema after copied import'),
    $page('next185 dirty wp_options root after hot journal'),
    $page('next185 dirty active_plugins after savepoint'),
    $page('next185 dirty cron after checkpoint'),
    $page('next185 clean usermeta root'),
]);
$hot = [2 => $page('next185 hot journal clean wp_options root')];
$savepointBefore = [3 => $page('next185 savepoint before active_plugins')];
$makeWalBytes = static function (array $frames, int $checkpoint, int $salt1, int $salt2) use ($pageSize, $page): string {
    $prefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $checkpoint, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($prefix, false);
    $bytes = $prefix . pack('N*', $seed[0], $seed[1]);
    foreach ($frames as [$pageNumber, $commitPageCount, $label]) {
        $image = $page($label);
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$currentWalBytes = $makeWalBytes([
    [1, 0, 'next185 current schema draft'],
    [2, 5, 'next185 current wp_options commit'],
    [4, 5, 'next185 current cron commit'],
], 185, 0x18500101, 0x18500102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next185 next active_plugins retry draft'],
    [4, 5, 'next185 next cron commit'],
], 186, 0x18600101, 0x18600102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
$nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next185Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next185',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next185 current schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-statement-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
        ['name' => 'bootstrap-statement-stale', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
    ],
    44,
    45,
    null,
    null,
    'restart',
    3,
    185
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];

$statements = [
    ['name' => 'select-usermeta-current-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'sql' => 'SELECT meta_value FROM wp_usermeta WHERE user_id=?', 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt, 'cursor_page' => 5],
    ['name' => 'select-options-hot-root', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [2], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
    ['name' => 'select-stale-sequence', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
    ['name' => 'select-stale-salt', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 185, 'observed_salt' => [0x18500101, 0x1850ffff]],
    ['name' => 'select-next-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 186, 'observed_salt' => $nextSalt],
    ['name' => 'select-token-stale-before-generation', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
    ['name' => 'select-dirty-current-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'dirty' => true, 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
];
$readers = [
    ['name' => 'reader-current-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt, 'pinned' => true],
    ['name' => 'reader-stale-sequence', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 184, 'observed_salt' => $currentSalt],
    ['name' => 'reader-stale-salt', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 185, 'observed_salt' => [0x18500101, 0x1850eeee]],
    ['name' => 'reader-next-generation', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 186, 'observed_salt' => $nextSalt],
    ['name' => 'reader-old-token', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt],
    ['name' => 'reader-dirty', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt, 'dirty' => true],
];

$plan = static fn (?array $statementRows = null, ?array $readerRows = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next185Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next185',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next185 current schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ],
    $statementRows ?? $statements,
    $readerRows ?? $readers,
    44,
    45,
    $currentToken,
    $nextToken,
    'restart',
    3,
    185
);
$ok = static fn (): array => $plan();
$allCurrent = static fn (): array => $plan([$statements[0]], [$readers[0]]);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next185'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'prepared_statements_and_readers_admitted_by_checkpoint_generation_after_hot_journal_savepoint'],
    'base status' => [static fn (): mixed => $ok()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next182'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ok()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ok()['savepoint'], 'plugin-import-next185'],
    'mode' => [static fn (): mixed => $ok()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $ok()['reader_end_frame'], 3],
    'current sequence' => [static fn (): mixed => $ok()['current_checkpoint_sequence'], 185],
    'next sequence' => [static fn (): mixed => $ok()['next_checkpoint_sequence'], 186],
    'current salt' => [static fn (): mixed => $ok()['current_wal_salt'], $currentSalt],
    'next salt' => [static fn (): mixed => $ok()['next_wal_salt'], $nextSalt],
    'current token' => [static fn (): mixed => $ok()['current_source_token'], $currentToken],
    'next token' => [static fn (): mixed => $ok()['next_source_token'], $nextToken],
    'admitted statements' => [static fn (): mixed => $ok()['admitted_statement_names'], ['select-usermeta-current-generation']],
    'reprepare statements' => [static fn (): mixed => $ok()['reprepare_statement_names'], ['select-options-hot-root', 'select-stale-sequence', 'select-stale-salt', 'select-next-generation', 'select-token-stale-before-generation', 'select-dirty-current-generation']],
    'admitted readers' => [static fn (): mixed => $ok()['admitted_reader_names'], ['reader-current-generation']],
    'reopen readers' => [static fn (): mixed => $ok()['reopen_reader_names'], ['reader-stale-sequence', 'reader-stale-salt', 'reader-next-generation', 'reader-old-token', 'reader-dirty']],
    'statement row count' => [static fn (): mixed => count($ok()['statement_rows']), 7],
    'reader row count' => [static fn (): mixed => count($ok()['reader_rows']), 6],
    'first statement generation reason' => [static fn (): mixed => $ok()['statement_rows'][0]['generation_reason'], 'statement_checkpoint_generation_matches_current_wal'],
    'hot root base reason retained' => [static fn (): mixed => $ok()['statement_rows'][1]['generation_reason'], 'statement_root_page_touched_by_hot_journal_or_savepoint_checkpoint'],
    'stale sequence reason' => [static fn (): mixed => $ok()['statement_rows'][2]['generation_reason'], 'statement_checkpoint_sequence_predates_current_wal_generation'],
    'stale salt reason' => [static fn (): mixed => $ok()['statement_rows'][3]['generation_reason'], 'statement_wal_salt_predates_current_checkpoint_generation'],
    'next generation reason' => [static fn (): mixed => $ok()['statement_rows'][4]['generation_reason'], 'statement_observed_next_wal_generation_before_reprepare'],
    'token stale base reason retained' => [static fn (): mixed => $ok()['statement_rows'][5]['generation_reason'], 'statement_source_token_predates_checkpoint_current_source'],
    'dirty base reason retained' => [static fn (): mixed => $ok()['statement_rows'][6]['generation_reason'], 'statement_dirty_after_failed_savepoint'],
    'cursor page preserved' => [static fn (): mixed => $ok()['statement_rows'][0]['cursor_page'], 5],
    'sql preserved' => [static fn (): mixed => str_contains($ok()['statement_rows'][0]['sql'], 'wp_usermeta'), true],
    'reader current reason' => [static fn (): mixed => $ok()['reader_rows'][0]['generation_reason'], 'reader_checkpoint_generation_matches_current_wal'],
    'reader stale sequence reason' => [static fn (): mixed => $ok()['reader_rows'][1]['generation_reason'], 'reader_checkpoint_sequence_predates_current_wal_generation'],
    'reader stale salt reason' => [static fn (): mixed => $ok()['reader_rows'][2]['generation_reason'], 'reader_wal_salt_predates_current_checkpoint_generation'],
    'reader next reason' => [static fn (): mixed => $ok()['reader_rows'][3]['generation_reason'], 'reader_observed_next_wal_generation_before_reopen'],
    'reader old token reason' => [static fn (): mixed => $ok()['reader_rows'][4]['generation_reason'], 'reader_source_token_predates_checkpoint_current_source'],
    'reader dirty reason' => [static fn (): mixed => $ok()['reader_rows'][5]['generation_reason'], 'reader_dirty_after_failed_savepoint'],
    'reader pinned preserved' => [static fn (): mixed => $ok()['reader_rows'][0]['pinned'], true],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['base_statement_current_source', 'statement_generation_mix', 'reader_generation_mix']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true]],
    'stale guards empty' => [static fn (): mixed => $ok()['stale_guard_names'], []],
    'operation admit statement' => [static fn (): mixed => in_array('admit_checkpoint_generation_current_source_next185', $ok()['operation_names'], true), true],
    'operation reprepare statement' => [static fn (): mixed => in_array('reprepare_checkpoint_generation_current_source_next185', $ok()['operation_names'], true), true],
    'operation retain reader' => [static fn (): mixed => in_array('retain_reader_checkpoint_generation_next185', $ok()['operation_names'], true), true],
    'operation reopen reader' => [static fn (): mixed => in_array('reopen_reader_checkpoint_generation_next185', $ok()['operation_names'], true), true],
    'operation final publish' => [static fn (): mixed => end($ok()['operation_names']), 'publish_checkpoint_generation_current_source_next185'],
    'generation digest length' => [static fn (): mixed => strlen($ok()['generation_digest']), 64],
    'dependency next185 present' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next185', $ok()['dependencies'], true), true],
    'dependency generation present' => [static fn (): mixed => in_array('sqlite-wal-checkpoint-generation-prepared-statement-admission', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat WAL byte truncation'), true],
    'all current blocked status' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next185'],
    'all current stale guards' => [static fn (): mixed => $allCurrent()['stale_guard_names'], ['base_statement_current_source', 'statement_generation_mix', 'reader_generation_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next185 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty statements rejected' => static fn () => $plan([], $readers),
    'empty readers rejected' => static fn () => $plan($statements, []),
    'statement missing sequence rejected' => static fn () => $plan([['name' => 'missing-sequence', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_salt' => $currentSalt]], $readers),
    'statement bad salt rejected' => static fn () => $plan([['name' => 'bad-salt', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 185, 'observed_salt' => [1]]], $readers),
    'statement bad cursor rejected' => static fn () => $plan([['name' => 'bad-cursor', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 185, 'observed_salt' => $currentSalt, 'cursor_page' => 0]], $readers),
    'reader missing sequence rejected' => static fn () => $plan($statements, [['name' => 'bad-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_salt' => $currentSalt]]),
    'reader bad salt rejected' => static fn () => $plan($statements, [['name' => 'bad-reader-salt', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 185, 'observed_salt' => ['x', 2]]]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next185 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
