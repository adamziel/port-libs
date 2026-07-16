<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next182.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databaseBytes = implode('', [
    $page('next182 dirty schema after copied import'),
    $page('next182 dirty wp_options root after copied import'),
    $page('next182 dirty active_plugins after copied import'),
    $page('next182 dirty autoload index after copied import'),
    $page('next182 dirty cron option after copied import'),
    $page('next182 dirty usermeta root after copied import'),
]);
$hot = [
    2 => $page('next182 hot journal clean wp_options root'),
    4 => $page('next182 hot journal clean autoload index'),
];
$savepointBefore = [
    3 => $page('next182 savepoint before active_plugins retry'),
    5 => $page('next182 savepoint before cron retry'),
];

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
    [1, 0, 'next182 current wal schema draft'],
    [2, 6, 'next182 current wal wp_options commit'],
    [4, 0, 'next182 current wal autoload draft'],
    [5, 6, 'next182 current wal cron commit'],
], 182, 0x18200101, 0x18200102);
$nextWalBytes = $makeWalBytes([
    [3, 0, 'next182 next wal active_plugins retry draft'],
    [5, 6, 'next182 next wal cron commit'],
], 183, 0x18300101, 0x18300102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$bootstrap = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next182Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next182',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    [1 => ['image' => $page('next182 current wal schema draft'), 'source_id' => 'bootstrap', 'epoch' => 1]],
    [1, 2, 3, 4, 5, 6],
    [
        ['name' => 'bootstrap-current', 'source_id' => 'bootstrap', 'epoch' => 1],
        ['name' => 'bootstrap-stale', 'source_id' => 'old-bootstrap', 'epoch' => 1],
    ],
    [
        ['name' => 'bootstrap-statement', 'source_id' => 'bootstrap', 'epoch' => 1, 'schema_cookie' => 41],
        ['name' => 'bootstrap-old', 'source_id' => 'old-bootstrap', 'epoch' => 1, 'schema_cookie' => 40],
    ],
    41,
    42,
    null,
    null,
    null,
    'restart',
    4,
    182
);
$currentToken = $bootstrap['current_source_token'];
$nextToken = $bootstrap['next_source_token'];

