<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalWalRecoveryPlan;
use PortLibs\LibSqlite\SQLiteRollbackJournal;
use PortLibs\LibSqlite\SQLiteRollbackJournalHeader;
use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;

$tests = [];

$pageSize = 512;
$sectorSize = 512;
$mainPath = '/wp-content/database/main.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$missingPath = '/wp-content/database/missing.sqlite';
$superPath = '/wp-content/database/main.sqlite-mj74';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$makeJournalBytes = static function (array $pages, int $initialPageCount, int $nonce) use ($sectorSize, $pageSize): string {
    $header = SQLiteRollbackJournalHeader::MAGIC . pack('N*', count($pages), $nonce, $initialPageCount, $sectorSize, $pageSize);
    $bytes = str_pad($header, $sectorSize, "\0");
    foreach ($pages as $pageNumber => $pageImage) {
        $bytes .= pack('N', $pageNumber) . $pageImage . pack('N', SQLiteRollbackJournal::pageChecksum($pageImage, $nonce));
    }

    return $bytes;
};

$makeWalBytes = static function (array $frames, int $salt) use ($pageSize): string {
    $salt1 = 0x74000000 + $salt;
    $salt2 = 0x74200000 + $salt;
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

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $removeTree($path . DIRECTORY_SEPARATOR . $entry);
    }
    rmdir($path);
};

$mainCleanHeader = $page('main clean schema before master recovery');
$mainCleanOptions = $page('main clean wp_options before master recovery');
$mainDirtyHeader = $page('main dirty schema after interrupted attach write');
$mainDirtyOptions = $page('main dirty wp_options after interrupted attach write');
$siteCleanHeader = $page('site clean schema before master recovery');
$siteCleanOptions = $page('site clean wp_2_options before master recovery');
$siteDirtyHeader = $page('site dirty schema after interrupted attach write');
$siteDirtyOptions = $page('site dirty wp_2_options after interrupted attach write');
$mainWalOption = $page('main wal committed option after master recovery');
$siteWalOption = $page('site wal committed option after master recovery');

$mainJournalBytes = $makeJournalBytes([1 => $mainCleanHeader, 2 => $mainCleanOptions], 2, 0x74010001);
$siteJournalBytes = $makeJournalBytes([1 => $siteCleanHeader, 2 => $siteCleanOptions], 2, 0x74010002);
$mainWalBytes = $makeWalBytes([
    [2, 2, $mainWalOption],
    [1, 0, $page('main wal uncommitted schema tail')],
], 74);
$siteWalBytes = $makeWalBytes([
    [2, 2, $siteWalOption],
    [1, 0, $page('site wal uncommitted schema tail')],
], 75);

$superBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";
$superBytesWithMissingMember = $mainPath . "-journal\n" . $missingPath . "-journal\n";

$local = static fn (string $root, string $path): string => rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, '/');

$prepareRoot = static function (string $superInput = null, bool $includeSite = true) use (
    $removeTree,
    $local,
    $mainPath,
    $sitePath,
    $superPath,
    $superBytes,
    $mainDirtyHeader,
    $mainDirtyOptions,
    $siteDirtyHeader,
    $siteDirtyOptions,
    $mainJournalBytes,
    $siteJournalBytes,
    $mainWalBytes,
    $siteWalBytes
): string {
    $root = sys_get_temp_dir() . '/sqlite-pager-super-master-74-' . bin2hex(random_bytes(6));
    $removeTree($root);
    mkdir($local($root, '/wp-content/database'), 0777, true);
    file_put_contents($local($root, $mainPath), $mainDirtyHeader . $mainDirtyOptions);
    file_put_contents($local($root, $mainPath . '-journal'), $mainJournalBytes);
    file_put_contents($local($root, $mainPath . '-wal'), $mainWalBytes);
    if ($includeSite) {
        file_put_contents($local($root, $sitePath), $siteDirtyHeader . $siteDirtyOptions);
        file_put_contents($local($root, $sitePath . '-journal'), $siteJournalBytes);
        file_put_contents($local($root, $sitePath . '-wal'), $siteWalBytes);
    }
    file_put_contents($local($root, $superPath), $superInput ?? $superBytes);

    return $root;
};

