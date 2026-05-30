<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$cachePath = '/srv/wp/database/cache.sqlite';
$orphanPath = '/srv/wp/database/orphan.sqlite';
$superPath = '/srv/wp/database/super-journal-current-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$makeJournal = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $image) {
        $bytes .= pack('N', $pageNumber) . $image . pack('N', SQLiteRollbackJournal::pageChecksum($image, $nonce));
    }

    return $bytes;
};
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    rmdir($path);
};
$local = static fn (string $root, string $path): string => rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, '/');

$mainClean1 = $page('current-source clean main schema before plugin activation');
$mainClean2 = $page('current-source clean main active_plugins before plugin activation');
$mainDirty1 = $page('current-source dirty main schema after crashed activation');
$mainDirty2 = $page('current-source dirty main active_plugins after crashed activation');
$mainDirty3 = $page('current-source dirty main overflow tail after crashed activation');
$staleMain1 = $page('current-source stale main schema snapshot that must be ignored');
$staleMain2 = $page('current-source stale active_plugins snapshot that must be ignored');
$siteClean1 = $page('current-source clean site schema before multisite import');
$siteClean2 = $page('current-source clean site upload_path before multisite import');
$siteDirty1 = $page('current-source dirty site schema after multisite import');
$siteDirty2 = $page('current-source dirty site upload_path after multisite import');
$cacheClean1 = $page('current-source clean cache schema before object-cache import');
$cacheDirty1 = $page('current-source dirty cache schema after object-cache import');
$orphanClean1 = $page('current-source clean orphan schema before unattached import');
$orphanDirty1 = $page('current-source dirty orphan schema after unattached import');

$mainDatabase = $mainDirty1 . $mainDirty2 . $mainDirty3;
$siteDatabase = $siteDirty1 . $siteDirty2;
$cacheDatabase = $cacheDirty1;
$orphanDatabase = $orphanDirty1;
$staleMainDatabase = $staleMain1 . $staleMain2;
$mainJournalBytes = $makeJournal([1 => $mainClean1, 2 => $mainClean2], 2, 0x10600001);
$siteJournalBytes = $makeJournal([1 => $siteClean1, 2 => $siteClean2], 2, 0x10600002);
$cacheJournalBytes = $makeJournal([1 => $cacheClean1], 1, 0x10600003);
$orphanJournalBytes = $makeJournal([1 => $orphanClean1], 1, 0x10600004);
$staleJournalBytes = $makeJournal([1 => $staleMain1, 2 => $staleMain2], 2, 0x10600005);
$superBytes = $mainPath . "-journal\n" . $sitePath . "-journal\r\n" . $cachePath . "-journal\n" . $mainPath . "-journal\n";
$partialSuperBytes = $mainPath . "-journal\n" . $cachePath . "-journal\n";

$databases = [
    [
        'database_path' => $mainPath,
        'current_database_bytes' => $mainDatabase,
        'current_journal_bytes' => $mainJournalBytes,
        'stale_database_bytes' => $staleMainDatabase,
        'stale_journal_bytes' => $staleJournalBytes,
    ],
    [
        'database_path' => $sitePath,
        'current_database_bytes' => $siteDatabase,
        'current_journal_bytes' => $siteJournalBytes,
    ],
    [
        'database_path' => $cachePath,
        'current_database_bytes' => $cacheDatabase,
        'current_journal_bytes' => $cacheJournalBytes,
    ],
];
$partialDatabases = [$databases[0], $databases[1], [
    'database_path' => $orphanPath,
    'current_database_bytes' => $orphanDatabase,
    'current_journal_bytes' => $orphanJournalBytes,
]];

