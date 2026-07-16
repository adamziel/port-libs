<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$orphanPath = '/srv/wp/database/orphan.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj89';
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

$mainClean1 = $page('current-source clean main schema before plugin import');
$mainClean2 = $page('current-source clean main active_plugins before plugin import');
$mainDirty1 = $page('current-source dirty main schema after crashed plugin import');
$mainDirty2 = $page('current-source dirty main active_plugins after crashed plugin import');
$mainDirty3 = $page('current-source dirty main overflow tail after crashed plugin import');
$staleMain1 = $page('stale snapshot main schema that must be ignored');
$staleMain2 = $page('stale snapshot active_plugins that must be ignored');
$siteClean1 = $page('current-source clean site schema before network import');
$siteClean2 = $page('current-source clean site upload_path before network import');
$siteDirty1 = $page('current-source dirty site schema after network import');
$siteDirty2 = $page('current-source dirty site upload_path after network import');
$orphanClean1 = $page('current-source clean orphan schema before import');
$orphanDirty1 = $page('current-source dirty orphan schema after import');

$mainDatabase = $mainDirty1 . $mainDirty2 . $mainDirty3;
$siteDatabase = $siteDirty1 . $siteDirty2;
$orphanDatabase = $orphanDirty1;
$staleMainDatabase = $staleMain1 . $staleMain2;
$mainJournalBytes = $makeJournal([1 => $mainClean1, 2 => $mainClean2], 2, 0x89000001);
$siteJournalBytes = $makeJournal([1 => $siteClean1, 2 => $siteClean2], 2, 0x89000002);
$orphanJournalBytes = $makeJournal([1 => $orphanClean1], 1, 0x89000003);
$staleJournalBytes = $makeJournal([1 => $staleMain1, 2 => $staleMain2], 2, 0x89000004);
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";
$duplicateMasterBytes = "\n" . $sitePath . "-journal\r\n" . $mainPath . "-journal\n" . $mainPath . "-journal\n";

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
];
$partialDatabases = [
    $databases[0],
    [
        'database_path' => $orphanPath,
        'current_database_bytes' => $orphanDatabase,
        'current_journal_bytes' => $orphanJournalBytes,
    ],
];

$plan = static fn (?string $bytes = null, array $input = null): array => SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    $bytes,
    $input ?? $databases,
    $pageSize
);
$complete = static fn (): array => $plan($masterBytes);
$missing = static fn (): array => $plan(null);
$partial = static fn (): array => $plan($masterBytes, $partialDatabases);
$duplicate = static fn (): array => $plan($duplicateMasterBytes);
$writeCurrentSourceFiles = static function (string $root, ?string $master = null, array $entries = null) use ($local, $masterPath, $masterBytes, $databases): void {
    @mkdir(dirname($local($root, $masterPath)), 0777, true);
    if ($master !== null) {
        file_put_contents($local($root, $masterPath), $master);
    }
    foreach ($entries ?? $databases as $entry) {
        $databasePath = (string) $entry['database_path'];
        @mkdir(dirname($local($root, $databasePath)), 0777, true);
        file_put_contents($local($root, $databasePath), (string) $entry['current_database_bytes']);
        file_put_contents($local($root, $databasePath . '-journal'), (string) $entry['current_journal_bytes']);
    }
};
$apply = static function (?string $master = null, array $entries = null) use ($writeCurrentSourceFiles, $masterBytes, $databases, $masterPath, $pageSize): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-master-hot-89-' . bin2hex(random_bytes(4));
    $writeCurrentSourceFiles($root, $master ?? $masterBytes, $entries ?? $databases);
    return (new SQLiteVfsFileWriter($root))->applyMasterJournalHotRollbackFromCurrentSource($masterPath, array_map(
        static fn (array $entry): array => array_filter([
            'database_path' => $entry['database_path'],
            'stale_database_bytes' => $entry['stale_database_bytes'] ?? null,
            'stale_journal_bytes' => $entry['stale_journal_bytes'] ?? null,
            'reserved_lock' => $entry['reserved_lock'] ?? null,
        ], static fn (mixed $value): bool => $value !== null),
        $entries ?? $databases
    ), $pageSize);
};

