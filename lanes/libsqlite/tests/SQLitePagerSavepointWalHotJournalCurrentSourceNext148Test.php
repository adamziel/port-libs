<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next148.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirty = [
    1 => $page('next148 dirty sqlite header after crashed import'),
    2 => $page('next148 dirty wp_options root after crashed import'),
    3 => $page('next148 dirty active_plugins after crashed import'),
    4 => $page('next148 dirty autoload index after crashed import'),
    5 => $page('next148 dirty transient rows after crashed import'),
    6 => $page('next148 dirty rewrite rules after crashed import'),
];
$clean = [
    1 => $page('next148 clean sqlite header before crashed import'),
    2 => $page('next148 clean wp_options root before crashed import'),
    3 => $page('next148 clean active_plugins before crashed import'),
    4 => $page('next148 clean autoload index before crashed import'),
    5 => $page('next148 clean transient rows before crashed import'),
    6 => $page('next148 clean rewrite rules before crashed import'),
];
$databaseBytes = implode('', $dirty);

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
    [1, 0, 'next148 current wal schema draft'],
    [2, 6, 'next148 current wal wp_options commit'],
    [3, 0, 'next148 current wal active_plugins draft'],
    [4, 6, 'next148 current wal autoload commit'],
    [5, 0, 'next148 current wal transient draft'],
], 148, 0x14800101, 0x14800102);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'next148 next wal wp_options retry draft'],
    [5, 0, 'next148 next wal transient retry draft'],
    [6, 6, 'next148 next wal rewrite retry commit'],
], 149, 0x14900101, 0x14900102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$plan = static fn (
    int $readerEndFrame = 5,
    array $pages = [1, 2, 3, 4, 5, 6],
    ?SQLiteWal $overrideNextWal = null,
    ?string $overrideNextWalBytes = null,
    bool $reservedLock = false
): array => SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next148',
    [2 => $clean[2], 4 => $clean[4], 6 => $clean[6]],
    [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4], 5 => $dirty[5], 6 => $dirty[6]],
    [
        2 => $page('next148 current savepoint wp_options draft'),
        4 => $page('next148 current savepoint autoload draft'),
        6 => $page('next148 current savepoint rewrite draft'),
    ],
    [
        3 => $page('next148 next savepoint active_plugins retry'),
        5 => $page('next148 next savepoint transient retry'),
    ],
    $currentWal,
    $currentWalBytes,
    $overrideNextWal ?? $nextWal,
    $overrideNextWalBytes ?? $nextWalBytes,
    $pages,
    $readerEndFrame,
    148,
    $reservedLock,
    true,
    true,
);

