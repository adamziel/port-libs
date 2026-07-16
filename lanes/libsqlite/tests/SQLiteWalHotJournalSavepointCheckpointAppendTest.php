<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteSavepointStack;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next153.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$cleanPages = [
    1 => $page('next153 recovered sqlite header'),
    2 => $page('next153 recovered wp_options root'),
    3 => $page('next153 recovered active_plugins row'),
    4 => $page('next153 recovered autoload index'),
    5 => $page('next153 recovered transient row'),
    6 => $page('next153 recovered rewrite rules'),
];
$dirtyDatabase = $page('next153 dirty sqlite header')
    . $page('next153 dirty wp_options root')
    . $page('next153 dirty active_plugins row')
    . $page('next153 dirty autoload index')
    . $page('next153 dirty transient row')
    . $page('next153 dirty rewrite rules');

$makeJournalBytes = static function (array $pages, int $nonce = 0x15315301) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, count($pages), $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $checkpoint = 153, int $salt1 = 0x15315301, int $salt2 = 0x15315302) use ($pageSize, $page): string {
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

$walFrames = [
    [2, 0, 'next153 retained wp_options draft'],
    [3, 6, 'next153 retained active_plugins commit'],
    [4, 0, 'next153 savepoint autoload draft'],
    [5, 6, 'next153 savepoint transient commit'],
    [2, 6, 'next153 savepoint stale wp_options commit'],
    [6, 6, 'next153 savepoint rewrite commit'],
];
$journalBytes = $makeJournalBytes($cleanPages);
$walBytes = $makeWalBytes($walFrames);
$wal = SQLiteWal::parse($walBytes, $pageSize, true);

$stack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next153');
    $stack->recordWalFrameWrite(1, 2);
    $stack->recordWalFrameWrite(2, 3, true);
    $stack->savepoint('plugin-settings-next153');
    $stack->recordWalFrameWrite(3, 4);
    $stack->recordWalFrameWrite(4, 5, true);
    $stack->recordWalFrameWrite(5, 2, true);
    $stack->recordWalFrameWrite(6, 6, true);

    return $stack;
};

$nextTransactions = static fn (): array => [[
    'pages' => [
        2 => $page('next153 next wp_options retry'),
        5 => $page('next153 next transient retry'),
        6 => $page('next153 next rewrite retry'),
    ],
    'database_page_count' => 6,
]];