$cache = [
    1 => ['image' => $page('next182 current wal schema draft'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'schema cache current'],
    2 => ['image' => $page('next182 current wal wp_options commit'), 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'label' => 'wp_options stale token'],
    3 => ['image' => $savepointBefore[3], 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1, 'label' => 'active_plugins stale epoch'],
    4 => ['image' => $page('next182 stale autoload cache'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'label' => 'autoload stale image'],
    5 => ['image' => $page('next182 current wal cron commit'), 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true, 'label' => 'cron dirty cache'],
];
$readers = [
    ['name' => 'wp-current-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-pinned-options', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'pinned' => true],
    ['name' => 'wp-stale-token', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch']],
    ['name' => 'wp-stale-epoch', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1],
    ['name' => 'wp-next-reader', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch']],
    ['name' => 'wp-dirty-reader', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'dirty' => true],
];
$statements = [
    ['name' => 'select-usermeta-clean', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [6], 'sql' => 'SELECT meta_value FROM wp_usermeta WHERE user_id=?'],
    ['name' => 'select-options-root-hot', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [2], 'sql' => 'SELECT option_value FROM wp_options WHERE option_name=?'],
    ['name' => 'select-active-plugins-savepoint', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [3], 'sql' => 'SELECT option_value FROM wp_options WHERE option_name="active_plugins"'],
    ['name' => 'select-stale-token', 'source_id' => 'old-token', 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [6]],
    ['name' => 'select-stale-epoch', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'] - 1, 'schema_cookie' => 41, 'root_pages' => [6]],
    ['name' => 'select-stale-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 40, 'root_pages' => [6]],
    ['name' => 'select-next-source', 'source_id' => $nextToken['id'], 'epoch' => $nextToken['epoch'], 'schema_cookie' => 42, 'root_pages' => [6]],
    ['name' => 'select-dirty', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [6], 'dirty' => true],
    ['name' => 'select-closed', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [6], 'closed' => true],
];

$plan = static fn (
    ?array $statementRows = null,
    int $currentCookie = 41,
    int $nextCookie = 42,
    ?array $expectedCurrent = null,
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next182Plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next182',
    $hot,
    $savepointBefore,
    $currentWal,
    $currentWalBytes,
    $nextWal,
    $nextWalBytes,
    $cache,
    [1, 2, 3, 4, 5, 6],
    $readers,
    $statementRows ?? $statements,
    $currentCookie,
    $nextCookie,
    $expectedCurrent ?? $currentToken,
    $nextToken,
    null,
    'restart',
    4,
    182
);
$ok = static fn (): array => $plan();
$sameCookie = static fn (): array => $plan([
    ['name' => 'select-options-root-stable-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [2]],
    ['name' => 'select-usermeta-stable-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [6]],
], 41, 41);
$stalePublication = static fn (): array => $plan(null, 41, 42, ['id' => 'stale-current-token', 'epoch' => $currentToken['epoch']]);

$cases = [
    'status' => [static fn (): mixed => $ok()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next182'],
    'reason' => [static fn (): mixed => $ok()['reason'], 'prepared_statement_cache_rebased_after_hot_journal_savepoint_checkpoint_current_source'],
    'base status' => [static fn (): mixed => $ok()['base_status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next167'],
    'database path' => [static fn (): mixed => $ok()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $ok()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $ok()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $ok()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $ok()['savepoint'], 'plugin-import-next182'],
    'mode' => [static fn (): mixed => $ok()['mode'], 'restart'],
    'reader frame' => [static fn (): mixed => $ok()['reader_end_frame'], 4],
    'current token' => [static fn (): mixed => $ok()['current_source_token'], $currentToken],
    'next token' => [static fn (): mixed => $ok()['next_source_token'], $nextToken],
    'fingerprint length' => [static fn (): mixed => strlen($ok()['publication_fingerprint']), 64],
    'current schema cookie' => [static fn (): mixed => $ok()['current_schema_cookie'], 41],
    'next schema cookie' => [static fn (): mixed => $ok()['next_schema_cookie'], 42],
    'schema changed' => [static fn (): mixed => $ok()['schema_cookie_changed'], true],
    'changed pages' => [static fn (): mixed => $ok()['changed_page_numbers'], [2, 3, 4, 5]],
    'admitted statements' => [static fn (): mixed => $ok()['admitted_statement_names'], ['select-usermeta-clean']],
    'reprepare statements' => [static fn (): mixed => $ok()['reprepare_statement_names'], [
        'select-options-root-hot',
        'select-active-plugins-savepoint',
        'select-stale-token',
        'select-stale-epoch',
        'select-stale-schema',
        'select-next-source',
        'select-dirty',
        'select-closed',
    ]],
    'reprepare count' => [static fn (): mixed => $ok()['statement_reprepare_count'], 8],
    'statement row count' => [static fn (): mixed => count($ok()['statement_rows']), 9],
    'first statement admitted' => [static fn (): mixed => $ok()['statement_rows'][0]['admitted'], true],
    'first statement reason' => [static fn (): mixed => $ok()['statement_rows'][0]['reason'], 'statement_matches_checkpoint_current_source'],
    'hot root reason' => [static fn (): mixed => $ok()['statement_rows'][1]['reason'], 'statement_root_page_touched_by_hot_journal_or_savepoint_checkpoint'],
    'savepoint root reason' => [static fn (): mixed => $ok()['statement_rows'][2]['reason'], 'statement_root_page_touched_by_hot_journal_or_savepoint_checkpoint'],
    'stale token reason' => [static fn (): mixed => $ok()['statement_rows'][3]['reason'], 'statement_source_token_predates_checkpoint_current_source'],
    'stale epoch reason' => [static fn (): mixed => $ok()['statement_rows'][4]['reason'], 'statement_epoch_predates_checkpoint_current_source'],
    'stale schema reason' => [static fn (): mixed => $ok()['statement_rows'][5]['reason'], 'statement_schema_cookie_predates_checkpoint_current_source'],
    'next source reason' => [static fn (): mixed => $ok()['statement_rows'][6]['reason'], 'statement_already_reprepared_on_next_wal_source'],
    'dirty reason' => [static fn (): mixed => $ok()['statement_rows'][7]['reason'], 'statement_dirty_after_failed_savepoint'],
    'closed reason' => [static fn (): mixed => $ok()['statement_rows'][8]['reason'], 'statement_closed_before_checkpoint_publish'],
    'root pages normalized' => [static fn (): mixed => $ok()['statement_rows'][1]['root_pages'], [2]],
    'sql preserved' => [static fn (): mixed => str_contains($ok()['statement_rows'][0]['sql'], 'wp_usermeta'), true],
    'guard names' => [static fn (): mixed => $ok()['guard_names'], ['publication_guard', 'statement_mix', 'schema_cookie_boundary']],
    'guard matches' => [static fn (): mixed => $ok()['guard_matches'], [true, true, true]],
    'stale guard names empty' => [static fn (): mixed => $ok()['stale_guard_names'], []],
    'operation final publish' => [static fn (): mixed => end($ok()['operation_names']), 'publish_statement_cache_current_source_next182'],
    'operation admits statement' => [static fn (): mixed => in_array('admit_statement_on_checkpoint_current_source_next182', $ok()['operation_names'], true), true],
    'operation reprepares statement' => [static fn (): mixed => in_array('reprepare_statement_for_checkpoint_current_source_next182', $ok()['operation_names'], true), true],
    'source digest length' => [static fn (): mixed => strlen($ok()['source_digest']), 64],
    'base admitted reader retained' => [static fn (): mixed => $ok()['base_plan']['admitted_reader_names'], ['wp-current-schema']],
    'base reopen readers retained' => [static fn (): mixed => $ok()['base_plan']['reader_reopen_count'], 5],
    'dependency next182 present' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next182', $ok()['dependencies'], true), true],
    'dependency statement rebase present' => [static fn (): mixed => in_array('sqlite-wal-current-source-statement-cache-rebase', $ok()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($ok()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($ok()['non_overlap'], 'does not repeat VFS byte application'), true],
    'stable schema admits touched root' => [static fn (): mixed => $sameCookie()['admitted_statement_names'], ['select-options-root-stable-schema', 'select-usermeta-stable-schema']],
    'stable schema reprepare empty' => [static fn (): mixed => $sameCookie()['reprepare_statement_names'], []],
    'stable schema status blocked without reprepare mix' => [static fn (): mixed => $sameCookie()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next182'],
    'stale publication status' => [static fn (): mixed => $stalePublication()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next182'],
    'stale publication guard names' => [static fn (): mixed => $stalePublication()['stale_guard_names'], ['publication_guard']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next182 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty statements rejected' => static fn () => $plan([]),
    'negative current schema rejected' => static fn () => $plan(null, -1, 42),
    'negative next schema rejected' => static fn () => $plan(null, 41, -1),
    'empty statement name rejected' => static fn () => $plan([['name' => '', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41]]),
    'empty statement source rejected' => static fn () => $plan([['name' => 'bad-source', 'source_id' => '', 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41]]),
    'bad statement epoch rejected' => static fn () => $plan([['name' => 'bad-epoch', 'source_id' => $currentToken['id'], 'epoch' => 0, 'schema_cookie' => 41]]),
    'bad statement schema rejected' => static fn () => $plan([['name' => 'bad-schema', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => -1]]),
    'bad root page rejected' => static fn () => $plan([['name' => 'bad-root', 'source_id' => $currentToken['id'], 'epoch' => $currentToken['epoch'], 'schema_cookie' => 41, 'root_pages' => [0]]]),
    'mutated current wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next182Plan($databasePath, $databaseBytes, $pageSize, 'plugin-import-next182', $hot, $savepointBefore, $currentWal, substr_replace($currentWalBytes, 'x', -1), $nextWal, $nextWalBytes, $cache, [1, 2, 3, 4, 5, 6], $readers, $statements, 41, 42),
    'mutated next wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next182Plan($databasePath, $databaseBytes, $pageSize, 'plugin-import-next182', $hot, $savepointBefore, $currentWal, $currentWalBytes, $nextWal, substr_replace($nextWalBytes, 'x', -1), $cache, [1, 2, 3, 4, 5, 6], $readers, $statements, 41, 42),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next182 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
