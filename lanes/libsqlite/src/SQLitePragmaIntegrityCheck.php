<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePragmaIntegrityCheck
{
    /**
     * @return array{pragma:string,limit:int,rows:list<array<string, string>>,errors:list<string>}
     */
    public static function execute(string $sql, string|SQLiteDatabase $database): array
    {
        if (self::isTableScopedPragmaSql($sql)) {
            $scoped = SQLitePragmaIntegrityTableScopeYield::collect($database, $sql);

            return [
                'pragma' => $scoped['pragma'],
                'limit' => 100,
                'rows' => $scoped['rows'],
                'errors' => $scoped['errors'],
            ];
        }

        [$pragma, $limit] = self::parsePragmaSql($sql);
        $errors = self::check($database, $pragma === 'quick_check', $limit);
        $rows = $errors === []
            ? [[$pragma => 'ok']]
            : array_map(static fn (string $error): array => [$pragma => $error], $errors);

        return [
            'pragma' => $pragma,
            'limit' => $limit,
            'rows' => $rows,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<string>
     */
    public static function check(string|SQLiteDatabase $database, bool $quick = false, int $limit = 100): array
    {
        $limit = max(1, $limit);
        $errors = [];

        if (is_string($database)) {
            try {
                $database = SQLiteDatabase::fromBytes($database);
            } catch (\InvalidArgumentException $exception) {
                return [self::formatError($exception)];
            }
        }

        self::appendHeaderErrors($database, $errors, $limit);
        if (count($errors) >= $limit) {
            return array_slice($errors, 0, $limit);
        }

        self::appendFreelistErrors($database, $errors, $limit);
        if (count($errors) >= $limit) {
            return array_slice($errors, 0, $limit);
        }

        self::appendSchemaRootPageErrors($database, $errors, $limit);
        if (count($errors) >= $limit) {
            return array_slice($errors, 0, $limit);
        }

        if (!$quick) {
            $freePages = self::freelistPageNumbers($database);
            self::appendPointerMapErrors($database, $freePages, $errors, $limit);
            if (count($errors) >= $limit) {
                return array_slice($errors, 0, $limit);
            }

            self::appendBtreeErrors($database, $freePages, $errors, $limit);
        }

        return array_slice($errors, 0, $limit);
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function parsePragmaSql(string $sql): array
    {
        $trimmed = rtrim(trim($sql), ';');
        $trimmed = trim($trimmed);
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^PRAGMA\s+(?:(?:' . $identifier . ')\s*\.\s*)?(integrity_check|quick_check)(?:\s*(?:\(\s*(\d+)\s*\)|=\s*(\d+)))?$/i', $trimmed, $matches)) {
            throw new \InvalidArgumentException('Unsupported SQLite integrity PRAGMA SQL');
        }

        $limit = 100;
        if (($matches[2] ?? '') !== '') {
            $limit = (int) $matches[2];
        } elseif (($matches[3] ?? '') !== '') {
            $limit = (int) $matches[3];
        }

        return [strtolower($matches[1]), max(1, $limit)];
    }

    private static function isTableScopedPragmaSql(string $sql): bool
    {
        $trimmed = trim(rtrim(trim($sql), ';'));
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|\'(?:\'\'|[^\'])+\'|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^PRAGMA\s+(?:(?:' . $identifier . ')\s*\.\s*)?(?:integrity_check|quick_check)\s*\(\s*(?<argument>.+)\s*\)$/i', $trimmed, $matches)) {
            return false;
        }

        $argument = trim($matches['argument']);
        if (preg_match('/^\d+$/', $argument) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param list<string> $errors
     */
    private static function appendHeaderErrors(SQLiteDatabase $database, array &$errors, int $limit): void
    {
        $header = $database->header;
        $pageCount = $database->pageCount();
        self::appendIf($errors, $limit, $header->databaseSizePages !== 0 && $header->databaseSizePages !== $pageCount, "database header page count {$header->databaseSizePages} does not match file page count {$pageCount}");
        self::appendIf($errors, $limit, $header->writeVersion < 1 || $header->writeVersion > 2, "invalid schema write version {$header->writeVersion}");
        self::appendIf($errors, $limit, $header->readVersion < 1 || $header->readVersion > 2, "invalid schema read version {$header->readVersion}");
        self::appendIf($errors, $limit, !in_array($header->textEncoding, [1, 2, 3], true), "invalid text encoding {$header->textEncoding}");
        self::appendIf($errors, $limit, $header->reservedSpace >= $header->pageSize, 'reserved bytes exceed page size');
        self::appendIf($errors, $limit, $header->firstFreelistTrunkPage === 0 && $header->freelistPageCount !== 0, 'freelist page count is nonzero but first trunk page is zero');
        self::appendIf($errors, $limit, $header->firstFreelistTrunkPage !== 0 && $header->firstFreelistTrunkPage > $pageCount, "first freelist trunk page {$header->firstFreelistTrunkPage} is beyond the database image");
        self::appendIf($errors, $limit, $header->largestRootBtreePage > $pageCount, "largest root btree page {$header->largestRootBtreePage} is beyond the database image");
    }

    /**
     * @param list<string> $errors
     */
    private static function appendFreelistErrors(SQLiteDatabase $database, array &$errors, int $limit): void
    {
        $header = $database->header;
        if ($header->firstFreelistTrunkPage === 0) {
            return;
        }
        if ($header->firstFreelistTrunkPage > $database->pageCount()) {
            return;
        }

        $pageNumber = $header->firstFreelistTrunkPage;
        $seen = [];
        $visitedPages = 0;
        $aborted = false;
        while ($pageNumber !== 0 && count($errors) < $limit) {
            if (isset($seen[$pageNumber])) {
                self::append($errors, $limit, "freelist trunk chain loops at page {$pageNumber}");
                $aborted = true;
                break;
            }
            $seen[$pageNumber] = true;

            try {
                $trunk = SQLiteFreelistTrunkPage::parse($pageNumber, $database->page($pageNumber), $database->usablePageSize(), $database->pageCount());
            } catch (\InvalidArgumentException $exception) {
                self::append($errors, $limit, self::formatError($exception));
                $aborted = true;
                break;
            }

            $visitedPages += $trunk->pageCount();
            foreach ($trunk->leafPageNumbers as $leafPageNumber) {
                if (isset($seen[$leafPageNumber])) {
                    self::append($errors, $limit, "freelist page {$leafPageNumber} appears more than once");
                    $aborted = true;
                    break 2;
                }
                $seen[$leafPageNumber] = true;
            }

            $pageNumber = $trunk->nextTrunkPage ?? 0;
        }

        if (!$aborted) {
            self::appendIf($errors, $limit, $visitedPages !== $header->freelistPageCount, "freelist header count {$header->freelistPageCount} does not match reachable freelist page count {$visitedPages}");
        }
    }

    /**
     * @param array<int, true> $freePages
     * @param list<string> $errors
     */
    private static function appendPointerMapErrors(SQLiteDatabase $database, array $freePages, array &$errors, int $limit): void
    {
        if (!$database->isAutoVacuum()) {
            return;
        }

        for ($pageNumber = 2; $pageNumber <= $database->pageCount() && count($errors) < $limit; $pageNumber++) {
            if ($database->isPointerMapPage($pageNumber)) {
                continue;
            }

            try {
                $entry = $database->pointerMapEntryForPage($pageNumber);
            } catch (\InvalidArgumentException $exception) {
                self::append($errors, $limit, self::formatError($exception));
                continue;
            }

            if ($entry->type !== SQLitePointerMapEntry::FREE_PAGE && $entry->parentPageNumber > $database->pageCount()) {
                self::append($errors, $limit, "pointer-map parent page {$entry->parentPageNumber} for page {$pageNumber} is beyond the database image");
            }
            if (!in_array($entry->type, [
                SQLitePointerMapEntry::ROOT_PAGE,
                SQLitePointerMapEntry::FREE_PAGE,
                SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE,
                SQLitePointerMapEntry::OVERFLOW_PAGE,
                SQLitePointerMapEntry::BTREE_PAGE,
            ], true)) {
                self::append($errors, $limit, "pointer-map type unknown for page {$pageNumber} is not valid");
                continue;
            }
            if (($entry->type === SQLitePointerMapEntry::ROOT_PAGE || ($entry->type === SQLitePointerMapEntry::FREE_PAGE && !isset($freePages[$pageNumber]))) && $entry->parentPageNumber !== 0) {
                self::append($errors, $limit, "pointer-map parent page {$entry->parentPageNumber} for {$entry->typeName()} page {$pageNumber} does not match expected parent 0");
            }
            if (($entry->type === SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE || $entry->type === SQLitePointerMapEntry::OVERFLOW_PAGE || $entry->type === SQLitePointerMapEntry::BTREE_PAGE) && $entry->parentPageNumber === 0) {
                self::append($errors, $limit, "pointer-map parent page 0 for {$entry->typeName()} page {$pageNumber} is not valid");
            }
            if ($entry->type === SQLitePointerMapEntry::FREE_PAGE && !isset($freePages[$pageNumber]) && ord($database->page($pageNumber)[0]) === 0) {
                self::append($errors, $limit, "pointer-map marks page {$pageNumber} free but the page is not reachable from the freelist");
            }
        }

        foreach (array_keys($freePages) as $pageNumber) {
            if (count($errors) >= $limit || $pageNumber === 1 || $database->isPointerMapPage($pageNumber)) {
                continue;
            }

            try {
                $entry = $database->pointerMapEntryForPage($pageNumber);
            } catch (\InvalidArgumentException $exception) {
                self::append($errors, $limit, self::formatError($exception));
                continue;
            }

            if ($entry->type !== SQLitePointerMapEntry::FREE_PAGE) {
                self::append($errors, $limit, "freelist page {$pageNumber} pointer-map type {$entry->typeName()} does not match expected free-page");
            } elseif ($entry->parentPageNumber !== 0) {
                self::append($errors, $limit, "freelist page {$pageNumber} pointer-map parent {$entry->parentPageNumber} does not match expected parent 0");
            }
        }
    }

    /**
     * @param array<int, true> $freePages
     * @param list<string> $errors
     */
    private static function appendBtreeErrors(SQLiteDatabase $database, array $freePages, array &$errors, int $limit): void
    {
        $overflowPages = [];
        $rootPages = self::schemaRootPageNumbers($database);
        $tableRootPages = self::schemaTableRootPageNumbers($database);
        $pageCount = $database->pageCount();
        $usableSize = $database->usablePageSize();

        for ($pageNumber = 1; $pageNumber <= $pageCount && count($errors) < $limit; $pageNumber++) {
            if (isset($freePages[$pageNumber]) || isset($overflowPages[$pageNumber]) || $database->isPointerMapPage($pageNumber)) {
                continue;
            }

            $page = $database->page($pageNumber);
            $headerOffset = $pageNumber === 1 ? 100 : 0;
            $flag = ord($page[$headerOffset]);
            if (!in_array($flag, [0x02, 0x05, 0x0a, 0x0d], true)) {
                self::appendInvalidBtreePointerMapError($database, $pageNumber, $errors, $limit);
                continue;
            }

            try {
                $header = SQLiteBTreePageHeader::parsePage($page, $database->header->pageSize, $headerOffset);
                $header->freeSpaceBytes($page, $usableSize);
                $usesLegacyLargestRootFallback = count($rootPages) === 1 && $database->header->largestRootBtreePage >= $pageNumber;
                self::appendPointerMapTypeError($database, $pageNumber, isset($rootPages[$pageNumber]) || $usesLegacyLargestRootFallback ? SQLitePointerMapEntry::ROOT_PAGE : SQLitePointerMapEntry::BTREE_PAGE, 0, $errors, $limit);

                $overflowReader = static function (int $firstOverflowPage, int $byteCount) use ($database, $pageNumber, &$overflowPages, &$errors, $limit): string {
                    if ($byteCount < 0) {
                        throw new \InvalidArgumentException('SQLite overflow byte count cannot be negative');
                    }

                    $pageNumbers = SQLiteOverflowPage::pageNumbersFromDatabase($database, $firstOverflowPage, $byteCount);
                    $previousPage = $pageNumber;
                    foreach ($pageNumbers as $index => $overflowPageNumber) {
                        if (isset($overflowPages[$overflowPageNumber])) {
                            throw new \InvalidArgumentException("SQLite overflow page {$overflowPageNumber} is referenced by more than one cell");
                        }
                        $overflowPages[$overflowPageNumber] = true;
                        $expectedType = $index === 0 ? SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE : SQLitePointerMapEntry::OVERFLOW_PAGE;
                        self::appendPointerMapTypeError($database, $overflowPageNumber, $expectedType, $previousPage, $errors, $limit);
                        $previousPage = $overflowPageNumber;
                    }

                    return str_repeat("\0", $byteCount);
                };

                match ($header->pageType) {
                    'table-leaf' => self::appendTableLeafCellErrors(
                        $database,
                        $pageNumber,
                        SQLiteTableLeafCell::parsePageCells($page, $header, $usableSize, $overflowReader),
                        $errors,
                        $limit,
                        $pageNumber === 1 || isset($tableRootPages[$pageNumber]),
                    ),
                    'index-leaf' => self::appendIndexCellOrderErrors($database, $pageNumber, SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader), $errors, $limit),
                    'index-interior' => self::appendIndexInteriorChildErrors($database, $pageNumber, $page, $header, $usableSize, $overflowReader, $errors, $limit),
                    'table-interior' => self::appendTableInteriorChildErrors($database, $pageNumber, $page, $header, $errors, $limit),
                    default => null,
                };
            } catch (\InvalidArgumentException $exception) {
                self::append($errors, $limit, "btree page {$pageNumber}: " . self::formatError($exception));
            }
        }
    }

    /**
     * @param list<SQLiteTableLeafCell> $cells
     * @param list<string> $errors
     */
    private static function appendTableLeafCellErrors(
        SQLiteDatabase $database,
        int $pageNumber,
        array $cells,
        array &$errors,
        int $limit,
        bool $validatePayloadRecords,
    ): void {
        self::appendTableLeafOrderErrors($pageNumber, $cells, $errors, $limit);
        if (!$validatePayloadRecords || count($errors) >= $limit) {
            return;
        }

        foreach ($cells as $cell) {
            try {
                SQLiteRecord::parse($cell->payload, $database->header->textEncoding);
            } catch (\InvalidArgumentException) {
                self::append($errors, $limit, 'database disk image is malformed');

                return;
            }
        }
    }

    /**
     * @param list<SQLiteTableLeafCell> $cells
     * @param list<string> $errors
     */
    private static function appendTableLeafOrderErrors(int $pageNumber, array $cells, array &$errors, int $limit): void
    {
        $previous = null;
        foreach ($cells as $cell) {
            if ($previous !== null && $cell->rowId <= $previous) {
                self::append($errors, $limit, "btree page {$pageNumber}: table leaf rowid {$cell->rowId} is not greater than previous rowid {$previous}");
                return;
            }
            $previous = $cell->rowId;
        }
    }

    /**
     * @param list<SQLiteIndexCell> $cells
     * @param list<string> $errors
     */
    private static function appendIndexCellOrderErrors(SQLiteDatabase $database, int $pageNumber, array $cells, array &$errors, int $limit): void
    {
        if (count($cells) < 2) {
            return;
        }

        $previous = null;
        foreach ($cells as $cell) {
            $record = $cell->record($database->header->textEncoding)->values;
            if ($previous !== null && SQLiteVdbeSortCompare::compareRecords($previous, $record) >= 0) {
                self::append($errors, $limit, "btree page {$pageNumber}: index record is not greater than previous record");
                return;
            }
            $previous = $record;
        }
    }

    /**
     * @param callable(int, int): string $overflowReader
     * @param list<string> $errors
     */
    private static function appendIndexInteriorChildErrors(
        SQLiteDatabase $database,
        int $pageNumber,
        string $page,
        SQLiteBTreePageHeader $header,
        int $usableSize,
        callable $overflowReader,
        array &$errors,
        int $limit,
    ): void {
        $cells = SQLiteIndexCell::parsePageCells($page, $header, $usableSize, $overflowReader);
        self::appendIndexCellOrderErrors($database, $pageNumber, $cells, $errors, $limit);
        foreach ($cells as $cell) {
            if ($cell->leftChildPage !== null) {
                self::appendInteriorChildPointerMapError($database, $pageNumber, $cell->leftChildPage, 'left child', $errors, $limit);
            }
        }

        if ($header->rightMostPointer !== null) {
            self::appendInteriorChildPointerMapError($database, $pageNumber, $header->rightMostPointer, 'right-most child', $errors, $limit);
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function appendTableInteriorChildErrors(
        SQLiteDatabase $database,
        int $pageNumber,
        string $page,
        SQLiteBTreePageHeader $header,
        array &$errors,
        int $limit,
    ): void {
        $previous = null;
        foreach (SQLiteTableInteriorCell::parsePageCells($page, $header) as $cell) {
            if ($previous !== null && $cell->key <= $previous) {
                self::append($errors, $limit, "btree page {$pageNumber}: table interior divider key {$cell->key} is not greater than previous divider key {$previous}");
                return;
            }
            $previous = $cell->key;
            self::appendInteriorChildPointerMapError($database, $pageNumber, $cell->leftChildPage, 'left child', $errors, $limit);
        }

        if ($header->rightMostPointer !== null) {
            self::appendInteriorChildPointerMapError($database, $pageNumber, $header->rightMostPointer, 'right-most child', $errors, $limit);
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function appendInteriorChildPointerMapError(
        SQLiteDatabase $database,
        int $parentPageNumber,
        int $childPageNumber,
        string $role,
        array &$errors,
        int $limit,
    ): void {
        if (!$database->isAutoVacuum() || $childPageNumber === 1 || $database->isPointerMapPage($childPageNumber)) {
            return;
        }
        if ($childPageNumber > $database->pageCount()) {
            self::append($errors, $limit, "btree page {$parentPageNumber} {$role} page {$childPageNumber} is beyond the database image");
            return;
        }

        try {
            $entry = $database->pointerMapEntryForPage($childPageNumber);
        } catch (\InvalidArgumentException $exception) {
            self::append($errors, $limit, self::formatError($exception));
            return;
        }

        if ($entry->type !== SQLitePointerMapEntry::BTREE_PAGE) {
            self::append($errors, $limit, "btree page {$parentPageNumber} {$role} page {$childPageNumber} pointer-map type {$entry->typeName()} does not match expected btree-page");
            return;
        }
        if ($entry->parentPageNumber !== $parentPageNumber) {
            self::append($errors, $limit, "btree page {$parentPageNumber} {$role} page {$childPageNumber} pointer-map parent {$entry->parentPageNumber} does not match expected parent {$parentPageNumber}");
        }
    }

    /**
     * @return array<int, true>
     */
    private static function schemaRootPageNumbers(SQLiteDatabase $database): array
    {
        $rootPages = [1 => true];
        try {
            $records = $database->schemaRecords();
            $records = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => in_array($record->type, ['table', 'index', 'view', 'trigger'], true)));
            if ($records === [] && $database->isAutoVacuum() && $database->header->largestRootBtreePage > 0) {
                for ($pageNumber = 2; $pageNumber <= min($database->header->largestRootBtreePage, $database->pageCount()); $pageNumber++) {
                    if (!$database->isPointerMapPage($pageNumber)) {
                        $rootPages[$pageNumber] = true;
                    }
                }

                return $rootPages;
            }
            foreach ($records as $record) {
                if (($record->type === 'table' || $record->type === 'index') && $record->rootPage !== null && $record->rootPage > 0 && $record->rootPage <= $database->pageCount()) {
                    $rootPages[$record->rootPage] = true;
                }
            }
        } catch (\InvalidArgumentException) {
        }

        return $rootPages;
    }

    /**
     * @return array<int, true>
     */
    private static function schemaTableRootPageNumbers(SQLiteDatabase $database): array
    {
        $rootPages = [];
        try {
            foreach ($database->schemaRecords() as $record) {
                if ($record->type === 'table' && $record->rootPage !== null && $record->rootPage > 0 && $record->rootPage <= $database->pageCount()) {
                    $rootPages[$record->rootPage] = true;
                }
            }
        } catch (\InvalidArgumentException) {
        }

        return $rootPages;
    }

    /**
     * @return array<int, true>
     */
    private static function freelistPageNumbers(SQLiteDatabase $database): array
    {
        $pages = [];
        $pageNumber = $database->header->firstFreelistTrunkPage;
        $seen = [];
        while ($pageNumber !== 0 && $pageNumber <= $database->pageCount() && !isset($seen[$pageNumber])) {
            $seen[$pageNumber] = true;
            try {
                $trunk = SQLiteFreelistTrunkPage::parse($pageNumber, $database->page($pageNumber), $database->usablePageSize(), $database->pageCount());
            } catch (\InvalidArgumentException) {
                break;
            }

            $pages[$pageNumber] = true;
            foreach ($trunk->leafPageNumbers as $leafPageNumber) {
                $pages[$leafPageNumber] = true;
            }
            $pageNumber = $trunk->nextTrunkPage ?? 0;
        }

        return $pages;
    }

    /**
     * @param list<string> $errors
     */
    private static function appendPointerMapTypeError(SQLiteDatabase $database, int $pageNumber, int $expectedType, int $expectedParent, array &$errors, int $limit): void
    {
        if (!$database->isAutoVacuum() || $pageNumber === 1 || $database->isPointerMapPage($pageNumber)) {
            return;
        }

        try {
            $entry = $database->pointerMapEntryForPage($pageNumber);
        } catch (\InvalidArgumentException $exception) {
            self::append($errors, $limit, self::formatError($exception));
            return;
        }

        if ($entry->type !== $expectedType) {
            self::append($errors, $limit, "pointer-map type {$entry->typeName()} for page {$pageNumber} does not match expected " . self::pointerMapTypeName($expectedType));
            return;
        }
        if ($expectedParent !== 0 && $entry->parentPageNumber !== $expectedParent) {
            self::append($errors, $limit, "pointer-map parent page {$entry->parentPageNumber} for page {$pageNumber} does not match expected parent {$expectedParent}");
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function appendSchemaRootPageErrors(SQLiteDatabase $database, array &$errors, int $limit): void
    {
        try {
            $records = $database->schemaRecords();
        } catch (\InvalidArgumentException) {
            return;
        }
        $records = array_values(array_filter($records, static fn (SQLiteSchemaRecord $record): bool => in_array($record->type, ['table', 'index', 'view', 'trigger'], true)));
        if ($records === []) {
            return;
        }

        $pageCount = $database->pageCount();
        $freePages = self::freelistPageNumbers($database);
        $rootPages = [1 => true];
        $maxRootPage = 1;

        foreach ($records as $record) {
            if ($record->rootPage === null || $record->rootPage === 0) {
                continue;
            }

            if ($record->rootPage < 0) {
                self::append($errors, $limit, "sqlite_schema {$record->type} {$record->name} rootpage {$record->rootPage} is negative");
                continue;
            }

            if ($record->rootPage > $pageCount) {
                self::append($errors, $limit, "sqlite_schema {$record->type} {$record->name} rootpage {$record->rootPage} is beyond the database image");
                continue;
            }

            if (isset($freePages[$record->rootPage])) {
                self::append($errors, $limit, "sqlite_schema {$record->type} {$record->name} rootpage {$record->rootPage} is on the freelist");
                continue;
            }

            $rootPages[$record->rootPage] = true;
            $maxRootPage = max($maxRootPage, $record->rootPage);
        }

        if ($database->isAutoVacuum() && $database->header->largestRootBtreePage !== 0 && $database->header->largestRootBtreePage !== $maxRootPage) {
            self::append($errors, $limit, "largest root btree page {$database->header->largestRootBtreePage} does not match sqlite_schema max rootpage {$maxRootPage}");
        }

        foreach ($rootPages as $rootPage => $_) {
            self::appendPointerMapTypeError($database, (int) $rootPage, SQLitePointerMapEntry::ROOT_PAGE, 0, $errors, $limit);
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function appendInvalidBtreePointerMapError(SQLiteDatabase $database, int $pageNumber, array &$errors, int $limit): void
    {
        if (!$database->isAutoVacuum() || $pageNumber === 1 || $database->isPointerMapPage($pageNumber)) {
            return;
        }

        try {
            $entry = $database->pointerMapEntryForPage($pageNumber);
        } catch (\InvalidArgumentException) {
            return;
        }

        if (($entry->type === SQLitePointerMapEntry::ROOT_PAGE || $entry->type === SQLitePointerMapEntry::BTREE_PAGE) && ord($database->page($pageNumber)[0]) !== 0) {
            self::append($errors, $limit, sprintf('btree page %d: Invalid SQLite b-tree page type flag: 0x%02x', $pageNumber, ord($database->page($pageNumber)[0])));
        }
    }

    private static function pointerMapTypeName(int $type): string
    {
        return match ($type) {
            SQLitePointerMapEntry::ROOT_PAGE => 'root-page',
            SQLitePointerMapEntry::FREE_PAGE => 'free-page',
            SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE => 'first-overflow-page',
            SQLitePointerMapEntry::OVERFLOW_PAGE => 'overflow-page',
            SQLitePointerMapEntry::BTREE_PAGE => 'btree-page',
            default => 'unknown',
        };
    }

    /**
     * @param list<string> $errors
     */
    private static function appendIf(array &$errors, int $limit, bool $condition, string $message): void
    {
        if ($condition) {
            self::append($errors, $limit, $message);
        }
    }

    /**
     * @param list<string> $errors
     */
    private static function append(array &$errors, int $limit, string $message): void
    {
        if (count($errors) < $limit) {
            $errors[] = $message;
        }
    }

    private static function formatError(\InvalidArgumentException $exception): string
    {
        return $exception->getMessage();
    }
}