$databaseInputs = static function (bool $includeSite = true) use (
    $mainPath,
    $sitePath,
    $mainDirtyHeader,
    $mainDirtyOptions,
    $siteDirtyHeader,
    $siteDirtyOptions,
    $mainJournalBytes,
    $siteJournalBytes,
    $mainWalBytes,
    $siteWalBytes,
    $pageSize
): array {
    $databases = [[
        'database_path' => $mainPath,
        'database_bytes' => $mainDirtyHeader . $mainDirtyOptions,
        'journal' => SQLiteRollbackJournal::parse($mainJournalBytes, true),
        'journal_bytes' => $mainJournalBytes,
        'wal_bytes' => $mainWalBytes,
        'page_numbers' => [1, 2],
        'database_page_size' => $pageSize,
    ]];
    if ($includeSite) {
        $databases[] = [
            'database_path' => $sitePath,
            'database_bytes' => $siteDirtyHeader . $siteDirtyOptions,
            'journal' => SQLiteRollbackJournal::parse($siteJournalBytes, true),
            'journal_bytes' => $siteJournalBytes,
            'wal_bytes' => $siteWalBytes,
            'page_numbers' => [1, 2],
            'database_page_size' => $pageSize,
        ];
    }

    return $databases;
};

$apply = static function (string $superInput = null, bool $includeSite = true) use (
    $prepareRoot,
    $removeTree,
    $databaseInputs,
    $local,
    $superPath,
    $superBytes,
    $mainPath,
    $sitePath
): array {
    $root = $prepareRoot($superInput, $includeSite);
    try {
        $writer = new SQLiteVfsFileWriter($root);
        $applied = $writer->applyMasterSuperJournalHotRecovery74(
            $superPath,
            $superInput ?? $superBytes,
            $databaseInputs($includeSite)
        );

        return [
            'applied' => $applied,
            'main_bytes' => file_get_contents($local($root, $mainPath)),
            'main_journal_exists' => is_file($local($root, $mainPath . '-journal')),
            'main_wal_bytes' => file_get_contents($local($root, $mainPath . '-wal')),
            'site_bytes' => $includeSite ? file_get_contents($local($root, $sitePath)) : null,
            'site_journal_exists' => $includeSite ? is_file($local($root, $sitePath . '-journal')) : null,
            'site_wal_bytes' => $includeSite ? file_get_contents($local($root, $sitePath . '-wal')) : null,
            'super_exists' => is_file($local($root, $superPath)),
            'super_bytes' => is_file($local($root, $superPath)) ? file_get_contents($local($root, $superPath)) : null,
        ];
    } finally {
        $removeTree($root);
    }
};

$normal = static fn (): array => $apply();
$missingMember = static fn (): array => $apply($superBytesWithMissingMember, false);

