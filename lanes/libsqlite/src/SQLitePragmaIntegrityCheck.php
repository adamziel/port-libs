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

        if (!$quick) {
            self::appendPointerMapErrors($database, $errors, $limit);
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
        if (!preg_match('/^PRAGMA\s+(?:(?:main|temp|[A-Za-z_][A-Za-z0-9_]*)\s*\.\s*)?(integrity_check|quick_check)(?:\s*(?:\(\s*(\d+)\s*\)|=\s*(\d+)))?$/i', $trimmed, $matches)) {
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
     * @param list<string> $errors
     */
    private static function appendPointerMapErrors(SQLiteDatabase $database, array &$errors, int $limit): void
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
        }
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
