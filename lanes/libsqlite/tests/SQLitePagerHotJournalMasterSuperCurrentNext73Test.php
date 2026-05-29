<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/wp-content/database/main.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$orphanPath = '/wp-content/database/orphan.sqlite';
$superPath = '/wp-content/database/main.sqlite-mj73';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$makeJournalBytes = static function (array $pages, int $initialPageCount = 2, int $nonce = 0x73010001) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt) use ($pageSize): string {
    $salt1 = 0x73000000 + $salt;
    $salt2 = 0x73200000 + $salt;
    $headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, $salt, $salt1, $salt2);
    $seed = SQLiteWal::checksumPair($headerPrefix, false);
    $bytes = $headerPrefix . pack('N*', $seed[0], $seed[1]);

    foreach ($frames as $frame) {
        [$pageNumber, $commitPageCount, $image] = $frame;
        $framePrefix = pack('N*', $pageNumber, $commitPageCount, $salt1, $salt2);
        $seed = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $seed[0], $seed[1]);
        $bytes .= $framePrefix . pack('N*', $seed[0], $seed[1]) . $image;
    }

    return $bytes;
};

$mainCleanHeader = $page('main clean schema before attached crash');
$mainCleanOptions = $page('main clean wp_options before attached crash');
$mainDirtyHeader = $page('main dirty schema after attached crash');
$mainDirtyOptions = $page('main dirty wp_options after attached crash');
$siteCleanHeader = $page('site clean schema before attached crash');
$siteCleanOptions = $page('site clean wp_2_options before attached crash');
$siteDirtyHeader = $page('site dirty schema after attached crash');
$siteDirtyOptions = $page('site dirty wp_2_options after attached crash');
$orphanCleanHeader = $page('orphan clean schema before attached crash');
$orphanCleanOptions = $page('orphan clean options before attached crash');
$orphanDirtyHeader = $page('orphan dirty schema after attached crash');
$orphanDirtyOptions = $page('orphan dirty options after attached crash');

$mainJournalBytes = $makeJournalBytes([1 => $mainCleanHeader, 2 => $mainCleanOptions], 2, 0x73010011);
$siteJournalBytes = $makeJournalBytes([1 => $siteCleanHeader, 2 => $siteCleanOptions], 2, 0x73010022);
$orphanJournalBytes = $makeJournalBytes([1 => $orphanCleanHeader, 2 => $orphanCleanOptions], 2, 0x73010033);
$mainJournal = SQLiteRollbackJournal::parse($mainJournalBytes, true);
$siteJournal = SQLiteRollbackJournal::parse($siteJournalBytes, true);
$orphanJournal = SQLiteRollbackJournal::parse($orphanJournalBytes, true);

$mainWalBytes = $makeWalBytes([
    [2, 2, $page('main wal committed option after hot recovery')],
    [1, 0, $page('main wal uncommitted schema tail')],
], 73);
$siteWalBytes = $makeWalBytes([
    [2, 2, $page('site wal committed option after hot recovery')],
    [1, 0, $page('site wal uncommitted schema tail')],
], 74);
$orphanWalBytes = $makeWalBytes([
    [2, 2, $page('orphan wal committed option without super member')],
], 75);

$superBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n" . $mainPath . "-journal\n";
$partialSuperBytes = $mainPath . "-journal\n";

$build = static fn (string $superInput = null, bool $siteReserved = false): array => SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext(
    $superPath,
    $superInput ?? $superBytes,
    [
        [
            'database_path' => $mainPath,
            'database_bytes' => $mainDirtyHeader . $mainDirtyOptions,
            'journal' => $mainJournal,
            'journal_bytes' => $mainJournalBytes,
            'wal_bytes' => $mainWalBytes,
            'page_numbers' => [1, 2],
            'database_page_size' => $pageSize,
        ],
        [
            'database_path' => $sitePath,
            'database_bytes' => $siteDirtyHeader . $siteDirtyOptions,
            'journal' => $siteJournal,
            'journal_bytes' => $siteJournalBytes,
            'wal_bytes' => $siteWalBytes,
            'page_numbers' => [1, 2],
            'database_page_size' => $pageSize,
            'reserved_lock' => $siteReserved,
        ],
        [
            'database_path' => $orphanPath,
            'database_bytes' => $orphanDirtyHeader . $orphanDirtyOptions,
            'journal' => $orphanJournal,
            'journal_bytes' => $orphanJournalBytes,
            'wal_bytes' => $orphanWalBytes,
            'page_numbers' => [1, 2],
            'database_page_size' => $pageSize,
        ],
    ],
);