$cases = [
    'apply status' => static fn (): mixed => $normal()['applied']['status'],
    'apply is atomic' => static fn (): mixed => $normal()['applied']['atomic'],
    'recovery status' => static fn (): mixed => $normal()['applied']['recovery']['status'],
    'recovered database count' => static fn (): mixed => $normal()['applied']['recovery']['recovered_database_count'],
    'skipped database count' => static fn (): mixed => $normal()['applied']['recovery']['skipped_database_count'],
    'main database contains clean schema' => static fn (): mixed => str_contains((string) $normal()['main_bytes'], 'main clean schema'),
    'main database contains wal option' => static fn (): mixed => str_contains((string) $normal()['main_bytes'], 'main wal committed option'),
    'main database drops dirty schema' => static fn (): mixed => !str_contains((string) $normal()['main_bytes'], 'main dirty schema'),
    'main database length is two pages' => static fn (): mixed => strlen((string) $normal()['main_bytes']),
    'site database contains clean schema' => static fn (): mixed => str_contains((string) $normal()['site_bytes'], 'site clean schema'),
    'site database contains wal option' => static fn (): mixed => str_contains((string) $normal()['site_bytes'], 'site wal committed option'),
    'site database drops dirty schema' => static fn (): mixed => !str_contains((string) $normal()['site_bytes'], 'site dirty schema'),
    'site database length is two pages' => static fn (): mixed => strlen((string) $normal()['site_bytes']),
    'main journal deleted' => static fn (): mixed => $normal()['main_journal_exists'],
    'site journal deleted' => static fn (): mixed => $normal()['site_journal_exists'],
    'super journal deleted' => static fn (): mixed => $normal()['super_exists'],
    'main wal committed prefix preserved' => static fn (): mixed => str_contains((string) $normal()['main_wal_bytes'], 'main wal committed option'),
    'main wal uncommitted tail discarded' => static fn (): mixed => !str_contains((string) $normal()['main_wal_bytes'], 'main wal uncommitted schema tail'),
    'site wal committed prefix preserved' => static fn (): mixed => str_contains((string) $normal()['site_wal_bytes'], 'site wal committed option'),
    'site wal uncommitted tail discarded' => static fn (): mixed => !str_contains((string) $normal()['site_wal_bytes'], 'site wal uncommitted schema tail'),
    'files deleted include journals and super' => static fn (): mixed => $normal()['applied']['files_deleted'],
    'durable sync count includes databases and wals' => static fn (): mixed => $normal()['applied']['durable_syncs'],
    'directory sync count includes recovery dirs' => static fn (): mixed => $normal()['applied']['directory_syncs'],
    'operations applied count' => static fn (): mixed => $normal()['applied']['applied'],
    'bytes written include recovered databases and wals' => static fn (): mixed => $normal()['applied']['bytes_written'] > 3000,
    'bytes truncated include recovered databases and wals' => static fn (): mixed => $normal()['applied']['bytes_truncated'] > 3000,
    'dependencies include vfs apply slice' => static fn (): mixed => in_array('sqlite-pager-hot-journal-master-super-vfs-apply74', $normal()['applied']['dependencies'], true),
    'dependencies include atomic rollback' => static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $normal()['applied']['dependencies'], true),
    'dependencies include file handle application' => static fn (): mixed => in_array('vfs-file-handle-write-application', $normal()['applied']['dependencies'], true),
    'plan journal action main deleted' => static fn (): mixed => $normal()['applied']['recovery']['journal_actions'][$mainPath . '-journal'],
    'plan journal action site deleted' => static fn (): mixed => $normal()['applied']['recovery']['journal_actions'][$sitePath . '-journal'],
    'plan super action deleted' => static fn (): mixed => $normal()['applied']['recovery']['super_journal_action'],
    'first operation restores main hot journal image' => static fn (): mixed => $normal()['applied']['operations'][0]['reason'],
    'main delete operation appears' => static fn (): mixed => in_array('delete_hot_journal_before_wal_recovery', array_column($normal()['applied']['operations'], 'reason'), true),
    'super delete operation appears' => static fn (): mixed => in_array('delete_super_journal_after_named_hot_journals', array_column($normal()['applied']['operations'], 'reason'), true),
    'super directory sync operation appears' => static fn (): mixed => in_array('persist_super_journal_recovery_deletion', array_column($normal()['applied']['operations'], 'reason'), true),
    'main next reader uses database then wal' => static fn (): mixed => $normal()['applied']['recovery']['next_reader_sources'][$mainPath],
    'site next reader uses database then wal' => static fn (): mixed => $normal()['applied']['recovery']['next_reader_sources'][$sitePath],
    'main next reader frame indexes' => static fn (): mixed => $normal()['applied']['recovery']['next_reader_frame_indexes'][$mainPath],
    'site next reader frame indexes' => static fn (): mixed => $normal()['applied']['recovery']['next_reader_frame_indexes'][$sitePath],
    'missing member preserves super status' => static fn (): mixed => $missingMember()['applied']['recovery']['super_journal_action'],
    'missing member keeps super file' => static fn (): mixed => $missingMember()['super_exists'],
    'missing member super bytes unchanged' => static fn (): mixed => $missingMember()['super_bytes'],
    'missing member still recovers main' => static fn (): mixed => str_contains((string) $missingMember()['main_bytes'], 'main clean schema'),
    'missing member deletes recovered main journal' => static fn (): mixed => $missingMember()['main_journal_exists'],
    'missing member does not count absent db as provided' => static fn (): mixed => $missingMember()['applied']['recovery']['database_count'],
    'missing member recovered count' => static fn (): mixed => $missingMember()['applied']['recovery']['recovered_database_count'],
    'missing member operations omit super delete' => static fn (): mixed => in_array('delete_super_journal_after_named_hot_journals', array_column($missingMember()['applied']['operations'], 'reason'), true),
    'missing member dependencies include slice' => static fn (): mixed => in_array('sqlite-pager-hot-journal-master-super-vfs-apply74', $missingMember()['applied']['dependencies'], true),
    'planner still exposes current next status' => static fn (): mixed => SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext($superPath, $superBytes, $databaseInputs())['status'],
    'read only writer rejects apply' => static function () use ($prepareRoot, $removeTree, $databaseInputs, $superPath, $superBytes): mixed {
        $root = $prepareRoot();
        try {
            (new SQLiteVfsFileWriter($root, readOnly: true))->applyMasterSuperJournalHotRecovery74($superPath, $superBytes, $databaseInputs());
        } catch (LogicException) {
            return 'rejected';
        } finally {
            $removeTree($root);
        }

        return 'accepted';
    },
    'immutable writer rejects apply' => static function () use ($prepareRoot, $removeTree, $databaseInputs, $superPath, $superBytes): mixed {
        $root = $prepareRoot();
        try {
            (new SQLiteVfsFileWriter($root, immutable: true))->applyMasterSuperJournalHotRecovery74($superPath, $superBytes, $databaseInputs());
        } catch (LogicException) {
            return 'rejected';
        } finally {
            $removeTree($root);
        }

        return 'accepted';
    },
];

