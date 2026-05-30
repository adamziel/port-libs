<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteVacuumBackupSerializePlan;

$makePage = static function (int $pageSize = 512, int $pageCount = 3, int $freelistCount = 0): string {
    $page = str_repeat("\0", $pageSize);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', $pageSize === 65536 ? 1 : $pageSize), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[20] = "\x00";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', 7), 24, 4);
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', $freelistCount > 0 ? $pageCount : 0), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 1), 40, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$makeDatabaseBytes = static function (int $pageSize = 512, int $pageCount = 3, int $freelistCount = 0) use ($makePage): string {
    $pages = [$makePage($pageSize, $pageCount, $freelistCount)];
    for ($page = 2; $page <= $pageCount; $page++) {
        $pages[] = str_pad("page:{$page};application-options", $pageSize, chr(64 + $page));
    }

    return implode('', $pages);
};

$database = SQLiteDatabase::fromBytes($makeDatabaseBytes());
$larger = SQLiteDatabase::fromBytes($makeDatabaseBytes(1024, 5, 2));
$tests = [];

$serializeCases = [
    'main schema status' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($database)['status'],
    'main schema name' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($database)['schema'],
    'main image byte length' => static fn () => strlen(SQLiteVacuumBackupSerializePlan::serialize($database)['bytes']),
    'main page size' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($database)['page_size'],
    'main page count' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($database)['page_count'],
    'main header page count' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($database)['database_size_pages'],
    'temp schema name' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($database, 'temp')['schema'],
    'attached schema name' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($database, 'wp_copy')['schema'],
    'preserves first page magic' => static fn () => substr(SQLiteVacuumBackupSerializePlan::serialize($database)['bytes'], 0, 16),
    'preserves later page bytes' => static fn () => substr(SQLiteVacuumBackupSerializePlan::serialize($database)['bytes'], 512, strlen('page:2;application-options')),
    'large page byte length' => static fn () => strlen(SQLiteVacuumBackupSerializePlan::serialize($larger)['bytes']),
    'large page count' => static fn () => SQLiteVacuumBackupSerializePlan::serialize($larger)['page_count'],
    'dependencies include serialize' => static fn () => in_array('sqlite3-serialize', SQLiteVacuumBackupSerializePlan::serialize($database)['dependencies'], true),
];

$serializeExpected = [
    'ok',
    'main',
    1536,
    512,
    3,
    3,
    'temp',
    'wp_copy',
    "SQLite format 3\0",
    'page:2;application-options',
    5120,
    5,
    true,
];

foreach (array_values($serializeCases) as $index => $callback) {
    $name = array_keys($serializeCases)[$index];
    $tests['vacuum backup serialize corpus serialize ' . $name] = static function (TestRunner $t) use ($callback, $serializeExpected, $index): void {
        $t->same($serializeExpected[$index], $callback());
    };
}

