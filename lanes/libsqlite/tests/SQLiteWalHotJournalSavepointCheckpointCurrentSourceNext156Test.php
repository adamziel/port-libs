<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsFileWriter;
use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next156.sqlite';
$journalPath = $databasePath . '-journal';
$walPath = $databasePath . '-wal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$dirty = [
    1 => $page('next156 dirty sqlite header after crashed import'),
    2 => $page('next156 dirty wp_options root after crashed import'),
    3 => $page('next156 dirty autoload index after crashed import'),
    4 => $page('next156 dirty plugin row after crashed import'),
    5 => $page('next156 dirty transient row after crashed import'),
    6 => $page('next156 dirty cron row after crashed import'),
];
$clean = [
    1 => $page('next156 clean sqlite header before crashed import'),
    2 => $page('next156 clean wp_options root before crashed import'),
    3 => $page('next156 clean autoload index before crashed import'),
    4 => $page('next156 clean plugin row before crashed import'),
    5 => $page('next156 clean transient row before crashed import'),
    6 => $page('next156 clean cron row before crashed import'),
];
$databaseBytes = implode('', $dirty);
$hotPages = [2 => $clean[2], 4 => $clean[4], 6 => $clean[6]];
$currentSourcePages = [2 => $dirty[2], 3 => $dirty[3], 4 => $dirty[4], 5 => $dirty[5], 6 => $dirty[6]];
$currentSavepointWrites = [
    2 => $page('next156 current savepoint wp_options draft'),
    3 => $page('next156 current savepoint autoload draft'),
    6 => $page('next156 current savepoint cron draft'),
];
$nextSavepointWrites = [
    4 => $page('next156 next savepoint plugin retry'),
    5 => $page('next156 next savepoint transient retry'),
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
    [1, 0, 'next156 current wal schema draft'],
    [2, 6, 'next156 current wal wp_options commit'],
    [3, 0, 'next156 current wal autoload draft'],
    [4, 6, 'next156 current wal plugin commit'],
    [5, 0, 'next156 current wal transient draft'],
], 156, 0x15600101, 0x15600102);
$nextWalBytes = $makeWalBytes([
    [2, 0, 'next156 next wal wp_options retry draft'],
    [4, 0, 'next156 next wal plugin retry draft'],
    [5, 6, 'next156 next wal transient retry commit'],
], 157, 0x15700101, 0x15700102);
$currentWal = SQLiteWal::parse($currentWalBytes, $pageSize, true);
$nextWal = SQLiteWal::parse($nextWalBytes, $pageSize, true);

$plan = static fn (
    int $readerEndFrame = 5,
    array $pages = [1, 2, 3, 4, 5, 6],
    bool $reservedLock = false,
    ?SQLiteWal $overrideNextWal = null,
    ?string $overrideNextWalBytes = null
): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCurrentWalSourceSwitch(
    $databasePath,
    $databaseBytes,
    $pageSize,
    'plugin-import-next156',
    $hotPages,
    $currentSourcePages,
    $currentSavepointWrites,
    $nextSavepointWrites,
    $currentWal,
    $currentWalBytes,
    $overrideNextWal ?? $nextWal,
    $overrideNextWalBytes ?? $nextWalBytes,
    $pages,
    $readerEndFrame,
    156,
    $reservedLock,
    true,
    true,
);