$plan = static fn (?string $bytes = null, array $input = null): array => SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext(
    $superPath,
    $bytes,
    $input ?? $databases,
    $pageSize
);
$complete = static fn (): array => $plan($superBytes);
$missing = static fn (): array => $plan(null);
$partial = static fn (): array => $plan($partialSuperBytes, $partialDatabases);
$reserved = static function () use ($databases, $superBytes, $superPath, $pageSize): array {
    $copy = $databases;
    $copy[1]['reserved_lock'] = true;
    return SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($superPath, $superBytes, $copy, $pageSize);
};
$writeCurrentSourceFiles = static function (string $root, ?string $super = null, array $entries = null) use ($local, $superPath, $superBytes, $databases): void {
    @mkdir(dirname($local($root, $superPath)), 0777, true);
    if ($super !== null) {
        file_put_contents($local($root, $superPath), $super);
    }
    foreach ($entries ?? $databases as $entry) {
        $databasePath = (string) $entry['database_path'];
        @mkdir(dirname($local($root, $databasePath)), 0777, true);
        file_put_contents($local($root, $databasePath), (string) $entry['current_database_bytes']);
        file_put_contents($local($root, $databasePath . '-journal'), (string) $entry['current_journal_bytes']);
    }
};
$apply = static function (?string $super = null, array $entries = null) use ($writeCurrentSourceFiles, $superBytes, $databases, $superPath, $pageSize, $removeTree): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-super-hot-106-' . bin2hex(random_bytes(4));
    $writeCurrentSourceFiles($root, $super ?? $superBytes, $entries ?? $databases);
    try {
        return (new SQLiteVfsFileWriter($root))->applySuperJournalHotRollbackFromCurrentSource($superPath, array_map(
            static fn (array $entry): array => array_filter([
                'database_path' => $entry['database_path'],
                'stale_database_bytes' => $entry['stale_database_bytes'] ?? null,
                'stale_journal_bytes' => $entry['stale_journal_bytes'] ?? null,
                'reserved_lock' => $entry['reserved_lock'] ?? null,
            ], static fn (mixed $value): bool => $value !== null),
            $entries ?? $databases
        ), $pageSize);
    } finally {
        $removeTree($root);
    }
};