$cases = [
    'status reports super recovery' => static fn (): mixed => $build()['status'],
    'reason reports member gate' => static fn (): mixed => $build()['reason'],
    'super path preserved' => static fn (): mixed => $build()['super_journal_path'],
    'super exists flag' => static fn (): mixed => $build()['super_journal_exists'],
    'dedupes super members' => static fn (): mixed => count($build()['super_journal_members']),
    'first member is main journal' => static fn (): mixed => $build()['super_journal_members'][0],
    'second member is site journal' => static fn (): mixed => $build()['super_journal_members'][1],
    'database count' => static fn (): mixed => $build()['database_count'],
    'recovers only named databases' => static fn (): mixed => $build()['recovered_database_count'],
    'skips orphan database not named by super' => static fn (): mixed => $build()['skipped_database_count'],
    'main journal deleted' => static fn (): mixed => $build()['journal_actions'][$mainPath . '-journal'],
    'site journal deleted' => static fn (): mixed => $build()['journal_actions'][$sitePath . '-journal'],
    'orphan journal preserved' => static fn (): mixed => $build()['journal_actions'][$orphanPath . '-journal'],
    'super journal deleted after all named clear' => static fn (): mixed => $build()['super_journal_action'],
    'operations include super delete' => static fn (): mixed => $build()['operations'][count($build()['operations']) - 2]['reason'],
    'operations include super directory sync' => static fn (): mixed => $build()['operations'][count($build()['operations']) - 1]['reason'],
    'main current page one is dirty' => static fn (): mixed => str_contains($build()['databases'][$mainPath]['current_reader'][0]['image'], 'main dirty schema'),
    'main next page one is clean journal image' => static fn (): mixed => str_contains($build()['databases'][$mainPath]['next_reader'][0]['image'], 'main clean schema'),
    'main next page two is committed wal image' => static fn (): mixed => str_contains($build()['databases'][$mainPath]['next_reader'][1]['image'], 'main wal committed option'),
    'site current page one is dirty' => static fn (): mixed => str_contains($build()['databases'][$sitePath]['current_reader'][0]['image'], 'site dirty schema'),
    'site next page one is clean journal image' => static fn (): mixed => str_contains($build()['databases'][$sitePath]['next_reader'][0]['image'], 'site clean schema'),
    'site next page two is committed wal image' => static fn (): mixed => str_contains($build()['databases'][$sitePath]['next_reader'][1]['image'], 'site wal committed option'),
    'orphan next page one remains dirty' => static fn (): mixed => str_contains($build()['databases'][$orphanPath]['next_reader'][0]['image'], 'orphan dirty schema'),
    'orphan next page two still sees wal committed page' => static fn (): mixed => str_contains($build()['databases'][$orphanPath]['next_reader'][1]['image'], 'orphan wal committed option'),
    'main current sources' => static fn (): mixed => $build()['current_reader_sources'][$mainPath],
    'main next sources' => static fn (): mixed => $build()['next_reader_sources'][$mainPath],
    'site next sources' => static fn (): mixed => $build()['next_reader_sources'][$sitePath],
    'orphan next sources' => static fn (): mixed => $build()['next_reader_sources'][$orphanPath],
    'main current frame indexes' => static fn (): mixed => $build()['current_reader_frame_indexes'][$mainPath],
    'main next frame indexes' => static fn (): mixed => $build()['next_reader_frame_indexes'][$mainPath],
    'site next frame indexes' => static fn (): mixed => $build()['next_reader_frame_indexes'][$sitePath],
    'orphan next frame indexes' => static fn (): mixed => $build()['next_reader_frame_indexes'][$orphanPath],
    'main hot recovered' => static fn (): mixed => $build()['databases'][$mainPath]['hot_recovered'],
    'site hot recovered' => static fn (): mixed => $build()['databases'][$sitePath]['hot_recovered'],
    'orphan hot skipped' => static fn (): mixed => $build()['databases'][$orphanPath]['hot_recovered'],
    'main discarded uncommitted wal tail' => static fn (): mixed => $build()['databases'][$mainPath]['discarded_valid_tail_frame_count'],
    'site discarded uncommitted wal tail' => static fn (): mixed => $build()['databases'][$sitePath]['discarded_valid_tail_frame_count'],
    'orphan has no wal tail discard' => static fn (): mixed => $build()['databases'][$orphanPath]['discarded_valid_tail_frame_count'],
    'dependencies include current slice' => static fn (): mixed => in_array('sqlite-pager-hot-journal-master-super-current-next73', $build()['dependencies'], true),
    'dependencies include wal visibility' => static fn (): mixed => in_array('sqlite-pager-hot-journal-wal-current-next-visibility', $build()['dependencies'], true),
    'partial super recovers main only' => static fn (): mixed => $build($partialSuperBytes)['recovered_database_count'],
    'partial super preserves super journal' => static fn (): mixed => $build($partialSuperBytes)['super_journal_action'],
    'partial super site remains dirty' => static fn (): mixed => str_contains($build($partialSuperBytes)['databases'][$sitePath]['next_reader'][0]['image'], 'site dirty schema'),
    'site reserved lock skips named recovery' => static fn (): mixed => $build(null, true)['databases'][$sitePath]['hot_recovered'],
    'site reserved lock preserves super journal' => static fn (): mixed => $build(null, true)['super_journal_action'],
    'empty super bytes no recovery' => static fn (): mixed => $build('')['status'],
    'empty super bytes reports no members' => static fn (): mixed => $build('')['super_journal_exists'],
    'empty super bytes skips all databases' => static fn (): mixed => $build('')['skipped_database_count'],
    'empty super path rejected' => static function () use ($superBytes): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext('', $superBytes, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty database list rejected' => static function () use ($superPath, $superBytes): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext($superPath, $superBytes, []);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'database path required' => static function () use ($superPath, $superBytes, $mainJournal, $mainJournalBytes, $mainWalBytes, $mainDirtyHeader, $mainDirtyOptions, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext($superPath, $superBytes, [[
                'database_path' => '',
                'database_bytes' => $mainDirtyHeader . $mainDirtyOptions,
                'journal' => $mainJournal,
                'journal_bytes' => $mainJournalBytes,
                'wal_bytes' => $mainWalBytes,
                'page_numbers' => [1],
                'database_page_size' => $pageSize,
            ]]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'database journal required' => static function () use ($superPath, $superBytes, $mainPath, $mainJournalBytes, $mainWalBytes, $mainDirtyHeader, $mainDirtyOptions, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext($superPath, $superBytes, [[
                'database_path' => $mainPath,
                'database_bytes' => $mainDirtyHeader . $mainDirtyOptions,
                'journal_bytes' => $mainJournalBytes,
                'wal_bytes' => $mainWalBytes,
                'page_numbers' => [1],
                'database_page_size' => $pageSize,
            ]]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'database pages required' => static function () use ($superPath, $superBytes, $mainPath, $mainJournal, $mainJournalBytes, $mainWalBytes, $mainDirtyHeader, $mainDirtyOptions, $pageSize): mixed {
        try {
            SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext($superPath, $superBytes, [[
                'database_path' => $mainPath,
                'database_bytes' => $mainDirtyHeader . $mainDirtyOptions,
                'journal' => $mainJournal,
                'journal_bytes' => $mainJournalBytes,
                'wal_bytes' => $mainWalBytes,
                'page_numbers' => [],
                'database_page_size' => $pageSize,
            ]]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'status reports super recovery' => 'super_journal_hot_recovery_current_next',
    'reason reports member gate' => 'master_super_journal_members_gate_hot_journal_recovery',
    'super path preserved' => $superPath,
    'super exists flag' => true,
    'dedupes super members' => 2,
    'first member is main journal' => $mainPath . '-journal',
    'second member is site journal' => $sitePath . '-journal',
    'database count' => 3,
    'recovers only named databases' => 2,
    'skips orphan database not named by super' => 1,
    'main journal deleted' => 'delete_journal_after_recovery',
    'site journal deleted' => 'delete_journal_after_recovery',
    'orphan journal preserved' => 'preserve_journal',
    'super journal deleted after all named clear' => 'delete_super_journal_after_named_hot_journals',
    'operations include super delete' => 'delete_super_journal_after_named_hot_journals',
    'operations include super directory sync' => 'persist_super_journal_recovery_deletion',
    'main current page one is dirty' => true,
    'main next page one is clean journal image' => true,
    'main next page two is committed wal image' => true,
    'site current page one is dirty' => true,
    'site next page one is clean journal image' => true,
    'site next page two is committed wal image' => true,
    'orphan next page one remains dirty' => true,
    'orphan next page two still sees wal committed page' => true,
    'main current sources' => ['database', 'wal'],
    'main next sources' => ['database', 'wal'],
    'site next sources' => ['database', 'wal'],
    'orphan next sources' => ['database', 'wal'],
    'main current frame indexes' => [null, 1],
    'main next frame indexes' => [null, 1],
    'site next frame indexes' => [null, 1],
    'orphan next frame indexes' => [null, 1],
    'main hot recovered' => true,
    'site hot recovered' => true,
    'orphan hot skipped' => false,
    'main discarded uncommitted wal tail' => 1,
    'site discarded uncommitted wal tail' => 1,
    'orphan has no wal tail discard' => 0,
    'dependencies include current slice' => true,
    'dependencies include wal visibility' => true,
    'partial super recovers main only' => 1,
    'partial super preserves super journal' => 'delete_super_journal_after_named_hot_journals',
    'partial super site remains dirty' => true,
    'site reserved lock skips named recovery' => false,
    'site reserved lock preserves super journal' => 'preserve_super_journal_until_named_journals_clear',
    'empty super bytes no recovery' => 'super_journal_no_hot_recovery_current_next',
    'empty super bytes reports no members' => false,
    'empty super bytes skips all databases' => 3,
    'empty super path rejected' => 'rejected',
    'empty database list rejected' => 'rejected',
    'database path required' => 'rejected',
    'database journal required' => 'rejected',
    'database pages required' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['pager hot journal master super current next73 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
