<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointMasterJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/srv/wp/database/main.sqlite';
$sitePath = '/srv/wp/database/site.sqlite';
$masterPath = '/srv/wp/database/main.sqlite-mj92';
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

$mainClean1 = $page('next92 clean main schema before crashed savepoint import');
$mainClean2 = $page('next92 clean main active_plugins before crashed savepoint import');
$mainClean3 = $page('next92 clean main options tail before crashed savepoint import');
$mainDirty1 = $page('next92 dirty main schema after crashed savepoint import');
$mainDirty2 = $page('next92 dirty main active_plugins after crashed savepoint import');
$mainDirty3 = $page('next92 dirty main options tail after crashed savepoint import');
$siteClean1 = $page('next92 clean site schema before attached savepoint import');
$siteDirty1 = $page('next92 dirty site schema after attached savepoint import');
$retry2 = $page('next92 retry writes recovered active_plugins from plugin batch');
$retry4 = $page('next92 retry appends fresh plugin option after recovery');
$staleMain = $page('next92 stale dirty cache page that must not be captured');

$mainDatabase = $mainDirty1 . $mainDirty2 . $mainDirty3;
$siteDatabase = $siteDirty1;
$mainJournalBytes = $makeJournal([1 => $mainClean1, 2 => $mainClean2, 3 => $mainClean3], 3, 0x92000001);
$siteJournalBytes = $makeJournal([1 => $siteClean1], 1, 0x92000002);
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";
$databases = [
    [
        'database_path' => $mainPath,
        'current_database_bytes' => $mainDatabase,
        'current_journal_bytes' => $mainJournalBytes,
        'stale_database_bytes' => $staleMain . $mainDirty2,
    ],
    [
        'database_path' => $sitePath,
        'current_database_bytes' => $siteDatabase,
        'current_journal_bytes' => $siteJournalBytes,
    ],
];
$retryWrites = [2 => $retry2, 4 => $retry4];

$plan = static fn (?string $master = null, array $input = null, array $writes = null): array => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext(
    $masterPath,
    func_num_args() >= 1 ? $master : $masterBytes,
    $input ?? $databases,
    $pageSize,
    $mainPath,
    'plugin-import-next92',
    $writes ?? $retryWrites
);
$missing = static fn (): array => $plan(null);
$reserved = static function () use ($databases, $masterBytes, $masterPath, $pageSize, $mainPath, $retryWrites): array {
    $copy = $databases;
    $copy[0]['reserved_lock'] = true;
    return SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext(
        $masterPath,
        $masterBytes,
        $copy,
        $pageSize,
        $mainPath,
        'plugin-import-next92',
        $retryWrites
    );
};
$writeFiles = static function (string $root, ?string $master = null, array $entries = null) use ($local, $masterPath, $masterBytes, $databases): void {
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
$apply = static function (?string $master = null, array $entries = null) use ($writeFiles, $masterBytes, $databases, $masterPath, $pageSize, $mainPath, $retryWrites, $removeTree): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-savepoint-master-92-' . bin2hex(random_bytes(4));
    $writeFiles($root, func_num_args() >= 1 ? $master : $masterBytes, $entries ?? $databases);
    try {
        return (new SQLiteVfsFileWriter($root))->applySavepointMasterJournalCurrentSourceNext92(
            $masterPath,
            array_map(
                static fn (array $entry): array => array_filter([
                    'database_path' => $entry['database_path'],
                    'stale_database_bytes' => $entry['stale_database_bytes'] ?? null,
                    'reserved_lock' => $entry['reserved_lock'] ?? null,
                ], static fn (mixed $value): bool => $value !== null),
                $entries ?? $databases
            ),
            $pageSize,
            $mainPath,
            'plugin-import-next92',
            $retryWrites
        );
    } finally {
        $removeTree($root);
    }
};