$cases = [
    'complete status' => static fn (): mixed => $complete()['status'],
    'complete reason' => static fn (): mixed => $complete()['reason'],
    'complete super exists' => static fn (): mixed => $complete()['super_journal_exists'],
    'complete member count dedupes' => static fn (): mixed => count($complete()['super_journal_members']),
    'complete database count' => static fn (): mixed => $complete()['database_count'],
    'complete recovered count' => static fn (): mixed => $complete()['recovered_database_count'],
    'complete blocked count' => static fn (): mixed => $complete()['blocked_database_count'],
    'complete stale candidate count' => static fn (): mixed => $complete()['stale_candidate_count'],
    'complete payload count' => static fn (): mixed => count($complete()['payloads']),
    'complete operations count' => static fn (): mixed => count($complete()['operations']),
    'complete first op reason' => static fn (): mixed => $complete()['operations'][0]['reason'],
    'complete main truncate reason' => static fn (): mixed => $complete()['operations'][1]['reason'],
    'complete main sync reason' => static fn (): mixed => $complete()['operations'][2]['reason'],
    'complete main delete reason' => static fn (): mixed => $complete()['operations'][3]['reason'],
    'complete cache write path' => static fn (): mixed => $complete()['operations'][8]['path'],
    'complete super delete reason' => static fn (): mixed => $complete()['operations'][12]['reason'],
    'complete directory sync reason' => static fn (): mixed => $complete()['operations'][13]['reason'],
    'complete main action' => static fn (): mixed => $complete()['journal_actions'][$mainPath . '-journal'],
    'complete site action' => static fn (): mixed => $complete()['journal_actions'][$sitePath . '-journal'],
    'complete cache action' => static fn (): mixed => $complete()['journal_actions'][$cachePath . '-journal'],
    'complete main listed' => static fn (): mixed => $complete()['hot_journals'][$mainPath . '-journal']['listed_in_super_journal'],
    'complete site listed' => static fn (): mixed => $complete()['hot_journals'][$sitePath . '-journal']['listed_in_super_journal'],
    'complete cache listed' => static fn (): mixed => $complete()['hot_journals'][$cachePath . '-journal']['listed_in_super_journal'],
    'complete stale ignored' => static fn (): mixed => $complete()['hot_journals'][$mainPath . '-journal']['stale_candidate_ignored'],
    'complete site stale false' => static fn (): mixed => $complete()['hot_journals'][$sitePath . '-journal']['stale_candidate_ignored'],
    'complete main current prefix dirty' => static fn (): mixed => str_starts_with($complete()['hot_journals'][$mainPath . '-journal']['current_source_database_prefix'], 'current-source dirty main'),
    'complete main next prefix clean' => static fn (): mixed => str_starts_with($complete()['hot_journals'][$mainPath . '-journal']['next_database_prefix'], 'current-source clean main'),
    'complete next main bytes truncated' => static fn (): mixed => strlen($complete()['next_databases'][$mainPath]),
    'complete next site bytes' => static fn (): mixed => strlen($complete()['next_databases'][$sitePath]),
    'complete next cache bytes' => static fn (): mixed => strlen($complete()['next_databases'][$cachePath]),
    'complete current source main database bytes' => static fn (): mixed => $complete()['current_source_bytes'][$mainPath]['database'],
    'complete current source main journal bytes' => static fn (): mixed => $complete()['current_source_bytes'][$mainPath]['journal'],
    'complete stale bytes not restored' => static fn (): mixed => str_contains($complete()['next_databases'][$mainPath], 'stale'),
    'complete active plugins restored' => static fn (): mixed => str_contains($complete()['next_databases'][$mainPath], 'clean main active_plugins'),
    'complete dependencies include slice' => static fn (): mixed => in_array('sqlite-pager-super-journal-hot-rollback-current-source-next106', $complete()['dependencies'], true),
    'complete dependencies include rollback' => static fn (): mixed => in_array('sqlite-rollback-journal-recovery', $complete()['dependencies'], true),
    'missing status' => static fn (): mixed => $missing()['status'],
    'missing recovered count' => static fn (): mixed => $missing()['recovered_database_count'],
    'missing blocked count' => static fn (): mixed => $missing()['blocked_database_count'],
    'missing operations empty' => static fn (): mixed => count($missing()['operations']),
    'missing main reason' => static fn (): mixed => $missing()['hot_journals'][$mainPath . '-journal']['reason'],
    'missing main action' => static fn (): mixed => $missing()['journal_actions'][$mainPath . '-journal'],
    'missing preserves dirty main' => static fn (): mixed => str_starts_with($missing()['next_databases'][$mainPath], 'current-source dirty main'),
    'partial status' => static fn (): mixed => $partial()['status'],
    'partial recovered count' => static fn (): mixed => $partial()['recovered_database_count'],
    'partial blocked count' => static fn (): mixed => $partial()['blocked_database_count'],
    'partial site listed false' => static fn (): mixed => $partial()['hot_journals'][$sitePath . '-journal']['listed_in_super_journal'],
    'partial orphan listed false' => static fn (): mixed => $partial()['hot_journals'][$orphanPath . '-journal']['listed_in_super_journal'],
    'partial site remains dirty' => static fn (): mixed => str_starts_with($partial()['next_databases'][$sitePath], 'current-source dirty site'),
    'partial super not deleted' => static fn (): mixed => count($partial()['operations']),
    'reserved lock status partial' => static fn (): mixed => $reserved()['status'],
    'reserved lock blocks site' => static fn (): mixed => $reserved()['hot_journals'][$sitePath . '-journal']['reason'],
    'reserved super not deleted' => static fn (): mixed => count($reserved()['operations']),
    'writer applies current source' => static fn (): mixed => $apply()['status'],
    'writer applied operation count' => static fn (): mixed => $apply()['applied'],
    'writer bytes written' => static fn (): mixed => $apply()['bytes_written'],
    'writer bytes truncated' => static fn (): mixed => $apply()['bytes_truncated'],
    'writer files deleted' => static fn (): mixed => $apply()['files_deleted'],
    'writer durable syncs' => static fn (): mixed => $apply()['durable_syncs'],
    'writer directory syncs' => static fn (): mixed => $apply()['directory_syncs'],
    'writer current source super exists' => static fn (): mixed => $apply()['current_source']['super_journal_exists'],
    'writer current source database count' => static fn (): mixed => count($apply()['current_source']['database_paths']),
    'writer recovery stale count' => static fn (): mixed => $apply()['recovery']['stale_candidate_count'],
    'writer missing super skips' => static function () use ($writeCurrentSourceFiles, $superPath, $databases, $pageSize, $removeTree): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-super-hot-106-' . bin2hex(random_bytes(4));
        $writeCurrentSourceFiles($root, null, $databases);
        try {
            return (new SQLiteVfsFileWriter($root))->applySuperJournalHotRollbackFromCurrentSource($superPath, array_map(
                static fn (array $entry): array => ['database_path' => $entry['database_path']],
                $databases
            ), $pageSize)['status'];
        } finally {
            $removeTree($root);
        }
    },
    'writer missing database rejected' => static function () use ($superPath, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-super-hot-106-' . bin2hex(random_bytes(4));
        try {
            (new SQLiteVfsFileWriter($root))->applySuperJournalHotRollbackFromCurrentSource($superPath, [['database_path' => '/missing.sqlite']], $pageSize);
        } catch (RuntimeException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty super path rejected' => static function () use ($superBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext('', $superBytes, $databases, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty database list rejected' => static function () use ($superPath, $superBytes, $pageSize): mixed {
        try {
            SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($superPath, $superBytes, [], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'bad page size rejected' => static function () use ($superPath, $superBytes, $databases): mixed {
        try {
            SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($superPath, $superBytes, $databases, 513);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'duplicate database rejected' => static function () use ($superPath, $superBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($superPath, $superBytes, [$databases[0], $databases[0]], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only rejected' => static function () use ($superPath, $superBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($superPath, $superBytes, $databases, $pageSize, true);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'unaligned current source rejected' => static function () use ($superPath, $superBytes, $databases, $pageSize): mixed {
        $copy = $databases;
        $copy[0]['current_database_bytes'] = 'short';
        try {
            SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($superPath, $superBytes, $copy, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'complete status' => 'super_journal_current_source_hot_rollback_complete',
    'complete reason' => 'current_vfs_source_controls_super_journal_hot_rollback',
    'complete super exists' => true,
    'complete member count dedupes' => 3,
    'complete database count' => 3,
    'complete recovered count' => 3,
    'complete blocked count' => 0,
    'complete stale candidate count' => 1,
    'complete payload count' => 3,
    'complete operations count' => 14,
    'complete first op reason' => 'restore_current_source_database_from_super_hot_journal',
    'complete main truncate reason' => 'trim_current_source_database_after_super_hot_rollback',
    'complete main sync reason' => 'sync_current_source_database_after_super_hot_rollback',
    'complete main delete reason' => 'delete_current_source_hot_journal_after_super_rollback',
    'complete cache write path' => $cachePath,
    'complete super delete reason' => 'delete_super_journal_after_current_source_hot_rollback',
    'complete directory sync reason' => 'persist_super_journal_current_source_hot_rollback',
    'complete main action' => 'delete_journal_after_recovery',
    'complete site action' => 'delete_journal_after_recovery',
    'complete cache action' => 'delete_journal_after_recovery',
    'complete main listed' => true,
    'complete site listed' => true,
    'complete cache listed' => true,
    'complete stale ignored' => true,
    'complete site stale false' => false,
    'complete main current prefix dirty' => true,
    'complete main next prefix clean' => true,
    'complete next main bytes truncated' => $pageSize * 2,
    'complete next site bytes' => $pageSize * 2,
    'complete next cache bytes' => $pageSize,
    'complete current source main database bytes' => $pageSize * 3,
    'complete current source main journal bytes' => strlen($mainJournalBytes),
    'complete stale bytes not restored' => false,
    'complete active plugins restored' => true,
    'complete dependencies include slice' => true,
    'complete dependencies include rollback' => true,
    'missing status' => 'super_journal_missing_preserved_current_source',
    'missing recovered count' => 0,
    'missing blocked count' => 3,
    'missing operations empty' => 0,
    'missing main reason' => 'missing_super_journal',
    'missing main action' => 'preserve_journal',
    'missing preserves dirty main' => true,
    'partial status' => 'super_journal_current_source_hot_rollback_partial',
    'partial recovered count' => 1,
    'partial blocked count' => 2,
    'partial site listed false' => false,
    'partial orphan listed false' => false,
    'partial site remains dirty' => true,
    'partial super not deleted' => 4,
    'reserved lock status partial' => 'super_journal_current_source_hot_rollback_partial',
    'reserved lock blocks site' => 'database_has_reserved_lock',
    'reserved super not deleted' => 8,
    'writer applies current source' => 'applied',
    'writer applied operation count' => 14,
    'writer bytes written' => $pageSize * 5,
    'writer bytes truncated' => $pageSize * 5,
    'writer files deleted' => 4,
    'writer durable syncs' => 3,
    'writer directory syncs' => 1,
    'writer current source super exists' => true,
    'writer current source database count' => 3,
    'writer recovery stale count' => 1,
    'writer missing super skips' => 'skipped',
    'writer missing database rejected' => 'rejected',
    'empty super path rejected' => 'rejected',
    'empty database list rejected' => 'rejected',
    'bad page size rejected' => 'rejected',
    'duplicate database rejected' => 'rejected',
    'read only rejected' => 'rejected',
    'unaligned current source rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['pager super journal hot rollback current source ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