$plan = static fn (
    string $mode = 'restart',
    int $readerEndFrame = 6,
    array $pages = [1, 2, 3, 4, 5, 6],
    bool $reservedLock = false,
    ?array $transactions = null
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan(
    $databasePath,
    $dirtyDatabase,
    $journalBytes,
    $stack(),
    'plugin-settings-next153',
    $wal,
    $walBytes,
    $pages,
    $transactions ?? $nextTransactions(),
    $readerEndFrame,
    $mode,
    $reservedLock
);

$restart = static fn (): array => $plan();
$truncate = static fn (): array => $plan('truncate');
$partial = static fn (): array => $plan('restart', 2, [2, 3, 6]);
$blocked = static fn (): array => $plan('restart', 6, [1, 2], true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next153'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovered_before_savepoint_rollback_current_wal_prefix_pins_checkpoint_until_reader_release'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-settings-next153'],
    'mode restart' => [static fn (): mixed => $restart()['mode'], 'restart'],
    'mode truncate' => [static fn (): mixed => $truncate()['mode'], 'truncate'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'reader frame' => [static fn (): mixed => $restart()['reader_end_frame'], 6],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'retained frames' => [static fn (): mixed => $restart()['retained_frame_count'], 2],
    'discarded frames' => [static fn (): mixed => $restart()['discarded_frame_count'], 4],
    'current checkpoint busy' => [static fn (): mixed => $restart()['current_checkpoint_busy'], true],
    'current checkpoint reason' => [static fn (): mixed => $restart()['current_checkpoint_reason'], 'reader_blocks_wal_reset'],
    'released checkpoint not busy' => [static fn (): mixed => $restart()['released_checkpoint_busy'], false],
    'released checkpoint reason' => [static fn (): mixed => $restart()['released_checkpoint_reason'], 'restart_checkpoint_can_reset_wal'],
    'released action restart' => [static fn (): mixed => $restart()['released_wal_action'], 'restart_wal'],
    'released action truncate' => [static fn (): mixed => $truncate()['released_wal_action'], 'truncate_wal'],
    'current wal length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], 1104],
    'next append frame count' => [static fn (): mixed => $restart()['next_append_frame_count'], 3],
    'next append last commit' => [static fn (): mixed => $restart()['next_append_last_commit_frame'], 3],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['database', 'wal', 'wal', 'database', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'wal', 'database', 'database', 'wal', 'wal']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [null, 1, 2, null, null, null]],
    'next frames' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, 1, null, null, 2, 3]],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], [
        'hot-journal-database>database>database',
        'hot-journal-database>wal>wal',
        'hot-journal-database>wal>database',
        'hot-journal-database>database>database',
        'hot-journal-database>database>wal',
        'hot-journal-database>database>wal',
    ]],
    'reader pins checkpoint' => [static fn (): mixed => $restart()['current_reader_pins_checkpoint'], true],
    'release unblocks checkpoint' => [static fn (): mixed => $restart()['reader_release_unblocks_checkpoint'], true],
    'next reader new generation' => [static fn (): mixed => $restart()['next_reader_uses_new_generation'], true],
    'row count' => [static fn (): mixed => count($restart()['rows']), 6],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6]],
    'row one hot label' => [static fn (): mixed => $restart()['rows'][0]['hot_label'], 'next153 recovered sqlite header'],
    'row two current label' => [static fn (): mixed => $restart()['rows'][1]['current_label'], 'next153 retained wp_options draft'],
    'row three current label' => [static fn (): mixed => $restart()['rows'][2]['current_label'], 'next153 retained active_plugins commit'],
    'row five next label' => [static fn (): mixed => $restart()['rows'][4]['next_label'], 'next153 next transient retry'],
    'row six next source' => [static fn (): mixed => $restart()['rows'][5]['next_source'], 'wal'],
    'hot journal nested recovered' => [static fn (): mixed => $restart()['hot_journal']['recovered'], true],
    'rollback dependency' => [static fn (): mixed => in_array('sqlite-wal-savepoint-checkpoint-current', $restart()['dependencies'], true), true],
    'next153 dependency' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next153', $restart()['dependencies'], true), true],
    'append dependency' => [static fn (): mixed => in_array('sqlite-wal-append-transaction', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'does not repeat accepted hot-journal restart'), true],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'partial current sources' => [static fn (): mixed => $partial()['current_sources'], ['wal', 'wal', 'database']],
    'partial next sources' => [static fn (): mixed => $partial()['next_sources'], ['wal', 'database', 'wal']],
    'partial frames' => [static fn (): mixed => $partial()['current_frame_indexes'], [1, 2, null]],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next153'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next153 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty savepoint rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), '', $wal, $walBytes, [1], $nextTransactions(), 2),
    'empty pages rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [], $nextTransactions(), 2),
    'empty transactions rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [1], [], 2),
    'negative reader rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [1], $nextTransactions(), -1),
    'reader past wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [1], $nextTransactions(), 7),
    'bad mode rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [1], $nextTransactions(), 2, 'passive'),
    'empty path rejected by hot plan' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan('', $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [1], $nextTransactions(), 2),
    'empty database rejected by hot plan' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, '', $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [1], $nextTransactions(), 2),
    'empty journal rejected by hot plan' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, '', $stack(), 'plugin-settings-next153', $wal, $walBytes, [1], $nextTransactions(), 2),
    'zero page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, [0], $nextTransactions(), 2),
    'string page rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan($databasePath, $dirtyDatabase, $journalBytes, $stack(), 'plugin-settings-next153', $wal, $walBytes, ['1'], $nextTransactions(), 2),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next153 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

$tests['wal hot journal savepoint checkpoint current source next153 validates zero page before hot journal bytes'] = static function (TestRunner $t) use ($databasePath, $dirtyDatabase, $stack, $wal, $walBytes, $nextTransactions): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan(
        $databasePath,
        $dirtyDatabase,
        '',
        $stack(),
        'plugin-settings-next153',
        $wal,
        $walBytes,
        [0],
        $nextTransactions(),
        2
    ), 'one-based integers');
};

$tests['wal hot journal savepoint checkpoint current source next153 validates string page before hot journal bytes'] = static function (TestRunner $t) use ($databasePath, $dirtyDatabase, $stack, $wal, $walBytes, $nextTransactions): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::hotJournalSavepointCheckpointAppendPlan(
        $databasePath,
        $dirtyDatabase,
        '',
        $stack(),
        'plugin-settings-next153',
        $wal,
        $walBytes,
        ['1'],
        $nextTransactions(),
        2
    ), 'one-based integers');
};

return $tests;