$expected = [
    'apply status' => 'applied',
    'apply is atomic' => true,
    'recovery status' => 'super_journal_hot_recovery_current_next',
    'recovered database count' => 2,
    'skipped database count' => 0,
    'main database contains clean schema' => true,
    'main database contains wal option' => true,
    'main database drops dirty schema' => true,
    'main database length is two pages' => 1024,
    'site database contains clean schema' => true,
    'site database contains wal option' => true,
    'site database drops dirty schema' => true,
    'site database length is two pages' => 1024,
    'main journal deleted' => false,
    'site journal deleted' => false,
    'super journal deleted' => false,
    'main wal committed prefix preserved' => true,
    'main wal uncommitted tail discarded' => true,
    'site wal committed prefix preserved' => true,
    'site wal uncommitted tail discarded' => true,
    'files deleted include journals and super' => 3,
    'durable sync count includes databases and wals' => 6,
    'directory sync count includes recovery dirs' => 3,
    'operations applied count' => 24,
    'bytes written include recovered databases and wals' => true,
    'bytes truncated include recovered databases and wals' => true,
    'dependencies include vfs apply slice' => true,
    'dependencies include atomic rollback' => true,
    'dependencies include file handle application' => true,
    'plan journal action main deleted' => 'delete_journal_after_recovery',
    'plan journal action site deleted' => 'delete_journal_after_recovery',
    'plan super action deleted' => 'delete_super_journal_after_named_hot_journals',
    'first operation restores main hot journal image' => 'restore_hot_journal_database_before_wal_recovery',
    'main delete operation appears' => true,
    'super delete operation appears' => true,
    'super directory sync operation appears' => true,
    'main next reader uses database then wal' => ['database', 'wal'],
    'site next reader uses database then wal' => ['database', 'wal'],
    'main next reader frame indexes' => [null, 1],
    'site next reader frame indexes' => [null, 1],
    'missing member preserves super status' => 'preserve_super_journal_until_named_journals_clear',
    'missing member keeps super file' => true,
    'missing member super bytes unchanged' => $superBytesWithMissingMember,
    'missing member still recovers main' => true,
    'missing member deletes recovered main journal' => false,
    'missing member does not count absent db as provided' => 1,
    'missing member recovered count' => 1,
    'missing member operations omit super delete' => false,
    'missing member dependencies include slice' => true,
    'planner still exposes current next status' => 'super_journal_hot_recovery_current_next',
    'read only writer rejects apply' => 'rejected',
    'immutable writer rejects apply' => 'rejected',
];

foreach ($cases as $name => $callback) {
    $tests['pager hot journal super master recovery current next74 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