$cases = [
    'status' => static fn (): mixed => $plan()['status'],
    'reason' => static fn (): mixed => $plan()['reason'],
    'primary path' => static fn (): mixed => $plan()['primary_database_path'],
    'savepoint' => static fn (): mixed => $plan()['savepoint'],
    'retry page numbers' => static fn (): mixed => $plan()['retry_page_numbers'],
    'master recovered complete' => static fn (): mixed => $plan()['master_recovery']['status'],
    'master recovered count' => static fn (): mixed => $plan()['master_recovery']['recovered_database_count'],
    'master blocked count' => static fn (): mixed => $plan()['master_recovery']['blocked_database_count'],
    'master stale ignored count' => static fn (): mixed => $plan()['master_recovery']['stale_candidate_count'],
    'captured count' => static fn (): mixed => count($plan()['captured_before_images']),
    'captured first page' => static fn (): mixed => $plan()['captured_before_images'][0]['page_number'],
    'captured first source' => static fn (): mixed => $plan()['captured_before_images'][0]['source'],
    'captured first clean prefix' => static fn (): mixed => $plan()['captured_before_images'][0]['prefix'],
    'captured first not dirty current' => static fn (): mixed => $plan()['captured_before_images'][0]['matches_dirty_current_source'],
    'captured first not zero fill' => static fn (): mixed => $plan()['captured_before_images'][0]['zero_filled_short_read'],
    'captured second page' => static fn (): mixed => $plan()['captured_before_images'][1]['page_number'],
    'captured second zero fill' => static fn (): mixed => $plan()['captured_before_images'][1]['zero_filled_short_read'],
    'captured second source' => static fn (): mixed => $plan()['captured_before_images'][1]['source'],
    'recovered has clean active_plugins' => static fn (): mixed => str_contains($plan()['recovered_database_bytes'], 'clean main active_plugins'),
    'recovered excludes retry write' => static fn (): mixed => str_contains($plan()['recovered_database_bytes'], 'retry writes recovered'),
    'final has retry active_plugins' => static fn (): mixed => str_contains($plan()['final_database_bytes'], 'retry writes recovered active_plugins'),
    'final has retry append' => static fn (): mixed => str_contains($plan()['final_database_bytes'], 'retry appends fresh plugin option'),
    'final excludes dirty active_plugins' => static fn (): mixed => str_contains($plan()['final_database_bytes'], 'dirty main active_plugins'),
    'final size includes append page' => static fn (): mixed => strlen($plan()['final_database_bytes']),
    'operations include recovery and logical retry' => static fn (): mixed => count($plan()['operations']),
    'logical retry capture op' => static fn (): mixed => $plan()['operations'][10]['op'],
    'logical retry write op' => static fn (): mixed => $plan()['operations'][11]['op'],
    'apply operation count' => static fn (): mixed => count($plan()['apply_operations']),
    'apply last write reason' => static fn (): mixed => $plan()['apply_operations'][10]['reason'],
    'apply last truncate reason' => static fn (): mixed => $plan()['apply_operations'][11]['reason'],
    'apply last sync reason' => static fn (): mixed => $plan()['apply_operations'][12]['reason'],
    'apply directory sync reason' => static fn (): mixed => $plan()['apply_operations'][13]['reason'],
    'payload includes final' => static fn (): mixed => isset($plan()['payloads'][$mainPath . '#savepoint-master-current-source-next92']),
    'payload final contains retry' => static fn (): mixed => str_contains($plan()['payloads'][$mainPath . '#savepoint-master-current-source-next92'], 'retry writes recovered'),
    'dependency marker' => static fn (): mixed => in_array('sqlite-pager-savepoint-master-journal-current-source-next92', $plan()['dependencies'], true),
    'dependency master recovery' => static fn (): mixed => in_array('sqlite-pager-master-journal-hot-rollback-current-source-next89', $plan()['dependencies'], true),
    'missing status' => static fn (): mixed => $missing()['status'],
    'missing final preserves dirty' => static fn (): mixed => str_contains($missing()['final_database_bytes'], 'dirty main active_plugins'),
    'missing apply operations empty' => static fn (): mixed => count($missing()['apply_operations']),
    'reserved status' => static fn (): mixed => $reserved()['status'],
    'reserved master partial' => static fn (): mixed => $reserved()['master_recovery']['status'],
    'reserved first reason' => static fn (): mixed => $reserved()['master_recovery']['hot_journals'][$mainPath . '-journal']['reason'],
    'writer status' => static fn (): mixed => $apply()['status'],
    'writer applied operations' => static fn (): mixed => $apply()['applied'],
    'writer bytes written' => static fn (): mixed => $apply()['bytes_written'],
    'writer bytes truncated' => static fn (): mixed => $apply()['bytes_truncated'],
    'writer files deleted' => static fn (): mixed => $apply()['files_deleted'],
    'writer durable syncs' => static fn (): mixed => $apply()['durable_syncs'],
    'writer directory syncs' => static fn (): mixed => $apply()['directory_syncs'],
    'writer atomic' => static fn (): mixed => $apply()['atomic'],
    'writer recovery final contains retry' => static fn (): mixed => str_contains($apply()['recovery']['final_database_bytes'], 'retry appends fresh plugin option'),
    'writer current source count' => static fn (): mixed => count($apply()['current_source']['database_paths']),
    'writer skipped when master missing' => static fn (): mixed => $apply(null)['status'],
];

