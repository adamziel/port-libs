<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;

$tests = [];

$pageSize = 512;
$mainPath = '/wp-content/database/.ht.sqlite';
$sitePath = '/wp-content/database/site.sqlite';
$orphanPath = '/wp-content/database/orphan.sqlite';
$masterPath = '/wp-content/database/.ht.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, "\0");

$mainCurrent = $page('main header current source')
    . $page('main wp_options dirty failed option')
    . $page('main wp_options dirty autoload index');
$siteCurrent = $page('site header current source')
    . $page('site wp_sitemeta dirty failed option')
    . $page('site wp_sitemeta dirty cache index');
$orphanCurrent = $page('orphan header current source')
    . $page('orphan dirty failed option');

$mainBeforeOption = $page('main wp_options before failed option');
$mainBeforeIndex = $page('main wp_options before autoload index');
$siteBeforeOption = $page('site wp_sitemeta before failed option');
$siteBeforeIndex = $page('site wp_sitemeta before cache index');
$orphanBeforeOption = $page('orphan before failed option');
$masterBytes = $mainPath . "-journal\n" . $sitePath . "-journal\n";

$databaseSpecs = static fn (): array => [
    [
        'database_path' => $mainPath,
        'statement_journal_path' => $mainPath . '-stmt-journal',
        'statement_pages' => [3 => $mainBeforeIndex, 2 => $mainBeforeOption],
        'outer_journal_bytes' => 'main outer transaction journal remains live',
    ],
    [
        'database_path' => $sitePath,
        'statement_pages' => [2 => $siteBeforeOption, 3 => $siteBeforeIndex],
        'outer_journal_bytes' => 'site outer transaction journal remains live',
    ],
    [
        'database_path' => $orphanPath,
        'statement_pages' => [2 => $orphanBeforeOption],
        'outer_journal_bytes' => 'orphan outer journal is not in master',
    ],
];

$makeRoot = static function (bool $withMaster = true, bool $reservedSite = false) use (
    $masterPath,
    $masterBytes,
    $mainPath,
    $sitePath,
    $orphanPath,
    $mainCurrent,
    $siteCurrent,
    $orphanCurrent,
    $databaseSpecs
): array {
    $root = sys_get_temp_dir() . '/port-libsqlite-stmt-source-' . bin2hex(random_bytes(4));
    $mainLocal = $root . $mainPath;
    if (!is_dir(dirname($mainLocal)) && !mkdir(dirname($mainLocal), 0777, true) && !is_dir(dirname($mainLocal))) {
        throw new RuntimeException('Unable to create pager statement current-source fixture');
    }

    file_put_contents($root . $mainPath, $mainCurrent);
    file_put_contents($root . $sitePath, $siteCurrent);
    file_put_contents($root . $orphanPath, $orphanCurrent);
    file_put_contents($root . $mainPath . '-journal', 'main outer rollback journal');
    file_put_contents($root . $mainPath . '-stmt-journal', 'main statement journal');
    file_put_contents($root . $sitePath . '-journal', 'site outer rollback journal');
    file_put_contents($root . $sitePath . '-stmt-journal', 'site statement journal');
    file_put_contents($root . $orphanPath . '-journal', 'orphan outer rollback journal');
    file_put_contents($root . $orphanPath . '-stmt-journal', 'orphan statement journal');
    if ($withMaster) {
        file_put_contents($root . $masterPath, $masterBytes);
    }

    $specs = $databaseSpecs();
    if ($reservedSite) {
        $specs[1]['reserved_lock'] = true;
    }

    return [$root, $specs];
};