$cases = [
    'complete status' => static fn (): mixed => $complete()['status'],
    'complete reason' => static fn (): mixed => $complete()['reason'],
    'complete master exists' => static fn (): mixed => $complete()['master_journal_exists'],
    'complete member count' => static fn (): mixed => count($complete()['master_journal_members']),
    'complete database count' => static fn (): mixed => $complete()['database_count'],
    'complete recovered count' => static fn (): mixed => $complete()['recovered_database_count'],
    'complete blocked count' => static fn (): mixed => $complete()['blocked_database_count'],
    'complete stale candidate count' => static fn (): mixed => $complete()['stale_candidate_count'],
    'complete payload count' => static fn (): mixed => count($complete()['payloads']),
    'complete operations count' => static fn (): mixed => count($complete()['operations']),
    'complete first operation reason' => static fn (): mixed => $complete()['operations'][0]['reason'],
    'complete main truncate reason' => static fn (): mixed => $complete()['operations'][1]['reason'],
    'complete main sync reason' => static fn (): mixed => $complete()['operations'][2]['reason'],
    'complete main delete reason' => static fn (): mixed => $complete()['operations'][3]['reason'],
    'complete site write path' => static fn (): mixed => $complete()['operations'][4]['path'],
    'complete master delete reason' => static fn (): mixed => $complete()['operations'][8]['reason'],
    'complete directory sync reason' => static fn (): mixed => $complete()['operations'][9]['reason'],
    'complete main action' => static fn (): mixed => $complete()['journal_actions'][$mainPath . '-journal'],
    'complete site action' => static fn (): mixed => $complete()['journal_actions'][$sitePath . '-journal'],
    'complete main listed' => static fn (): mixed => $complete()['hot_journals'][$mainPath . '-journal']['listed_in_master_journal'],
    'complete site listed' => static fn (): mixed => $complete()['hot_journals'][$sitePath . '-journal']['listed_in_master_journal'],
    'complete main stale ignored' => static fn (): mixed => $complete()['hot_journals'][$mainPath . '-journal']['stale_candidate_ignored'],
    'complete site stale not ignored' => static fn (): mixed => $complete()['hot_journals'][$sitePath . '-journal']['stale_candidate_ignored'],
    'complete main current prefix dirty' => static fn (): mixed => str_starts_with($complete()['hot_journals'][$mainPath . '-journal']['current_source_database_prefix'], 'current-source dirty main'),
    'complete main next prefix clean' => static fn (): mixed => str_starts_with($complete()['hot_journals'][$mainPath . '-journal']['next_database_prefix'], 'current-source clean main'),
    'complete next main bytes' => static fn (): mixed => strlen($complete()['next_databases'][$mainPath]),
    'complete next site bytes' => static fn (): mixed => strlen($complete()['next_databases'][$sitePath]),
    'complete current source main bytes' => static fn (): mixed => $complete()['current_source_bytes'][$mainPath]['database'],
    'complete current source main journal bytes' => static fn (): mixed => $complete()['current_source_bytes'][$mainPath]['journal'],
    'complete stale bytes not restored' => static fn (): mixed => str_contains($complete()['next_databases'][$mainPath], 'stale snapshot'),
    'complete current source clean active plugins restored' => static fn (): mixed => str_contains($complete()['next_databases'][$mainPath], 'clean main active_plugins'),
    'complete tail page truncated' => static fn (): mixed => strlen($complete()['next_databases'][$mainPath]),
    'complete dependencies include slice' => static fn (): mixed => in_array('sqlite-pager-master-journal-hot-rollback-current-source-next89', $complete()['dependencies'], true),
    'complete dependencies include rollback' => static fn (): mixed => in_array('sqlite-rollback-journal-recovery', $complete()['dependencies'], true),
    'missing status' => static fn (): mixed => $missing()['status'],
    'missing recovered count' => static fn (): mixed => $missing()['recovered_database_count'],
    'missing blocked count' => static fn (): mixed => $missing()['blocked_database_count'],
    'missing operations empty' => static fn (): mixed => count($missing()['operations']),
    'missing main reason' => static fn (): mixed => $missing()['hot_journals'][$mainPath . '-journal']['reason'],
    'missing main action' => static fn (): mixed => $missing()['journal_actions'][$mainPath . '-journal'],
    'missing preserves current dirty' => static fn (): mixed => str_starts_with($missing()['next_databases'][$mainPath], 'current-source dirty main'),
    'partial status' => static fn (): mixed => $partial()['status'],
    'partial recovered count' => static fn (): mixed => $partial()['recovered_database_count'],
    'partial blocked count' => static fn (): mixed => $partial()['blocked_database_count'],
    'partial orphan listed false' => static fn (): mixed => $partial()['hot_journals'][$orphanPath . '-journal']['listed_in_master_journal'],
    'partial orphan reason' => static fn (): mixed => $partial()['hot_journals'][$orphanPath . '-journal']['reason'],
    'partial orphan remains dirty' => static fn (): mixed => str_starts_with($partial()['next_databases'][$orphanPath], 'current-source dirty orphan'),
    'duplicate status' => static fn (): mixed => $duplicate()['status'],
    'duplicate member count dedupes' => static fn (): mixed => count($duplicate()['master_journal_members']),
    'reserved lock blocks main' => static function () use ($databases, $masterBytes, $masterPath, $pageSize, $mainPath): mixed {
        $copy = $databases;
        $copy[0]['reserved_lock'] = true;
        return SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $copy, $pageSize)['hot_journals'][$mainPath . '-journal']['reason'];
    },
    'writer applies current source' => static fn (): mixed => $apply()['status'],
    'writer applied operation count' => static fn (): mixed => $apply()['applied'],
    'writer bytes written' => static fn (): mixed => $apply()['bytes_written'],
    'writer bytes truncated' => static fn (): mixed => $apply()['bytes_truncated'],
    'writer files deleted' => static fn (): mixed => $apply()['files_deleted'],
    'writer durable syncs' => static fn (): mixed => $apply()['durable_syncs'],
    'writer directory syncs' => static fn (): mixed => $apply()['directory_syncs'],
    'writer current source master exists' => static fn (): mixed => $apply()['current_source']['master_journal_exists'],
    'writer current source database count' => static fn (): mixed => count($apply()['current_source']['database_paths']),
    'writer recovery stale candidate count' => static fn (): mixed => $apply()['recovery']['stale_candidate_count'],
    'writer recovery deletes master' => static fn (): mixed => $apply()['recovery']['operations'][8]['reason'],
    'writer missing master skips' => static function () use ($writeCurrentSourceFiles, $masterPath, $databases, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-master-hot-89-' . bin2hex(random_bytes(4));
        $writeCurrentSourceFiles($root, null, $databases);
        return (new SQLiteVfsFileWriter($root))->applyMasterJournalHotRollbackFromCurrentSource($masterPath, [['database_path' => $databases[0]['database_path']], ['database_path' => $databases[1]['database_path']]], $pageSize)['status'];
    },
    'writer missing database rejected' => static function () use ($masterPath, $pageSize): mixed {
        $root = sys_get_temp_dir() . '/port-libsqlite-master-hot-89-' . bin2hex(random_bytes(4));
        try {
            (new SQLiteVfsFileWriter($root))->applyMasterJournalHotRollbackFromCurrentSource($masterPath, [['database_path' => '/missing.sqlite']], $pageSize);
        } catch (RuntimeException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty master path rejected' => static function () use ($masterBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext('', $masterBytes, $databases, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'empty database list rejected' => static function () use ($masterPath, $masterBytes, $pageSize): mixed {
        try {
            SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, [], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'bad page size rejected' => static function () use ($masterPath, $masterBytes, $databases): mixed {
        try {
            SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, 513);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'duplicate database rejected' => static function () use ($masterPath, $masterBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, [$databases[0], $databases[0]], $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'read only rejected' => static function () use ($masterPath, $masterBytes, $databases, $pageSize): mixed {
        try {
            SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, true);
        } catch (LogicException) {
            return 'rejected';
        }
        return 'accepted';
    },
    'unaligned current source rejected' => static function () use ($masterPath, $masterBytes, $databases, $pageSize): mixed {
        $copy = $databases;
        $copy[0]['current_database_bytes'] = 'short';
        try {
            SQLitePagerMasterJournalHotRollbackCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $copy, $pageSize);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'accepted';
    },
];

$expected = [
    'complete status' => 'master_journal_current_source_hot_rollback_complete',
    'complete reason' => 'current_vfs_source_controls_master_journal_hot_rollback',
    'complete master exists' => true,
    'complete member count' => 2,
    'complete database count' => 2,
    'complete recovered count' => 2,
    'complete blocked count' => 0,
    'complete stale candidate count' => 1,
    'complete payload count' => 2,
    'complete operations count' => 10,
    'complete first operation reason' => 'restore_current_source_database_from_master_hot_journal',
    'complete main truncate reason' => 'trim_current_source_database_after_master_hot_rollback',
    'complete main sync reason' => 'sync_current_source_database_after_master_hot_rollback',
    'complete main delete reason' => 'delete_current_source_hot_journal_after_master_rollback',
    'complete site write path' => $sitePath,
    'complete master delete reason' => 'delete_master_journal_after_current_source_hot_rollback',
    'complete directory sync reason' => 'persist_master_journal_current_source_hot_rollback',
    'complete main action' => 'delete_journal_after_recovery',
    'complete site action' => 'delete_journal_after_recovery',
    'complete main listed' => true,
    'complete site listed' => true,
    'complete main stale ignored' => true,
    'complete site stale not ignored' => false,
    'complete main current prefix dirty' => true,
    'complete main next prefix clean' => true,
    'complete next main bytes' => $pageSize * 2,
    'complete next site bytes' => $pageSize * 2,
    'complete current source main bytes' => $pageSize * 3,
    'complete current source main journal bytes' => strlen($mainJournalBytes),
    'complete stale bytes not restored' => false,
    'complete current source clean active plugins restored' => true,
    'complete tail page truncated' => $pageSize * 2,
    'complete dependencies include slice' => true,
    'complete dependencies include rollback' => true,
    'missing status' => 'master_journal_missing_preserved_current_source',
    'missing recovered count' => 0,
    'missing blocked count' => 2,
    'missing operations empty' => 0,
    'missing main reason' => 'missing_super_journal',
    'missing main action' => 'preserve_journal',
    'missing preserves current dirty' => true,
    'partial status' => 'master_journal_current_source_hot_rollback_partial',
    'partial recovered count' => 1,
    'partial blocked count' => 1,
    'partial orphan listed false' => false,
    'partial orphan reason' => 'missing_super_journal',
    'partial orphan remains dirty' => true,
    'duplicate status' => 'master_journal_current_source_hot_rollback_complete',
    'duplicate member count dedupes' => 2,
    'reserved lock blocks main' => 'database_has_reserved_lock',
    'writer applies current source' => 'applied',
    'writer applied operation count' => 10,
    'writer bytes written' => $pageSize * 4,
    'writer bytes truncated' => $pageSize * 4,
    'writer files deleted' => 3,
    'writer durable syncs' => 2,
    'writer directory syncs' => 1,
    'writer current source master exists' => true,
    'writer current source database count' => 2,
    'writer recovery stale candidate count' => 1,
    'writer recovery deletes master' => 'delete_master_journal_after_current_source_hot_rollback',
    'writer missing master skips' => 'skipped',
    'writer missing database rejected' => 'rejected',
    'empty master path rejected' => 'rejected',
    'empty database list rejected' => 'rejected',
    'bad page size rejected' => 'rejected',
    'duplicate database rejected' => 'rejected',
    'read only rejected' => 'rejected',
    'unaligned current source rejected' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['pager master journal hot rollback current source ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