$deserializeBytes = $makeDatabaseBytes(512, 4);
$deserializeCases = [
    'status' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes)['status'],
    'schema' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes, 'main')['schema'],
    'readonly flag' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes, 'main', true)['readonly'],
    'page size' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes)['page_size'],
    'page count' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes)['page_count'],
    'round trip bytes' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes)['bytes'] === $deserializeBytes,
    'database object page count' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes)['database']->pageCount(),
    'attached schema' => static fn () => SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes, 'archive_copy')['schema'],
    'dependencies include deserialize' => static fn () => in_array('sqlite3-deserialize', SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes)['dependencies'], true),
    'rejects short header image' => static function () use ($deserializeBytes): string {
        try {
            SQLiteVacuumBackupSerializePlan::deserialize(substr($deserializeBytes, 0, 200));
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    },
    'rejects malformed schema' => static function () use ($deserializeBytes): string {
        try {
            SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes, 'main;drop');
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    },
    'rejects empty schema' => static function () use ($deserializeBytes): string {
        try {
            SQLiteVacuumBackupSerializePlan::deserialize($deserializeBytes, '');
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    },
];

$deserializeExpected = ['ok', 'main', true, 512, 4, true, 4, 'archive_copy', true, InvalidArgumentException::class, InvalidArgumentException::class, InvalidArgumentException::class];
foreach (array_values($deserializeCases) as $index => $callback) {
    $name = array_keys($deserializeCases)[$index];
    $tests['vacuum backup serialize corpus deserialize ' . $name] = static function (TestRunner $t) use ($callback, $deserializeExpected, $index): void {
        $t->same($deserializeExpected[$index], $callback());
    };
}

$backupCases = [
    'all status' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['status'],
    'all done flag' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['done'],
    'all page count' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['page_count'],
    'all copied steps' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['steps'],
    'all remaining' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['remaining'],
    'all bytes length' => static fn () => strlen(SQLiteVacuumBackupSerializePlan::backup($database)['bytes']),
    'first page number' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['pages'][0]['page'],
    'second page offset' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['pages'][1]['offset'],
    'third page bytes' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database)['pages'][2]['bytes'],
    'single step status' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database, 'main', 'main', 1)['status'],
    'single step remaining' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database, 'main', 'main', 1)['remaining'],
    'two step byte length' => static fn () => strlen(SQLiteVacuumBackupSerializePlan::backup($database, 'main', 'main', 2)['bytes']),
    'target schema' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database, 'copy')['target_schema'],
    'source schema' => static fn () => SQLiteVacuumBackupSerializePlan::backup($database, 'copy', 'main')['source_schema'],
    'larger backup remaining' => static fn () => SQLiteVacuumBackupSerializePlan::backup($larger, 'main', 'main', 3)['remaining'],
    'larger backup first bytes' => static fn () => SQLiteVacuumBackupSerializePlan::backup($larger, 'main', 'main', 1)['pages'][0]['bytes'],
    'dependencies include backup' => static fn () => in_array('sqlite3-backup-api', SQLiteVacuumBackupSerializePlan::backup($database)['dependencies'], true),
    'rejects zero step' => static function () use ($database): string {
        try {
            SQLiteVacuumBackupSerializePlan::backup($database, 'main', 'main', 0);
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    },
    'rejects bad target schema' => static function () use ($database): string {
        try {
            SQLiteVacuumBackupSerializePlan::backup($database, 'bad-name');
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    },
];

$backupExpected = ['done', true, 3, 3, 0, 1536, 1, 512, 512, 'in_progress', 2, 1024, 'copy', 'main', 2, 1024, true, InvalidArgumentException::class, InvalidArgumentException::class];
foreach (array_values($backupCases) as $index => $callback) {
    $name = array_keys($backupCases)[$index];
    $tests['vacuum backup serialize corpus backup ' . $name] = static function (TestRunner $t) use ($callback, $backupExpected, $index): void {
        $t->same($backupExpected[$index], $callback());
    };
}

$vacuumTarget = sys_get_temp_dir() . '/port-libs-sqlite-vacuum-copy.sqlite';
@unlink($vacuumTarget);
$vacuumCases = [
    'status' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['status'],
    'target path' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['target_path'],
    'page size' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['page_size'],
    'page count' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['page_count'],
    'source freelist count' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['source_freelist_pages'],
    'byte length' => static fn () => strlen(SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['bytes']),
    'first operation' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['operations'][0]['op'],
    'second operation' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['operations'][1]['op'],
    'directory sync operation' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['operations'][2]['op'],
    'write operation bytes' => static fn () => SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['operations'][0]['bytes'],
    'dependencies include vacuum' => static fn () => in_array('sqlite-vacuum-into', SQLiteVacuumBackupSerializePlan::vacuumInto($larger, $vacuumTarget)['dependencies'], true),
];

$vacuumExpected = ['ready', $vacuumTarget, 1024, 5, 2, 5120, 'write', 'sync', 'sync_directory', 5120, true];
foreach (array_values($vacuumCases) as $index => $callback) {
    $name = array_keys($vacuumCases)[$index];
    $tests['vacuum backup serialize corpus vacuum into ' . $name] = static function (TestRunner $t) use ($callback, $vacuumExpected, $index): void {
        $t->same($vacuumExpected[$index], $callback());
    };
}

return $tests;