$apply = static fn (bool $withMaster = true, bool $reservedSite = false): array => (static function () use ($makeRoot, $masterPath, $pageSize, $withMaster, $reservedSite): array {
    [$root, $specs] = $makeRoot($withMaster, $reservedSite);
    $applied = (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementPageRecoveryFromCurrentSource($masterPath, $specs, $pageSize);

    return [
        'root' => $root,
        'applied' => $applied,
        'main' => is_file($root . '/wp-content/database/.ht.sqlite') ? (string) file_get_contents($root . '/wp-content/database/.ht.sqlite') : '',
        'site' => is_file($root . '/wp-content/database/site.sqlite') ? (string) file_get_contents($root . '/wp-content/database/site.sqlite') : '',
        'orphan' => is_file($root . '/wp-content/database/orphan.sqlite') ? (string) file_get_contents($root . '/wp-content/database/orphan.sqlite') : '',
        'main_stmt_exists' => is_file($root . '/wp-content/database/.ht.sqlite-stmt-journal'),
        'site_stmt_exists' => is_file($root . '/wp-content/database/site.sqlite-stmt-journal'),
        'orphan_stmt_exists' => is_file($root . '/wp-content/database/orphan.sqlite-stmt-journal'),
        'main_journal_exists' => is_file($root . '/wp-content/database/.ht.sqlite-journal'),
        'site_journal_exists' => is_file($root . '/wp-content/database/site.sqlite-journal'),
        'master_exists' => is_file($root . '/wp-content/database/.ht.sqlite-mj'),
    ];
})();

$cases = [
    'apply status' => [static fn (): mixed => $apply()['applied']['status'], 'applied'],
    'apply atomic' => [static fn (): mixed => $apply()['applied']['atomic'], true],
    'applied operation count' => [static fn (): mixed => $apply()['applied']['applied'], 7],
    'bytes written from current sources' => [static fn (): mixed => $apply()['applied']['bytes_written'], $pageSize * 6],
    'bytes truncated from current sources' => [static fn (): mixed => $apply()['applied']['bytes_truncated'], $pageSize * 6],
    'statement journals deleted count' => [static fn (): mixed => $apply()['applied']['files_deleted'], 2],
    'durable sync count' => [static fn (): mixed => $apply()['applied']['durable_syncs'], 0],
    'directory sync count' => [static fn (): mixed => $apply()['applied']['directory_syncs'], 1],
    'current source master path' => [static fn (): mixed => $apply()['applied']['current_source']['master_journal_path'], $masterPath],
    'current source master exists' => [static fn (): mixed => $apply()['applied']['current_source']['master_journal_exists'], true],
    'current source database order' => [static fn (): mixed => $apply()['applied']['current_source']['database_paths'], [$mainPath, $sitePath, $orphanPath]],
    'current source main bytes' => [static fn (): mixed => $apply()['applied']['current_source']['database_bytes'][$mainPath], $pageSize * 3],
    'current source site bytes' => [static fn (): mixed => $apply()['applied']['current_source']['database_bytes'][$sitePath], $pageSize * 3],
    'current source orphan bytes' => [static fn (): mixed => $apply()['applied']['current_source']['database_bytes'][$orphanPath], $pageSize * 2],
    'default statement journal path recorded' => [static fn (): mixed => $apply()['applied']['current_source']['statement_journal_paths'][$sitePath], $sitePath . '-stmt-journal'],
    'custom statement journal path recorded' => [static fn (): mixed => $apply()['applied']['current_source']['statement_journal_paths'][$mainPath], $mainPath . '-stmt-journal'],
    'recovery status' => [static fn (): mixed => $apply()['applied']['recovery']['status'], 'master_journal_statement_recovered_current_next'],
    'recovered database count' => [static fn (): mixed => $apply()['applied']['recovery']['recovered_database_count'], 2],
    'skipped database count' => [static fn (): mixed => $apply()['applied']['recovery']['skipped_database_count'], 1],
    'master members hydrated from file' => [static fn (): mixed => $apply()['applied']['recovery']['master_journal_members'], [$mainPath . '-journal', $sitePath . '-journal']],
    'main current prefix came from file' => [static fn (): mixed => $apply()['applied']['recovery']['current_page_prefixes'][$mainPath][2], 'main wp_options dirty failed option'],
    'site current prefix came from file' => [static fn (): mixed => $apply()['applied']['recovery']['current_page_prefixes'][$sitePath][3], 'site wp_sitemeta dirty cache index'],
    'main next prefix restored' => [static fn (): mixed => $apply()['applied']['recovery']['next_page_prefixes'][$mainPath][2], 'main wp_options before failed option'],
    'main next second page restored' => [static fn (): mixed => $apply()['applied']['recovery']['next_page_prefixes'][$mainPath][3], 'main wp_options before autoload index'],
    'site next prefix restored' => [static fn (): mixed => $apply()['applied']['recovery']['next_page_prefixes'][$sitePath][2], 'site wp_sitemeta before failed option'],
    'site next second page restored' => [static fn (): mixed => $apply()['applied']['recovery']['next_page_prefixes'][$sitePath][3], 'site wp_sitemeta before cache index'],
    'orphan current prefix kept' => [static fn (): mixed => $apply()['applied']['recovery']['next_page_prefixes'][$orphanPath][2], 'orphan dirty failed option'],
    'main reason recovered' => [static fn (): mixed => $apply()['applied']['recovery']['databases'][$mainPath]['reason'], 'master_journal_member_statement_rollback'],
    'site reason recovered' => [static fn (): mixed => $apply()['applied']['recovery']['databases'][$sitePath]['reason'], 'master_journal_member_statement_rollback'],
    'orphan reason skipped' => [static fn (): mixed => $apply()['applied']['recovery']['databases'][$orphanPath]['reason'], 'missing_master_journal_member'],
    'main statement action delete' => [static fn (): mixed => $apply()['applied']['recovery']['statement_journal_actions'][$mainPath . '-stmt-journal'], 'delete_statement_journal_after_rollback'],
    'site statement action delete' => [static fn (): mixed => $apply()['applied']['recovery']['statement_journal_actions'][$sitePath . '-stmt-journal'], 'delete_statement_journal_after_rollback'],
    'orphan statement action preserve' => [static fn (): mixed => $apply()['applied']['recovery']['statement_journal_actions'][$orphanPath . '-stmt-journal'], 'preserve_statement_journal'],
    'main statement journal deleted on disk' => [static fn (): mixed => $apply()['main_stmt_exists'], false],
    'site statement journal deleted on disk' => [static fn (): mixed => $apply()['site_stmt_exists'], false],
    'orphan statement journal preserved on disk' => [static fn (): mixed => $apply()['orphan_stmt_exists'], true],
    'outer main journal preserved' => [static fn (): mixed => $apply()['main_journal_exists'], true],
    'outer site journal preserved' => [static fn (): mixed => $apply()['site_journal_exists'], true],
    'master journal preserved for outer transaction' => [static fn (): mixed => $apply()['master_exists'], true],
    'main file restored first statement page' => [static fn (): mixed => substr($apply()['main'], $pageSize, $pageSize), $mainBeforeOption],
    'main file restored second statement page' => [static fn (): mixed => substr($apply()['main'], $pageSize * 2, $pageSize), $mainBeforeIndex],
    'main file kept header page' => [static fn (): mixed => substr($apply()['main'], 0, $pageSize), $page('main header current source')],
    'site file restored first statement page' => [static fn (): mixed => substr($apply()['site'], $pageSize, $pageSize), $siteBeforeOption],
    'site file restored second statement page' => [static fn (): mixed => substr($apply()['site'], $pageSize * 2, $pageSize), $siteBeforeIndex],
    'orphan file remains dirty' => [static fn (): mixed => substr($apply()['orphan'], $pageSize, $pageSize), $page('orphan dirty failed option')],
    'first recovery operation reads current source payload' => [static fn (): mixed => $apply()['applied']['recovery']['operations'][0]['payload_key'], $mainPath . '#statement-rollback'],
    'operation order' => [static fn (): mixed => array_column($apply()['applied']['operations'], 'op'), ['write', 'truncate', 'delete', 'write', 'truncate', 'delete', 'sync_directory']],
    'dependency current-source present' => [static fn (): mixed => in_array('sqlite-pager-statement-current-source-next84', $apply()['applied']['dependencies'], true), true],
    'dependency existing statement recovery present' => [static fn (): mixed => in_array('sqlite-statement-journal-page-recovery', $apply()['applied']['dependencies'], true), true],
    'reserved site is skipped' => [static fn (): mixed => $apply(true, true)['applied']['recovery']['databases'][$sitePath]['reason'], 'database_has_reserved_lock'],
    'reserved site statement journal preserved' => [static fn (): mixed => $apply(true, true)['site_stmt_exists'], true],
    'reserved site recovery count' => [static fn (): mixed => $apply(true, true)['applied']['recovery']['recovered_database_count'], 1],
    'missing master skipped' => [static fn (): mixed => $apply(false)['applied']['status'], 'skipped'],
    'missing master source recorded absent' => [static fn (): mixed => $apply(false)['applied']['current_source']['master_journal_exists'], false],
    'missing master no operations' => [static fn (): mixed => $apply(false)['applied']['applied'], 0],
    'missing master keeps main statement journal' => [static fn (): mixed => $apply(false)['main_stmt_exists'], true],
    'missing master reason' => [static fn (): mixed => $apply(false)['applied']['recovery']['databases'][$mainPath]['reason'], 'missing_master_journal'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager statement journal recovery current source ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty master rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyMasterJournalStatementPageRecoveryFromCurrentSource('', $databaseSpecs(), $pageSize),
    'empty database list rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyMasterJournalStatementPageRecoveryFromCurrentSource($masterPath, [], $pageSize),
    'missing database rejected' => static function () use ($masterPath, $pageSize): void {
        $root = sys_get_temp_dir() . '/port-libsqlite-stmt-source-missing-' . bin2hex(random_bytes(4));
        mkdir($root . '/wp-content/database', 0777, true);
        file_put_contents($root . $masterPath, "/missing.sqlite-journal\n");
        (new SQLiteVfsFileWriter($root))->applyMasterJournalStatementPageRecoveryFromCurrentSource($masterPath, [[
            'database_path' => '/missing.sqlite',
            'statement_pages' => [1 => str_repeat('m', $pageSize)],
        ]], $pageSize);
    },
    'path escape rejected' => static fn () => (new SQLiteVfsFileWriter(sys_get_temp_dir()))->applyMasterJournalStatementPageRecoveryFromCurrentSource('../escape-mj', $databaseSpecs(), $pageSize),
    'readonly rejected before hydration write' => static function () use ($makeRoot, $masterPath, $pageSize): void {
        [$root, $specs] = $makeRoot();
        (new SQLiteVfsFileWriter($root, true))->applyMasterJournalStatementPageRecoveryFromCurrentSource($masterPath, $specs, $pageSize);
    },
];

foreach ($throws as $name => $callback) {
    $tests['pager statement journal recovery current source ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
