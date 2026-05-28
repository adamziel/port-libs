<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalSuperCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/www/wp-content/database/main.sqlite';
$metaPath = '/srv/www/wp-content/database/site-meta.sqlite';
$orphanPath = '/srv/www/wp-content/database/orphan.sqlite';
$superPath = '/srv/www/wp-content/database/main.sqlite-mj70';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$mainClean1 = $page('clean main schema before interrupted attached import');
$mainClean2 = $page('clean wp_options before interrupted attached import');
$mainClean3 = $page('clean main autoload index before interrupted attached import');
$mainDirty1 = $page('dirty main schema after interrupted attached import');
$mainDirty2 = $page('dirty wp_options after interrupted attached import');
$mainDirty3 = $page('dirty main autoload index after interrupted attached import');
$mainDirty4 = $page('dirty main overflow page beyond initial count');
$metaClean1 = $page('clean site-meta schema before interrupted attached import');
$metaClean2 = $page('clean wp_sitemeta before interrupted attached import');
$metaDirty1 = $page('dirty site-meta schema after interrupted attached import');
$metaDirty2 = $page('dirty wp_sitemeta after interrupted attached import');
$orphanClean1 = $page('clean orphan schema before interrupted import');
$orphanDirty1 = $page('dirty orphan schema after interrupted import');

