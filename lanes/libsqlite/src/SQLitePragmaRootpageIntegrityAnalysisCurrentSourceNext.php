<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaRootpageIntegrityAnalysisCurrentSourceNext
{
    /**
     * @return array{
     *   status:string,
     *   source:string,
     *   page_count:int,
     *   largest_root_btree_page:int,
     *   max_schema_rootpage:int,
     *   auto_vacuum:bool,
     *   ok_count:int,
     *   problem_count:int,
     *   current:array{schema_records:int,root_records:int,duplicate_rootpages:int,freelist_conflicts:int,pointer_map_conflicts:int,largest_root_mismatch:int},
     *   next:array{ready:bool,blocking:list<string>},
     *   rows:list<array<string,mixed>>
     * }
     */
    public static function analyze(string|SQLiteDatabase $database): array
    {
        if (is_string($database)) {
            $database = SQLiteDatabase::fromBytes($database);
        }

        $records = array_values(array_filter(
            $database->schemaRecords(),
            static fn (SQLiteSchemaRecord $record): bool => in_array($record->type, ['table', 'index', 'view', 'trigger'], true),
        ));
        $freelist = self::freelistPageNumbers($database);
        $byRootPage = [];
        $rows = [];
        $rootRecords = 0;
        $maxRootPage = 1;

        foreach ($records as $record) {
            $rootPage = $record->rootPage;
            if ($rootPage !== null && $rootPage > 0) {
                $rootRecords++;
                $maxRootPage = max($maxRootPage, $rootPage);
                $byRootPage[$rootPage] ??= [];
                $byRootPage[$rootPage][] = $record;
            }
        }

        foreach ($records as $record) {
            $rows[] = self::recordRow($database, $record, $byRootPage, $freelist);
        }

        if ($database->isAutoVacuum() && $database->header->largestRootBtreePage !== 0 && $database->header->largestRootBtreePage !== $maxRootPage) {
            $rows[] = [
                'kind' => 'largest_root_mismatch',
                'type' => 'header',
                'name' => 'database-header',
                'table' => null,
                'rootpage' => $database->header->largestRootBtreePage,
                'rowid' => null,
                'status' => 'error',
                'page_status' => 'header_mismatch',
                'page_type' => null,
                'page_flag' => null,
                'pointer_map_type' => null,
                'pointer_map_parent' => null,
                'pointer_map_page' => null,
                'duplicate_names' => [],
                'message' => "largest root btree page {$database->header->largestRootBtreePage} does not match sqlite_schema max rootpage {$maxRootPage}",
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return [
                $left['rootpage'] ?? PHP_INT_MAX,
                $left['rowid'] ?? PHP_INT_MAX,
                $left['type'],
                $left['name'],
            ] <=> [
                $right['rootpage'] ?? PHP_INT_MAX,
                $right['rowid'] ?? PHP_INT_MAX,
                $right['type'],
                $right['name'],
            ];
        });

        $problemRows = array_values(array_filter($rows, static fn (array $row): bool => $row['status'] !== 'ok' && $row['status'] !== 'ignored'));
        $blocking = array_values(array_map(static fn (array $row): string => $row['message'], $problemRows));

        return [
            'status' => $problemRows === [] ? 'ok' : 'error',
            'source' => 'pragma-rootpage-integrity-analysis-current-source-next111',
            'page_count' => $database->pageCount(),
            'largest_root_btree_page' => $database->header->largestRootBtreePage,
            'max_schema_rootpage' => $maxRootPage,
            'auto_vacuum' => $database->isAutoVacuum(),
            'ok_count' => count($rows) - count($problemRows),
            'problem_count' => count($problemRows),
            'current' => [
                'schema_records' => count($records),
                'root_records' => $rootRecords,
                'duplicate_rootpages' => count(array_filter($byRootPage, static fn (array $items): bool => count($items) > 1)),
                'freelist_conflicts' => count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'freelist_conflict')),
                'pointer_map_conflicts' => count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'pointer_map_conflict')),
                'largest_root_mismatch' => count(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'largest_root_mismatch')),
            ],
            'next' => [
                'ready' => $problemRows === [],
                'blocking' => $blocking,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param array<int,list<SQLiteSchemaRecord>> $byRootPage
     * @param array<int,true> $freelist
     * @return array<string,mixed>
     */
    private static function recordRow(SQLiteDatabase $database, SQLiteSchemaRecord $record, array $byRootPage, array $freelist): array
    {
        $rootPage = $record->rootPage;
        $row = [
            'kind' => 'schema_rootpage',
            'type' => $record->type,
            'name' => $record->name,
            'table' => $record->tableName,
            'rootpage' => $rootPage,
            'rowid' => $record->rowId,
            'status' => 'ok',
            'page_status' => 'ok',
            'page_type' => null,
            'page_flag' => null,
            'pointer_map_type' => null,
            'pointer_map_parent' => null,
            'pointer_map_page' => null,
            'duplicate_names' => [],
            'message' => "sqlite_schema {$record->type} {$record->name} rootpage " . ($rootPage ?? 'NULL') . ' ok',
        ];

        if ($rootPage === null || $rootPage === 0) {
            $row['status'] = in_array($record->type, ['view', 'trigger'], true) ? 'ignored' : 'error';
            $row['page_status'] = 'no_rootpage';
            $row['message'] = in_array($record->type, ['view', 'trigger'], true)
                ? "sqlite_schema {$record->type} {$record->name} rootpage 0 ignored"
                : "sqlite_schema {$record->type} {$record->name} rootpage is empty";

            return $row;
        }

        if ($rootPage < 0) {
            $row['status'] = 'error';
            $row['page_status'] = 'negative';
            $row['message'] = "sqlite_schema {$record->type} {$record->name} rootpage {$rootPage} is negative";

            return $row;
        }

        if ($rootPage > $database->pageCount()) {
            $row['status'] = 'error';
            $row['page_status'] = 'beyond_image';
            $row['message'] = "sqlite_schema {$record->type} {$record->name} rootpage {$rootPage} is beyond the database image";

            return $row;
        }

        if (isset($freelist[$rootPage])) {
            $row['kind'] = 'freelist_conflict';
            $row['status'] = 'error';
            $row['page_status'] = 'freelist';
            $row['message'] = "sqlite_schema {$record->type} {$record->name} rootpage {$rootPage} is on the freelist";

            return $row;
        }

        $duplicates = array_values(array_filter(
            $byRootPage[$rootPage] ?? [],
            static fn (SQLiteSchemaRecord $candidate): bool => $candidate->rowId !== $record->rowId,
        ));
        if ($duplicates !== []) {
            $row['kind'] = 'duplicate_rootpage';
            $row['status'] = 'error';
            $row['page_status'] = 'duplicate';
            $row['duplicate_names'] = array_map(static fn (SQLiteSchemaRecord $candidate): string => "{$candidate->type}:{$candidate->name}", $duplicates);
            $row['message'] = "sqlite_schema {$record->type} {$record->name} rootpage {$rootPage} is also used by " . implode(', ', $row['duplicate_names']);
        }

        $page = $database->page($rootPage);
        $headerOffset = $rootPage === 1 ? 100 : 0;
        $flag = ord($page[$headerOffset]);
        $row['page_flag'] = $flag;
        $row['page_type'] = self::pageTypeName($flag);
        $expectedPageType = $record->type === 'index' ? 'index' : 'table';
        if ($row['status'] === 'ok' && (($record->type === 'index' && !in_array($flag, [0x02, 0x0a], true)) || ($record->type === 'table' && !in_array($flag, [0x05, 0x0d], true)))) {
            $row['status'] = 'error';
            $row['page_status'] = 'wrong_btree_type';
            $row['message'] = "sqlite_schema {$record->type} {$record->name} rootpage {$rootPage} points at {$row['page_type']} page, expected {$expectedPageType} b-tree";
        }

        if ($database->isAutoVacuum() && $rootPage !== 1 && !$database->isPointerMapPage($rootPage)) {
            try {
                $entry = $database->pointerMapEntryForPage($rootPage);
                $row['pointer_map_type'] = $entry->typeName();
                $row['pointer_map_parent'] = $entry->parentPageNumber;
                $row['pointer_map_page'] = $entry->pointerMapPageNumber;
                if ($entry->type !== SQLitePointerMapEntry::ROOT_PAGE || $entry->parentPageNumber !== 0) {
                    $row['kind'] = 'pointer_map_conflict';
                    $row['status'] = 'error';
                    $row['page_status'] = 'pointer_map';
                    $row['message'] = "sqlite_schema {$record->type} {$record->name} rootpage {$rootPage} pointer-map {$entry->typeName()} parent {$entry->parentPageNumber} does not match expected root-page parent 0";
                }
            } catch (InvalidArgumentException $exception) {
                $row['kind'] = 'pointer_map_conflict';
                $row['status'] = 'error';
                $row['page_status'] = 'pointer_map';
                $row['message'] = $exception->getMessage();
            }
        }

        return $row;
    }

    /**
     * @return array<int,true>
     */
    private static function freelistPageNumbers(SQLiteDatabase $database): array
    {
        $first = $database->header->firstFreelistTrunkPage;
        if ($first === 0 || $first > $database->pageCount()) {
            return [];
        }

        $pages = [];
        $pageNumber = $first;
        while ($pageNumber !== 0 && !isset($pages[$pageNumber])) {
            $trunk = SQLiteFreelistTrunkPage::parse($pageNumber, $database->page($pageNumber), $database->usablePageSize(), $database->pageCount());
            $pages[$pageNumber] = true;
            foreach ($trunk->leafPageNumbers as $leafPageNumber) {
                $pages[$leafPageNumber] = true;
            }
            $pageNumber = $trunk->nextTrunkPage ?? 0;
        }

        return $pages;
    }

    private static function pageTypeName(int $flag): string
    {
        return match ($flag) {
            0x02 => 'index-interior',
            0x05 => 'table-interior',
            0x0a => 'index-leaf',
            0x0d => 'table-leaf',
            0x00 => 'zero',
            default => sprintf('unknown-0x%02x', $flag),
        };
    }
}
