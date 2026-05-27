<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLitePragmaSnapshot;
use PortLibs\LibSqlite\SQLiteVacuumBackupSerializePlan;

$makeDatabase = static function (int $pageSize = 512, int $pageCount = 106, string $autoVacuum = 'none'): SQLiteDatabase {
    $first = str_repeat("\0", $pageSize);
    $first = substr_replace($first, "SQLite format 3\0", 0, 16);
    $first = substr_replace($first, pack('n', $pageSize === 65536 ? 1 : $pageSize), 16, 2);
    $first[18] = "\x01";
    $first[19] = "\x01";
    $first[20] = "\x00";
    $first[21] = "\x40";
    $first[22] = "\x20";
    $first[23] = "\x20";
    $first = substr_replace($first, pack('N', 19), 24, 4);
    $first = substr_replace($first, pack('N', $pageCount), 28, 4);
    $first = substr_replace($first, pack('N', 1), 40, 4);
    $first = substr_replace($first, pack('N', 1), 56, 4);
    if ($autoVacuum !== 'none') {
        $first = substr_replace($first, pack('N', min($pageCount, 106)), 52, 4);
        $first = substr_replace($first, pack('N', $autoVacuum === 'incremental' ? 1 : 0), 64, 4);
    }

    $pages = [$first];
    for ($page = 2; $page <= $pageCount; $page++) {
        $pages[] = str_pad("wp_options-vacuum-page-{$page};", $pageSize, chr(65 + ($page % 26)));
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$target = sys_get_temp_dir() . '/port-libs-sqlite-vacuum-into-autovacuum-edge.sqlite';
@unlink($target);

$noneToIncremental = static fn (): array => SQLiteVacuumBackupSerializePlan::vacuumInto($makeDatabase(512, 106, 'none'), $target, false, 512, 'incremental');
$fullPreserved = static fn (): array => SQLiteVacuumBackupSerializePlan::vacuumInto($makeDatabase(512, 106, 'full'), $target, false);
$smallFull = static fn (): array => SQLiteVacuumBackupSerializePlan::vacuumInto($makeDatabase(1024, 4, 'none'), $target, true, 4096, 'full');
$disabled = static fn (): array => SQLiteVacuumBackupSerializePlan::vacuumInto($makeDatabase(512, 106, 'incremental'), $target, true, 512, 'none');

$tests = [];

$cases = [
    'status remains ready' => [static fn () => $noneToIncremental()['status'], 'ready'],
    'target path is preserved' => [static fn () => $noneToIncremental()['target_path'], $target],
    'target page size is rewritten' => [static fn () => $smallFull()['page_size'], 4096],
    'target page count is compacted on larger page size' => [static fn () => $smallFull()['page_count'], 1],
    'target page count preserves 512-byte edge image' => [static fn () => $noneToIncremental()['page_count'], 106],
    'source auto vacuum reports none' => [static fn () => $noneToIncremental()['source_auto_vacuum'], 'none'],
    'target auto vacuum reports incremental' => [static fn () => $noneToIncremental()['target_auto_vacuum'], 'incremental'],
    'incremental flag is materialized' => [static fn () => $noneToIncremental()['incremental_vacuum'], 1],
    'largest root page is materialized when enabling auto vacuum' => [static fn () => $noneToIncremental()['largest_root_page'], 2],
    'pointer-map pages include page two and next stride' => [static fn () => $noneToIncremental()['pointer_map_page_numbers'], [2, 105]],
    'pointer-map entry pages start after first pointer map' => [static fn () => array_slice($noneToIncremental()['pointer_map_entry_page_numbers'], 0, 3), [3, 4, 5]],
    'pointer-map entry pages skip next pointer-map page' => [static fn () => in_array(105, $noneToIncremental()['pointer_map_entry_page_numbers'], true), false],
    'pointer-map entry pages include edge page after next map' => [static fn () => in_array(106, $noneToIncremental()['pointer_map_entry_page_numbers'], true), true],
    'pointer-map entry page count excludes two pointer-map pages' => [static fn () => count($noneToIncremental()['pointer_map_entry_page_numbers']), 103],
    'rewrite operation is appended after durable operations' => [static fn () => $noneToIncremental()['operations'][3]['op'], 'vacuum_rewrite'],
    'write operation remains first for existing callers' => [static fn () => $noneToIncremental()['operations'][0]['op'], 'write'],
    'sync operation remains second for existing callers' => [static fn () => $noneToIncremental()['operations'][1]['op'], 'sync'],
    'directory sync remains third for existing callers' => [static fn () => $noneToIncremental()['operations'][2]['op'], 'sync_directory'],
    'write byte count matches rewritten image' => [static fn () => $noneToIncremental()['operations'][0]['bytes'], strlen($noneToIncremental()['bytes'])],
    'rewrite operation records pointer-map pages' => [static fn () => $noneToIncremental()['operations'][3]['pointer_map_pages'], [2, 105]],
    'rewrite operation records target mode' => [static fn () => $noneToIncremental()['operations'][3]['target_auto_vacuum'], 'incremental'],
    'rewrite operation records source mode' => [static fn () => $noneToIncremental()['operations'][3]['source_auto_vacuum'], 'none'],
    'rewrite operation records page-size edge' => [static fn () => $smallFull()['operations'][3]['target_page_size'], 4096],
    'rewrite operations include header rewrite' => [static fn () => $noneToIncremental()['vacuum_rewrite_operations'][0]['op'], 'rewrite_header'],
    'rewrite operations include image rewrite' => [static fn () => $noneToIncremental()['vacuum_rewrite_operations'][1]['op'], 'rewrite_database_image'],
    'rewrite operations omit page-size change when unchanged' => [static fn () => count($noneToIncremental()['vacuum_rewrite_operations']), 2],
    'rewrite operations include page-size change when changed' => [static fn () => $smallFull()['vacuum_rewrite_operations'][2]['op'], 'page_size_change_requires_vacuum'],
    'dependencies include vacuum into' => [static fn () => in_array('sqlite-vacuum-into', $noneToIncremental()['dependencies'], true), true],
    'dependencies include pointer-map layout' => [static fn () => in_array('sqlite-auto-vacuum-pointer-map-layout', $noneToIncremental()['dependencies'], true), true],
    'header page size parses from rewritten image' => [static fn () => SQLiteHeader::parse($smallFull()['bytes'])->pageSize, 4096],
    'header page count parses from rewritten image' => [static fn () => SQLiteHeader::parse($noneToIncremental()['bytes'])->databaseSizePages, 106],
    'header largest-root parses from rewritten image' => [static fn () => SQLiteHeader::parse($noneToIncremental()['bytes'])->largestRootBtreePage, 2],
    'header incremental flag parses from rewritten image' => [static fn () => SQLiteHeader::parse($noneToIncremental()['bytes'])->incrementalVacuum, 1],
    'pragma snapshot sees incremental mode' => [static fn () => SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes($noneToIncremental()['bytes']))->value('auto_vacuum'), 'incremental'],
    'pragma snapshot sees full mode' => [static fn () => SQLitePragmaSnapshot::fromDatabase(SQLiteDatabase::fromBytes($smallFull()['bytes']))->value('auto_vacuum'), 'full'],
    'disabling auto vacuum clears pointer-map pages' => [static fn () => $disabled()['pointer_map_page_numbers'], []],
    'disabling auto vacuum clears entry pages' => [static fn () => $disabled()['pointer_map_entry_page_numbers'], []],
    'disabling auto vacuum clears largest root' => [static fn () => $disabled()['largest_root_page'], 0],
    'preserved full mode keeps auto-vacuum target mode' => [static fn () => $fullPreserved()['target_auto_vacuum'], 'full'],
    'preserved full mode has no incremental flag' => [static fn () => $fullPreserved()['incremental_vacuum'], 0],
    'preserved full mode exposes pointer-map pages' => [static fn () => $fullPreserved()['pointer_map_page_numbers'], [2, 105]],
    'preserved full mode reports source full' => [static fn () => $fullPreserved()['source_auto_vacuum'], 'full'],
    'small full image has only first pointer-map page' => [static fn () => $smallFull()['pointer_map_page_numbers'], []],
    'small full image has no entry pages when single page' => [static fn () => $smallFull()['pointer_map_entry_page_numbers'], []],
    'image length is target page aligned' => [static fn () => strlen($noneToIncremental()['bytes']) % $noneToIncremental()['page_size'], 0],
    'image length matches target page count' => [static fn () => strlen($noneToIncremental()['bytes']), $noneToIncremental()['page_count'] * $noneToIncremental()['page_size']],
    'database parses rewritten image' => [static fn () => SQLiteDatabase::fromBytes($noneToIncremental()['bytes'])->pageCount(), 106],
    'database identifies page two as pointer map' => [static fn () => SQLiteDatabase::fromBytes($noneToIncremental()['bytes'])->isPointerMapPage(2), true],
    'database identifies next stride as pointer map' => [static fn () => SQLiteDatabase::fromBytes($noneToIncremental()['bytes'])->isPointerMapPage(105), true],
    'database identifies edge page as entry page' => [static fn () => SQLiteDatabase::fromBytes($noneToIncremental()['bytes'])->isPointerMapPage(106), false],
    'rejects bad target path before planning' => [static function () use ($makeDatabase): string {
        try {
            SQLiteVacuumBackupSerializePlan::vacuumInto($makeDatabase(), '', false, 512, 'incremental');
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    }, InvalidArgumentException::class],
    'rejects bad auto-vacuum mode through rewrite planner' => [static function () use ($makeDatabase, $target): string {
        try {
            SQLiteVacuumBackupSerializePlan::vacuumInto($makeDatabase(), $target, true, 512, 'sometimes');
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    }, InvalidArgumentException::class],
    'rejects bad page size through rewrite planner' => [static function () use ($makeDatabase, $target): string {
        try {
            SQLiteVacuumBackupSerializePlan::vacuumInto($makeDatabase(), $target, true, 3000, 'incremental');
        } catch (Throwable $exception) {
            return get_class($exception);
        }
        return 'no-exception';
    }, InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vacuum into autovacuum page edge ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
