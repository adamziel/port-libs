<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next188.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('next188 dirty schema after copied import'),
    $page('next188 dirty wp_options root after hot journal'),
    $page('next188 dirty active_plugins after savepoint'),
    $page('next188 dirty cron after checkpoint'),
    $page('next188 clean usermeta root'),
]);
$hot = [2 => $page('next188 hot journal clean wp_options root')];
$savepointBefore = [3 => $page('next188 savepoint before active_plugins')];
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
    [1, 0, 'next188 current schema draft'],
    [2, 5, 'next188 current wp_options commit'],
    [4, 5, 'next188 current cron commit'],
], 188, 0x18800101, 0x18800102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next188 next active_plugins retry draft'],
    [4, 5, 'next188 next cron commit'],
], 189, 0x18900101, 0x18900102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);
$currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
$nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next188',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next188 current schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5],
    [['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1]],
    [
        ['name' => 'bootstrap-statement-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
        ['name' => 'bootstrap-statement-stale', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
    ],
    [
        ['name' => 'bootstrap-reader-current', 'source_id' => 'bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
        ['name' => 'bootstrap-reader-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
    ],
    44,
    45,
    700,
    701,
    null,
    null,
    'restart',
    3,
    188
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];

$statements = [
    ['name' => 'select-usermeta-current-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'sql' => 'SELECT meta_value FROM wp_usermeta WHERE user_id=?', 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'cursor_page' => 5, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
    ['name' => 'select-stale-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
    ['name' => 'select-stale-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 43],
    ['name' => 'select-next-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 701, 'observed_schema_cookie' => 45],
    ['name' => 'select-stale-generation-before-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
    ['name' => 'select-hot-root-before-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [2], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
];
$readers = [
    ['name' => 'reader-current-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44, 'pinned' => true],
    ['name' => 'reader-stale-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 699, 'observed_schema_cookie' => 44],
    ['name' => 'reader-stale-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 43],
    ['name' => 'reader-next-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 701, 'observed_schema_cookie' => 45],
    ['name' => 'reader-stale-generation-before-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 187, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
    ['name' => 'reader-old-token-before-hook', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700, 'observed_schema_cookie' => 44],
];

$plan = static fn (?array $statementRows = null, ?array $readerRows = null, int $currentHook = 700, int $nextHook = 701): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next188Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next188',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next188 current schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']]],
    [1, 2, 3, 4, 5],
    [
        ['name' => 'base-current-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
        ['name' => 'base-stale-reader', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ],
    $statementRows ?? $statements,
    $readerRows ?? $readers,
    44,
    45,
    $currentHook,
    $nextHook,
    $currentToken,
    $nextToken,
    'restart',
    3,
    188
);
$ok = static fn (): array => $plan();
$allCurrent = static fn (): array => $plan([$statements[0]], [$readers[0]]);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next188'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'prepared_statements_and_readers_admitted_by_commit_hook_after_wal_generation'],
    'base status' => [static fn (): mixed => $ok()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next185'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ok()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ok()['savepoint'], 'plugin-import-next188'],
    'mode' => [static fn (): mixed => $ok()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $ok()['reader_end_frame'], 3],
    'current sequence' => [static fn (): mixed => $ok()['current_checkpoint_sequence'], 188],
    'next sequence' => [static fn (): mixed => $ok()['next_checkpoint_sequence'], 189],
    'current salt' => [static fn (): mixed => $ok()['current_wal_salt'], $currentSalt],
    'next salt' => [static fn (): mixed => $ok()['next_wal_salt'], $nextSalt],
    'current schema' => [static fn (): mixed => $ok()['current_schema_cookie'], 44],
    'next schema' => [static fn (): mixed => $ok()['next_schema_cookie'], 45],
    'current hook' => [static fn (): mixed => $ok()['current_commit_hook'], 700],
    'next hook' => [static fn (): mixed => $ok()['next_commit_hook'], 701],
    'admitted statements' => [static fn (): mixed => $ok()['admitted_statement_names'], ['select-usermeta-current-hook']],
    'reprepare statements' => [static fn (): mixed => $ok()['reprepare_statement_names'], ['select-stale-hook', 'select-stale-schema', 'select-next-hook', 'select-stale-generation-before-hook', 'select-hot-root-before-hook']],
    'admitted readers' => [static fn (): mixed => $ok()['admitted_reader_names'], ['reader-current-hook']],
    'reopen readers' => [static fn (): mixed => $ok()['reopen_reader_names'], ['reader-stale-hook', 'reader-stale-schema', 'reader-next-hook', 'reader-stale-generation-before-hook', 'reader-old-token-before-hook']],
    'statement row count' => [static fn (): mixed => count($ok()['statement_rows']), 6],
    'reader row count' => [static fn (): mixed => count($ok()['reader_rows']), 6],
    'statement current reason' => [static fn (): mixed => $ok()['statement_rows'][0]['hook_reason'], 'statement_commit_hook_matches_current_source'],
    'statement stale hook reason' => [static fn (): mixed => $ok()['statement_rows'][1]['hook_reason'], 'statement_commit_hook_predates_current_source'],
    'statement stale schema reason' => [static fn (): mixed => $ok()['statement_rows'][2]['hook_reason'], 'statement_schema_cookie_predates_current_source'],
    'statement next hook reason' => [static fn (): mixed => $ok()['statement_rows'][3]['hook_reason'], 'statement_observed_next_commit_hook_before_reprepare'],
    'statement stale generation retained' => [static fn (): mixed => $ok()['statement_rows'][4]['hook_reason'], 'statement_checkpoint_sequence_predates_current_wal_generation'],
    'statement hot root retained' => [static fn (): mixed => $ok()['statement_rows'][5]['hook_reason'], 'statement_root_page_touched_by_hot_journal_or_savepoint_checkpoint'],
    'reader current reason' => [static fn (): mixed => $ok()['reader_rows'][0]['hook_reason'], 'reader_commit_hook_matches_current_source'],
    'reader stale hook reason' => [static fn (): mixed => $ok()['reader_rows'][1]['hook_reason'], 'reader_commit_hook_predates_current_source'],
    'reader stale schema reason' => [static fn (): mixed => $ok()['reader_rows'][2]['hook_reason'], 'reader_schema_cookie_predates_current_source'],
    'reader next hook reason' => [static fn (): mixed => $ok()['reader_rows'][3]['hook_reason'], 'reader_observed_next_commit_hook_before_reprepare'],
    'reader stale generation retained' => [static fn (): mixed => $ok()['reader_rows'][4]['hook_reason'], 'reader_checkpoint_sequence_predates_current_wal_generation'],
    'reader old token retained' => [static fn (): mixed => $ok()['reader_rows'][5]['hook_reason'], 'reader_source_token_predates_checkpoint_current_source'],
    'reader pinned preserved' => [static fn (): mixed => $ok()['reader_rows'][0]['pinned'], true],
    'cursor page preserved' => [static fn (): mixed => $ok()['statement_rows'][0]['cursor_page'], 5],
    'sql preserved' => [static fn (): mixed => str_contains($ok()['statement_rows'][0]['sql'], 'wp_usermeta'), true],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['base_generation_current_source', 'commit_hook_forward', 'statement_commit_hook_mix', 'reader_commit_hook_mix']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true, true]],
    'stale guards empty' => [static fn (): mixed => $ok()['stale_guard_names'], []],
    'operation admit statement' => [static fn (): mixed => in_array('admit_commit_hook_current_source_next188', $ok()['operation_names'], true), true],
    'operation reprepare statement' => [static fn (): mixed => in_array('reprepare_commit_hook_current_source_next188', $ok()['operation_names'], true), true],
    'operation retain reader' => [static fn (): mixed => in_array('retain_reader_commit_hook_next188', $ok()['operation_names'], true), true],
    'operation reopen reader' => [static fn (): mixed => in_array('reopen_reader_commit_hook_next188', $ok()['operation_names'], true), true],
    'operation final publish' => [static fn (): mixed => end($ok()['operation_names']), 'publish_commit_hook_current_source_next188'],
    'hook digest length' => [static fn (): mixed => strlen($ok()['hook_digest']), 64],
    'dependency next188 present' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next188', $ok()['dependencies'], true), true],
    'dependency hook present' => [static fn (): mixed => in_array('sqlite-wal-commit-hook-prepared-statement-reader-admission', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'next185 salt/sequence generation checks'), true],
    'all current blocked status' => [static fn (): mixed => $allCurrent()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next188'],
    'all current stale guards' => [static fn (): mixed => $allCurrent()['stale_guard_names'], ['base_generation_current_source', 'statement_commit_hook_mix', 'reader_commit_hook_mix']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next188 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'backwards hook rejected' => static fn () => $plan($statements, $readers, 701, 700),
    'negative current hook rejected' => static fn () => $plan($statements, $readers, -1, 700),
    'statement missing hook rejected' => static fn () => $plan([['name' => 'missing-hook', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_schema_cookie' => 44]], $readers),
    'statement missing schema rejected' => static fn () => $plan([['name' => 'missing-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 44, 'root_pages' => [5], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700]], $readers),
    'reader missing hook rejected' => static fn () => $plan($statements, [['name' => 'bad-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_schema_cookie' => 44]]),
    'reader missing schema rejected' => static fn () => $plan($statements, [['name' => 'bad-reader-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'observed_checkpoint_sequence' => 188, 'observed_salt' => $currentSalt, 'observed_commit_hook' => 700]]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next188 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