$makeJournalBytes = static function (array $pages, int $initialPageCount) use ($sectorSize, $pageSize): string {
    $nonce = 0x706a0070 + $initialPageCount;
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$mainDatabase = $mainDirty1 . $mainDirty2 . $mainDirty3 . $mainDirty4;
$metaDatabase = $metaDirty1 . $metaDirty2;
$orphanDatabase = $orphanDirty1;
$mainJournalBytes = $makeJournalBytes([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3);
$metaJournalBytes = $makeJournalBytes([1 => $metaClean1, 2 => $metaClean2], 2);
$orphanJournalBytes = $makeJournalBytes([1 => $orphanClean1], 1);
$superJournalBytes = $mainPath . "-journal\n" . $metaPath . "-journal\n";
$reversedSuperJournalBytes = "\n" . $metaPath . "-journal\r\n" . $mainPath . "-journal\n" . $mainPath . "-journal\n";

$databases = [
    [
        'database_path' => $mainPath,
        'database_bytes' => $mainDatabase,
        'journal_bytes' => $mainJournalBytes,
    ],
    [
        'database_path' => $metaPath,
        'database_bytes' => $metaDatabase,
        'journal_bytes' => $metaJournalBytes,
    ],
];
$partialDatabases = [
    $databases[0],
    [
        'database_path' => $orphanPath,
        'database_bytes' => $orphanDatabase,
        'journal_bytes' => $orphanJournalBytes,
    ],
];

$plan = static fn (?string $superBytes = null, array $input = null): array => SQLitePagerHotJournalSuperCurrentNextPlan::currentNext(
    $superPath,
    $superBytes,
    $input ?? $databases,
    $pageSize
);
$complete = static fn (): array => $plan($superJournalBytes);
$missing = static fn (): array => $plan(null);
$partial = static fn (): array => $plan($superJournalBytes, $partialDatabases);
$reversed = static fn (): array => $plan($reversedSuperJournalBytes);

$cases = [
    'complete status' => static fn (): mixed => $complete()['status'],
    'complete reason' => static fn (): mixed => $complete()['reason'],
    'complete super path' => static fn (): mixed => $complete()['super_journal_path'],
    'complete super exists' => static fn (): mixed => $complete()['super_journal_exists'],
    'complete database count' => static fn (): mixed => $complete()['database_count'],
    'complete recovered count' => static fn (): mixed => $complete()['recovered_count'],
    'complete blocked count' => static fn (): mixed => $complete()['blocked_count'],
    'complete operations count' => static fn (): mixed => count($complete()['operations']),
    'complete payload count' => static fn (): mixed => count($complete()['payloads']),
    'complete first operation writes main' => static fn (): mixed => $complete()['operations'][0]['path'],
    'complete first operation reason' => static fn (): mixed => $complete()['operations'][0]['reason'],
    'complete main truncate reason' => static fn (): mixed => $complete()['operations'][1]['reason'],
    'complete main sync reason' => static fn (): mixed => $complete()['operations'][2]['reason'],
    'complete main journal delete reason' => static fn (): mixed => $complete()['operations'][3]['reason'],
    'complete meta write path' => static fn (): mixed => $complete()['operations'][4]['path'],
    'complete super delete reason' => static fn (): mixed => $complete()['operations'][8]['reason'],
    'complete directory sync reason' => static fn (): mixed => $complete()['operations'][9]['reason'],
    'complete main journal action' => static fn (): mixed => $complete()['journal_actions'][$mainPath . '-journal'],
    'complete meta journal action' => static fn (): mixed => $complete()['journal_actions'][$metaPath . '-journal'],
    'complete main listed' => static fn (): mixed => $complete()['hot_journals'][$mainPath . '-journal']['listed_in_super_journal'],
    'complete meta listed' => static fn (): mixed => $complete()['hot_journals'][$metaPath . '-journal']['listed_in_super_journal'],
    'complete main hot recovered' => static fn (): mixed => $complete()['hot_journals'][$mainPath . '-journal']['hot'],
    'complete meta hot recovered' => static fn (): mixed => $complete()['hot_journals'][$metaPath . '-journal']['hot'],
    'complete current main page one dirty' => static fn (): mixed => str_starts_with($complete()['current_page_summaries'][$mainPath][0]['prefix'], 'dirty main schema'),
    'complete next main page one clean' => static fn (): mixed => str_starts_with($complete()['next_page_summaries'][$mainPath][0]['prefix'], 'clean main schema'),
    'complete next main page two clean' => static fn (): mixed => str_starts_with($complete()['next_page_summaries'][$mainPath][1]['prefix'], 'clean wp_options'),
    'complete next main page three clean' => static fn (): mixed => str_starts_with($complete()['next_page_summaries'][$mainPath][2]['prefix'], 'clean main autoload'),
    'complete next main truncates page four' => static fn (): mixed => count($complete()['next_page_summaries'][$mainPath]),
    'complete current main had page four' => static fn (): mixed => count($complete()['current_page_summaries'][$mainPath]),
    'complete next meta page two clean' => static fn (): mixed => str_starts_with($complete()['next_page_summaries'][$metaPath][1]['prefix'], 'clean wp_sitemeta'),
    'complete payload main bytes' => static fn (): mixed => strlen($complete()['payloads'][$mainPath . '#hot-super-journal']),
    'complete payload meta bytes' => static fn (): mixed => strlen($complete()['payloads'][$metaPath . '#hot-super-journal']),
    'complete dependencies include pager surface' => static fn (): mixed => in_array('sqlite-pager-hot-journal-super-current-next', $complete()['dependencies'], true),
    'complete dependencies include rollback' => static fn (): mixed => in_array('sqlite-rollback-journal-recovery', $complete()['dependencies'], true),
    'complete dependencies include super recovery' => static fn (): mixed => in_array('sqlite-super-journal-hot-recovery', $complete()['dependencies'], true),
    'missing status' => static fn (): mixed => $missing()['status'],
    'missing recovered count' => static fn (): mixed => $missing()['recovered_count'],
    'missing blocked count' => static fn (): mixed => $missing()['blocked_count'],
    'missing operations empty' => static fn (): mixed => count($missing()['operations']),
    'missing payloads empty' => static fn (): mixed => count($missing()['payloads']),
    'missing main journal action preserves' => static fn (): mixed => $missing()['journal_actions'][$mainPath . '-journal'],
    'missing main reason' => static fn (): mixed => $missing()['hot_journals'][$mainPath . '-journal']['reason'],
    'missing main super exists false' => static fn (): mixed => $missing()['hot_journals'][$mainPath . '-journal']['super_journal_exists'],
    'missing next main page remains dirty' => static fn (): mixed => str_starts_with($missing()['next_page_summaries'][$mainPath][0]['prefix'], 'dirty main schema'),
    'missing current equals next bytes' => static fn (): mixed => $missing()['current_databases'][$mainPath] === $missing()['next_databases'][$mainPath],
    'partial status' => static fn (): mixed => $partial()['status'],
    'partial recovered count' => static fn (): mixed => $partial()['recovered_count'],
    'partial blocked count' => static fn (): mixed => $partial()['blocked_count'],
    'partial orphan listed false' => static fn (): mixed => $partial()['hot_journals'][$orphanPath . '-journal']['listed_in_super_journal'],
    'partial orphan reason' => static fn (): mixed => $partial()['hot_journals'][$orphanPath . '-journal']['reason'],
    'partial orphan remains dirty' => static fn (): mixed => str_starts_with($partial()['next_page_summaries'][$orphanPath][0]['prefix'], 'dirty orphan schema'),
    'partial main recovers clean' => static fn (): mixed => str_starts_with($partial()['next_page_summaries'][$mainPath][0]['prefix'], 'clean main schema'),
    'reversed duplicate super list still complete' => static fn (): mixed => $reversed()['status'],
    'reversed main listed' => static fn (): mixed => $reversed()['hot_journals'][$mainPath . '-journal']['listed_in_super_journal'],
    'reversed meta listed' => static fn (): mixed => $reversed()['hot_journals'][$metaPath . '-journal']['listed_in_super_journal'],
    'reserved lock blocks one database' => static function () use ($databases, $superPath, $superJournalBytes, $pageSize, $mainPath): mixed {
        $copy = $databases;
        $copy[0]['reserved_lock'] = true;
        return SQLitePagerHotJournalSuperCurrentNextPlan::currentNext($superPath, $superJournalBytes, $copy, $pageSize)['hot_journals'][$mainPath . '-journal']['reason'];
    },
    'writer applies complete recovery' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize)['status'];
    },
    'writer operation count' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize)['applied'];
    },
    'writer bytes written' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize)['bytes_written'];
    },
    'writer bytes truncated' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize)['bytes_truncated'];
    },
    'writer files deleted' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize)['files_deleted'];
    },
    'writer durable syncs' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize)['durable_syncs'];
    },
    'writer directory syncs' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize)['directory_syncs'];
    },
    'writer deletes local super journal' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        @mkdir(dirname($root . $superPath), 0777, true);
        file_put_contents($root . $superPath, $superJournalBytes);
        (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize);
        return is_file($root . $superPath);
    },
    'writer main file recovered clean' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize, $mainPath): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize);
        return str_starts_with((string) file_get_contents($root . $mainPath), 'clean main schema');
    },
    'writer main file truncated' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize, $mainPath): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, $superJournalBytes, $databases, $pageSize);
        return filesize($root . $mainPath);
    },
    'writer missing super skips' => static function () use ($superPath, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, null, $databases, $pageSize)['status'];
    },
    'writer missing super applies zero' => static function () use ($superPath, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-hot-super-70-' . bin2hex(random_bytes(4));
        return (new SQLiteVfsFileWriter($root))->applyHotJournalSuperRecovery($superPath, null, $databases, $pageSize)['applied'];
    },
    'empty super path rejected' => static function () use ($superJournalBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerHotJournalSuperCurrentNextPlan::currentNext('', $superJournalBytes, $databases, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty database list rejected' => static function () use ($superPath, $superJournalBytes, $pageSize): mixed {
        try {
            SQLitePagerHotJournalSuperCurrentNextPlan::currentNext($superPath, $superJournalBytes, [], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'bad page size rejected' => static function () use ($superPath, $superJournalBytes, $databases): mixed {
        try {
            SQLitePagerHotJournalSuperCurrentNextPlan::currentNext($superPath, $superJournalBytes, $databases, 500);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'duplicate database rejected' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerHotJournalSuperCurrentNextPlan::currentNext($superPath, $superJournalBytes, [$databases[0], $databases[0]], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'unaligned database rejected' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        $copy = $databases;
        $copy[0]['database_bytes'] = 'not aligned';
        try {
            SQLitePagerHotJournalSuperCurrentNextPlan::currentNext($superPath, $superJournalBytes, $copy, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only rejected' => static function () use ($superPath, $superJournalBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerHotJournalSuperCurrentNextPlan::currentNext($superPath, $superJournalBytes, $databases, $pageSize, true);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'complete status' => 'super_journal_hot_recovery_complete',
    'complete reason' => 'current_dirty_attached_databases_next_super_journal_hot_recovery',
    'complete super path' => $superPath,
    'complete super exists' => true,
    'complete database count' => 2,
    'complete recovered count' => 2,
    'complete blocked count' => 0,
    'complete operations count' => 10,
    'complete payload count' => 2,
    'complete first operation writes main' => $mainPath,
    'complete first operation reason' => 'restore_attached_database_from_hot_journal_super_journal',
    'complete main truncate reason' => 'trim_attached_database_after_hot_journal_super_journal',
    'complete main sync reason' => 'sync_attached_database_after_hot_journal_super_journal',
    'complete main journal delete reason' => 'delete_attached_hot_journal_after_super_recovery',
    'complete meta write path' => $metaPath,
    'complete super delete reason' => 'delete_super_journal_after_attached_hot_recovery',
    'complete directory sync reason' => 'persist_super_journal_hot_recovery_sidecars',
    'complete main journal action' => 'delete_journal_after_recovery',
    'complete meta journal action' => 'delete_journal_after_recovery',
    'complete main listed' => true,
    'complete meta listed' => true,
    'complete main hot recovered' => true,
    'complete meta hot recovered' => true,
    'complete current main page one dirty' => true,
    'complete next main page one clean' => true,
    'complete next main page two clean' => true,
    'complete next main page three clean' => true,
    'complete next main truncates page four' => 3,
    'complete current main had page four' => 4,
    'complete next meta page two clean' => true,
    'complete payload main bytes' => $pageSize * 3,
    'complete payload meta bytes' => $pageSize * 2,
    'complete dependencies include pager surface' => true,
    'complete dependencies include rollback' => true,
    'complete dependencies include super recovery' => true,
    'missing status' => 'super_journal_missing_preserved_current',
    'missing recovered count' => 0,
    'missing blocked count' => 2,
    'missing operations empty' => 0,
    'missing payloads empty' => 0,
    'missing main journal action preserves' => 'preserve_journal',
    'missing main reason' => 'missing_super_journal',
    'missing main super exists false' => false,
    'missing next main page remains dirty' => true,
    'missing current equals next bytes' => true,
    'partial status' => 'super_journal_hot_recovery_partial',
    'partial recovered count' => 1,
    'partial blocked count' => 1,
    'partial orphan listed false' => false,
    'partial orphan reason' => 'missing_super_journal',
    'partial orphan remains dirty' => true,
    'partial main recovers clean' => true,
    'reversed duplicate super list still complete' => 'super_journal_hot_recovery_complete',
    'reversed main listed' => true,
    'reversed meta listed' => true,
    'reserved lock blocks one database' => 'database_has_reserved_lock',
    'writer applies complete recovery' => 'applied',
    'writer operation count' => 10,
    'writer bytes written' => $pageSize * 5,
    'writer bytes truncated' => $pageSize * 5,
    'writer files deleted' => 3,
    'writer durable syncs' => 2,
    'writer directory syncs' => 1,
    'writer deletes local super journal' => false,
    'writer main file recovered clean' => true,
    'writer main file truncated' => $pageSize * 3,
    'writer missing super skips' => 'skipped',
    'writer missing super applies zero' => 0,
    'empty super path rejected' => 'rejected',
    'empty database list rejected' => 'rejected',
    'bad page size rejected' => 'rejected',
    'duplicate database rejected' => 'rejected',
    'unaligned database rejected' => 'rejected',
    'read only rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['pager hot journal super current next70 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