$restart = static fn (): array => $plan();
$partial = static fn (): array => $plan(3, [2, 3, 6]);
$blocked = static fn (): array => $plan(5, [2, 6], null, null, true);

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'pager-savepoint-wal-hot-journal-current-source-next148'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_recovered_before_savepoint_retry_current_wal_reader_pinned_next_wal_separated'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $databasePath . '-journal'],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $databasePath . '-wal'],
    'page size' => [static fn (): mixed => $restart()['page_size'], 512],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-import-next148'],
    'reader end frame' => [static fn (): mixed => $restart()['reader_end_frame'], 5],
    'hot recovered' => [static fn (): mixed => $restart()['hot_recovered'], true],
    'retry matches reader' => [static fn (): mixed => $restart()['retry_matches_current_reader'], true],
    'next source separated' => [static fn (): mixed => $restart()['next_source_separated'], true],
    'current checkpoint' => [static fn (): mixed => $restart()['current_wal_source']['checkpoint_sequence'], 148],
    'next checkpoint' => [static fn (): mixed => $restart()['next_wal_source']['checkpoint_sequence'], 149],
    'current salt one' => [static fn (): mixed => $restart()['current_wal_source']['salt_1'], 0x14800101],
    'next salt one' => [static fn (): mixed => $restart()['next_wal_source']['salt_1'], 0x14900101],
    'current frame count' => [static fn (): mixed => $restart()['current_wal_source']['frame_count'], 5],
    'next frame count' => [static fn (): mixed => $restart()['next_wal_source']['frame_count'], 3],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_source']['sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($restart()['next_wal_source']['sha256']), 64],
    'wal sources differ' => [static fn (): mixed => $restart()['current_wal_source']['sha256'] !== $restart()['next_wal_source']['sha256'], true],
    'current sources' => [static fn (): mixed => $restart()['current_sources'], ['wal', 'wal', 'wal', 'wal', 'database', 'database']],
    'retry sources' => [static fn (): mixed => $restart()['retry_sources'], ['wal', 'wal', 'wal', 'wal', 'database', 'database']],
    'next sources' => [static fn (): mixed => $restart()['next_sources'], ['database', 'wal', 'database', 'database', 'wal', 'wal']],
    'current frames' => [static fn (): mixed => $restart()['current_frame_indexes'], [1, 2, 3, 4, null, null]],
    'retry frames' => [static fn (): mixed => $restart()['retry_frame_indexes'], [1, 2, 3, 4, null, null]],
    'next frames' => [static fn (): mixed => $restart()['next_frame_indexes'], [null, 1, null, null, 2, 3]],
    'current labels' => [static fn (): mixed => $restart()['current_labels'], [
        'next148 current wal schema draft',
        'next148 current wal wp_options commit',
        'next148 current wal active_plugins draft',
        'next148 current wal autoload commit',
        'next148 dirty transient rows after crashed import',
        'next148 clean rewrite rules before crashed import',
    ]],
    'retry labels match current' => [static fn (): mixed => $restart()['retry_labels'], $restart()['current_labels']],
    'next labels' => [static fn (): mixed => $restart()['next_labels'], [
        'next148 dirty sqlite header after crashed import',
        'next148 next wal wp_options retry draft',
        'next148 dirty active_plugins after crashed import',
        'next148 clean autoload index before crashed import',
        'next148 next wal transient retry draft',
        'next148 next wal rewrite retry commit',
    ]],
    'separated pages' => [static fn (): mixed => $restart()['next_separated_page_numbers'], [1, 2, 3, 4, 5, 6]],
    'separated page count' => [static fn (): mixed => $restart()['next_separated_page_count'], 6],
    'source transitions' => [static fn (): mixed => $restart()['source_transitions'], [
        'wal>savepoint-retry>wal>next-wal>database',
        'wal>savepoint-retry>wal>next-wal>wal',
        'wal>savepoint-retry>wal>next-wal>database',
        'wal>savepoint-retry>wal>next-wal>database',
        'database>savepoint-retry>database>next-wal>wal',
        'database>savepoint-retry>database>next-wal>wal',
    ]],
    'source digest length' => [static fn (): mixed => strlen($restart()['source_digest']), 64],
    'base status' => [static fn (): mixed => $restart()['base_status'], 'hot_journal_recovered_savepoint_current_source_next'],
    'base operation count' => [static fn (): mixed => count($restart()['base_operations']), 15],
    'base writes current page' => [static fn (): mixed => in_array('write_current_savepoint_page', $restart()['base_operations'], true), true],
    'base captures next page' => [static fn (): mixed => in_array('capture_next_savepoint_before_image', $restart()['base_operations'], true), true],
    'base payload hot journal' => [static fn (): mixed => in_array($databasePath . '#hot-journal', $restart()['base_payload_keys'], true), true],
    'base payload rollback' => [static fn (): mixed => in_array($databasePath . '#savepoint-rollback', $restart()['base_payload_keys'], true), true],
    'row count' => [static fn (): mixed => count($restart()['rows']), 6],
    'row pages' => [static fn (): mixed => array_column($restart()['rows'], 'page_number'), [1, 2, 3, 4, 5, 6]],
    'row one hot label' => [static fn (): mixed => $restart()['rows'][0]['hot_database_label'], 'next148 dirty sqlite header after crashed import'],
    'row two rolled back label' => [static fn (): mixed => $restart()['rows'][1]['rolled_back_label'], 'next148 clean wp_options root before crashed import'],
    'row three current label' => [static fn (): mixed => $restart()['rows'][2]['current_label'], 'next148 current wal active_plugins draft'],
    'row four retry label' => [static fn (): mixed => $restart()['rows'][3]['retry_label'], 'next148 current wal autoload commit'],
    'row five next source' => [static fn (): mixed => $restart()['rows'][4]['next_source'], 'wal'],
    'row six next label' => [static fn (): mixed => $restart()['rows'][5]['next_label'], 'next148 next wal rewrite retry commit'],
    'partial current sources' => [static fn (): mixed => $partial()['current_sources'], ['wal', 'database', 'database']],
    'partial current frames' => [static fn (): mixed => $partial()['current_frame_indexes'], [2, null, null]],
    'partial next sources' => [static fn (): mixed => $partial()['next_sources'], ['wal', 'database', 'wal']],
    'partial next frames' => [static fn (): mixed => $partial()['next_frame_indexes'], [1, null, 3]],
    'partial retry labels' => [static fn (): mixed => $partial()['retry_labels'], [
        'next148 current wal wp_options commit',
        'next148 dirty active_plugins after crashed import',
        'next148 clean rewrite rules before crashed import',
    ]],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'pager-savepoint-wal-hot-journal-current-source-blocked-next148'],
    'blocked hot recovered false' => [static fn (): mixed => $blocked()['hot_recovered'], false],
    'blocked retry still matches reader' => [static fn (): mixed => $blocked()['retry_matches_current_reader'], true],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-savepoint-wal-hot-journal-current-source-next148', $restart()['dependencies'], true), true],
    'dependency next88' => [static fn (): mixed => in_array('sqlite-pager-savepoint-hot-journal-current-source-next88', $restart()['dependencies'], true), true],
    'dependency wal snapshot' => [static fn (): mixed => in_array('sqlite-wal-reader-snapshot', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'next143 WAL reader restart'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint wal hot journal current source next148 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current wal bytes rejected' => static fn () => SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]], $currentWal, '', $nextWal, $nextWalBytes, [1], 1),
    'empty next wal bytes rejected' => static fn () => SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]], $currentWal, $currentWalBytes, $nextWal, '', [1], 1),
    'empty reader pages rejected' => static fn () => $plan(1, []),
    'negative reader frame rejected' => static fn () => $plan(-1, [1]),
    'past reader frame rejected' => static fn () => $plan(6, [1]),
    'zero reader page rejected' => static fn () => $plan(1, [0]),
    'string reader page rejected' => static fn () => $plan(1, ['1']),
    'stale checkpoint rejected' => static function () use ($makeWalBytes, $pageSize, $plan): array {
        $bytes = $makeWalBytes([[2, 6, 'next148 stale wal']], 148, 0x14900101, 0x14900102);
        return $plan(1, [1], SQLiteWal::parse($bytes, $pageSize, true), $bytes);
    },
    'same salt rejected' => static function () use ($makeWalBytes, $pageSize, $plan): array {
        $bytes = $makeWalBytes([[2, 6, 'next148 same salt wal']], 149, 0x14800101, 0x14800102);
        return $plan(1, [1], SQLiteWal::parse($bytes, $pageSize, true), $bytes);
    },
    'mismatched current bytes rejected' => static fn () => SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]], $currentWal, $currentWalBytes . 'x', $nextWal, $nextWalBytes, [1], 1),
    'mismatched next bytes rejected' => static fn () => SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]], $currentWal, $currentWalBytes, $nextWal, $nextWalBytes . 'x', [1], 1),
    'base empty path rejected' => static fn () => SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan('', $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]], $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, [1], 1),
    'base stale source rejected' => static fn () => SQLitePagerSavepointWalHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $clean[1]], [1 => $clean[1]], [1 => $dirty[1]], $currentWal, $currentWalBytes, $nextWal, $nextWalBytes, [1], 1),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint wal hot journal current source next148 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