$expected = [
    'status' => 'master_journal_recovered_retry_savepoint_current_source_next',
    'reason' => 'retry_savepoint_uses_master_journal_recovered_current_source',
    'primary path' => $mainPath,
    'savepoint' => 'plugin-import-next92',
    'retry page numbers' => [2, 4],
    'master recovered complete' => 'master_journal_current_source_hot_rollback_complete',
    'master recovered count' => 2,
    'master blocked count' => 0,
    'master stale ignored count' => 1,
    'captured count' => 2,
    'captured first page' => 2,
    'captured first source' => 'master-journal-recovered-database',
    'captured first clean prefix' => 'next92 clean main active_plugins before crashed savepoin',
    'captured first not dirty current' => false,
    'captured first not zero fill' => false,
    'captured second page' => 4,
    'captured second zero fill' => true,
    'captured second source' => 'zero-fill',
    'recovered has clean active_plugins' => true,
    'recovered excludes retry write' => false,
    'final has retry active_plugins' => true,
    'final has retry append' => true,
    'final excludes dirty active_plugins' => false,
    'final size includes append page' => $pageSize * 4,
    'operations include recovery and logical retry' => 14,
    'logical retry capture op' => 'capture_savepoint_before_image',
    'logical retry write op' => 'write_retry_savepoint_page',
    'apply operation count' => 14,
    'apply last write reason' => 'write_retry_savepoint_after_master_current_source_recovery',
    'apply last truncate reason' => 'trim_retry_savepoint_after_master_current_source_recovery',
    'apply last sync reason' => 'sync_retry_savepoint_after_master_current_source_recovery',
    'apply directory sync reason' => 'persist_retry_savepoint_after_master_current_source_recovery',
    'payload includes final' => true,
    'payload final contains retry' => true,
    'dependency marker' => true,
    'dependency master recovery' => true,
    'missing status' => 'master_journal_recovery_blocked_before_retry_savepoint',
    'missing final preserves dirty' => true,
    'missing apply operations empty' => 0,
    'reserved status' => 'master_journal_recovered_retry_savepoint_current_source_next',
    'reserved master partial' => 'master_journal_current_source_hot_rollback_partial',
    'reserved first reason' => 'database_has_reserved_lock',
    'writer status' => 'applied',
    'writer applied operations' => 14,
    'writer bytes written' => $pageSize * 8,
    'writer bytes truncated' => $pageSize * 8,
    'writer files deleted' => 3,
    'writer durable syncs' => 3,
    'writer directory syncs' => 2,
    'writer atomic' => true,
    'writer recovery final contains retry' => true,
    'writer current source count' => 2,
    'writer skipped when master missing' => 'skipped',
];

foreach ($cases as $name => $callback) {
    $tests['pager savepoint master journal current source next92 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$throws = [
    'empty primary rejected' => static fn () => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, '', 's', $retryWrites),
    'empty savepoint rejected' => static fn () => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, '', $retryWrites),
    'empty writes rejected' => static fn () => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 's', []),
    'bad page size rejected' => static fn () => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, 500, $mainPath, 's', $retryWrites),
    'zero page rejected' => static fn () => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 's', [0 => $retry2]),
    'short page rejected' => static fn () => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, $mainPath, 's', [2 => 'short']),
    'missing primary rejected' => static fn () => SQLitePagerSavepointMasterJournalCurrentSourceNextPlan::currentSourceNext($masterPath, $masterBytes, $databases, $pageSize, '/missing.sqlite', 's', $retryWrites),
    'writer missing database rejected' => static function () use ($masterPath, $pageSize, $mainPath, $retryWrites): void {
        $root = sys_get_temp_dir() . '/port-libsqlite-savepoint-master-92-' . bin2hex(random_bytes(4));
        (new SQLiteVfsFileWriter($root))->applySavepointMasterJournalCurrentSourceNext92($masterPath, [['database_path' => $mainPath]], $pageSize, $mainPath, 's', $retryWrites);
    },
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint master journal current source next92 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