$tmpRoot = static function (): string {
    $root = sys_get_temp_dir() . '/port-libsqlite-next156-' . bin2hex(random_bytes(4));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException("could not create temp root: {$root}");
    }

    return $root;
};
$local = static fn (string $root, string $path): string => rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, '/');
$seedFiles = static function (string $root) use ($local, $databasePath, $journalPath, $walPath, $databaseBytes, $currentWalBytes): void {
    $dir = dirname($local($root, $databasePath));
    if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("could not create database dir: {$dir}");
    }
    file_put_contents($local($root, $databasePath), $databaseBytes);
    file_put_contents($local($root, $journalPath), 'next156-hot-journal-placeholder');
    file_put_contents($local($root, $walPath), $currentWalBytes . 'stale-tail');
};
$removeTree = static function (string $root): void {
    if (!is_dir($root)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($root);
};

$restart = static fn (): array => $plan();
$blocked = static fn (): array => $plan(5, [2, 6], true);
$applied = static function () use ($tmpRoot, $seedFiles, $removeTree, $plan): array {
    $root = $tmpRoot();
    try {
        $seedFiles($root);
        $planned = $plan();
        $writer = new SQLiteVfsFileWriter($root);
        $result = $writer->applyAtomicOperations($planned['operations'], $planned['payloads'], $planned['dependencies']);
        $result['planned'] = $planned;
        $result['root_path'] = $root;

        return $result;
    } catch (Throwable $throwable) {
        $removeTree($root);
        throw $throwable;
    }
};

$cases = [
    'status' => [static fn (): mixed => $restart()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next156'],
    'reason' => [static fn (): mixed => $restart()['reason'], 'hot_journal_savepoint_retry_database_synced_current_wal_preserved_next_wal_installed'],
    'database path' => [static fn (): mixed => $restart()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $restart()['journal_path'], $journalPath],
    'wal path' => [static fn (): mixed => $restart()['wal_path'], $walPath],
    'savepoint' => [static fn (): mixed => $restart()['savepoint'], 'plugin-import-next156'],
    'reader frame' => [static fn (): mixed => $restart()['reader_end_frame'], 5],
    'operation count' => [static fn (): mixed => $restart()['operation_count'], 10],
    'database sync count' => [static fn (): mixed => $restart()['database_sync_count'], 1],
    'wal sync count' => [static fn (): mixed => $restart()['wal_sync_count'], 2],
    'directory sync count' => [static fn (): mixed => $restart()['directory_sync_count'], 1],
    'current wal length' => [static fn (): mixed => $restart()['current_wal_bytes_length'], strlen($currentWalBytes)],
    'next wal length' => [static fn (): mixed => $restart()['next_wal_bytes_length'], strlen($nextWalBytes)],
    'current sha length' => [static fn (): mixed => strlen($restart()['current_wal_sha256']), 64],
    'next sha length' => [static fn (): mixed => strlen($restart()['next_wal_sha256']), 64],
    'next replaces current' => [static fn (): mixed => $restart()['next_replaces_current_wal'], true],
    'base status' => [static fn (): mixed => $restart()['base_status'], 'pager-savepoint-wal-hot-journal-current-source-next148'],
    'base reason' => [static fn (): mixed => $restart()['base_reason'], 'hot_journal_recovered_before_savepoint_retry_current_wal_reader_pinned_next_wal_separated'],
    'base reader retry match' => [static fn (): mixed => $restart()['base_reader_retry_match'], true],
    'base next separated' => [static fn (): mixed => $restart()['base_next_source_separated'], true],
    'separated pages' => [static fn (): mixed => $restart()['next_separated_page_numbers'], [1, 2, 3, 4, 5]],
    'payload current wal exists' => [static fn (): mixed => $restart()['payloads'][$walPath . '#current-source-next156'], $currentWalBytes],
    'payload next wal exists' => [static fn (): mixed => $restart()['payloads'][$walPath . '#next-source-next156'], $nextWalBytes],
    'operation preserves current wal' => [static fn (): mixed => in_array('preserve_current_wal_source_for_pinned_reader_before_next_savepoint_retry_next156', $restart()['operation_reasons'], true), true],
    'operation installs next wal' => [static fn (): mixed => in_array('install_next_wal_source_after_hot_journal_savepoint_retry_next156', $restart()['operation_reasons'], true), true],
    'operation persists directory' => [static fn (): mixed => in_array('persist_hot_journal_savepoint_checkpoint_current_source_sidecars_next156', $restart()['operation_reasons'], true), true],
    'base plan included' => [static fn (): mixed => is_array($restart()['base_plan']['rows']), true],
    'base row count' => [static fn (): mixed => count($restart()['base_plan']['rows']), 6],
    'base next labels' => [static fn (): mixed => $restart()['base_plan']['next_labels'], [
        'next156 dirty sqlite header after crashed import',
        'next156 next wal wp_options retry draft',
        'next156 dirty autoload index after crashed import',
        'next156 next wal plugin retry draft',
        'next156 next wal transient retry commit',
        'next156 clean cron row before crashed import',
    ]],
    'blocked status' => [static fn (): mixed => $blocked()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next156'],
    'blocked reason' => [static fn (): mixed => $blocked()['reason'], 'base_current_source_not_ready_for_vfs_apply'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next156', $restart()['dependencies'], true), true],
    'dependency atomic switch' => [static fn (): mixed => in_array('vfs-atomic-wal-source-switch', $restart()['dependencies'], true), true],
    'dependency next148' => [static fn (): mixed => in_array('sqlite-pager-savepoint-wal-hot-journal-current-source-next148', $restart()['dependencies'], true), true],
    'dependency closure' => [static fn (): mixed => str_contains($restart()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($restart()['non_overlap'], 'checkpoint transaction'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next156 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$applyCases = [
    'apply status' => [static fn (): mixed => $applied()['status'], 'applied'],
    'apply operation count' => [static fn (): mixed => $applied()['applied'], 10],
    'apply files deleted' => [static fn (): mixed => $applied()['files_deleted'], 1],
    'apply durable syncs' => [static fn (): mixed => $applied()['durable_syncs'], 3],
    'apply directory syncs' => [static fn (): mixed => $applied()['directory_syncs'], 1],
    'apply has atomic dependency' => [static fn (): mixed => in_array('vfs-atomic-rollback-on-write-failure', $applied()['dependencies'], true), true],
    'apply has next156 dependency' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next156', $applied()['dependencies'], true), true],
    'apply final wal bytes' => [static function () use ($applied, $local, $walPath, $nextWalBytes): bool {
        $result = $applied();
        $ok = file_get_contents($local($result['root_path'], $walPath)) === $nextWalBytes;
        return $ok;
    }, true],
    'apply journal deleted' => [static function () use ($applied, $local, $journalPath): bool {
        $result = $applied();
        return !file_exists($local($result['root_path'], $journalPath));
    }, true],
    'apply database recovered' => [static function () use ($applied, $local, $databasePath): bool {
        $result = $applied();
        $bytes = (string) file_get_contents($local($result['root_path'], $databasePath));
        return str_contains($bytes, 'next156 clean wp_options root before crashed import')
            && str_contains($bytes, 'next156 clean cron row before crashed import')
            && str_contains($bytes, 'next156 dirty sqlite header after crashed import');
    }, true],
];

foreach ($applyCases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next156 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'read only apply rejected' => static function () use ($tmpRoot, $seedFiles, $removeTree, $plan): void {
        $root = $tmpRoot();
        try {
            $seedFiles($root);
            $planned = $plan();
            (new SQLiteVfsFileWriter($root, true))->applyAtomicOperations($planned['operations'], $planned['payloads'], $planned['dependencies']);
        } finally {
            $removeTree($root);
        }
    },
    'missing current wal rejected' => static fn () => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::planCurrentWalSourceSwitch($databasePath, $databaseBytes, $pageSize, 's', [1 => $clean[1]], [1 => $dirty[1]], [1 => $clean[1]], [1 => $dirty[1]], $currentWal, '', $nextWal, $nextWalBytes, [1], 1),
    'empty pages rejected' => static fn () => $plan(1, []),
    'bad reader frame rejected' => static fn () => $plan(9, [1]),
    'same checkpoint rejected' => static function () use ($makeWalBytes, $pageSize, $plan): array {
        $bytes = $makeWalBytes([[2, 6, 'next156 stale wal']], 156, 0x15700101, 0x15700102);
        return $plan(1, [1], false, SQLiteWal::parse($bytes, $pageSize, true), $bytes);
    },
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next156 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
