<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVacuumPageSizeAutoVacuumPlan
{
    private const VALID_PAGE_SIZES = [512, 1024, 2048, 4096, 8192, 16384, 32768, 65536];

    /**
     * @return array{
     *     status:string,
     *     source_page_size:int,
     *     target_page_size:int,
     *     source_page_count:int,
     *     target_page_count:int,
     *     source_auto_vacuum:string,
     *     target_auto_vacuum:string,
     *     incremental_vacuum:int,
     *     largest_root_page:int,
     *     bytes:string,
     *     operations:list<array<string,mixed>>,
     *     dependencies:list<string>
     * }
     */
    public static function plan(SQLiteDatabase $source, int|string|null $pageSize = null, int|string|null $autoVacuum = null): array
    {
        $targetPageSize = $pageSize === null ? $source->header->pageSize : self::normalizePageSize($pageSize);
        [$targetAutoVacuum, $incrementalVacuum] = $autoVacuum === null
            ? [self::autoVacuumMode($source), $source->isIncrementalVacuum() ? 1 : 0]
            : self::normalizeAutoVacuum($autoVacuum);

        $content = self::databaseBytes($source);
        $targetPageCount = max(1, (int) ceil(strlen($content) / $targetPageSize));
        $bytes = str_pad($content, $targetPageCount * $targetPageSize, "\0");
        $bytes = substr_replace($bytes, "SQLite format 3\0", 0, 16);
        $bytes = substr_replace($bytes, pack('n', $targetPageSize === 65536 ? 1 : $targetPageSize), 16, 2);
        $bytes = substr_replace($bytes, pack('N', $targetPageCount), 28, 4);

        $largestRootPage = $targetAutoVacuum === 'none' ? 0 : max(2, min($targetPageCount, max(2, $source->header->largestRootBtreePage)));
        $bytes = substr_replace($bytes, pack('N', $largestRootPage), 52, 4);
        $bytes = substr_replace($bytes, pack('N', $incrementalVacuum), 64, 4);

        $operations = [
            [
                'op' => 'rewrite_header',
                'page_size' => $targetPageSize,
                'auto_vacuum' => $targetAutoVacuum,
                'incremental_vacuum' => $incrementalVacuum,
                'largest_root_page' => $largestRootPage,
            ],
            [
                'op' => 'rewrite_database_image',
                'source_pages' => $source->pageCount(),
                'target_pages' => $targetPageCount,
                'bytes' => strlen($bytes),
            ],
        ];
        if ($targetPageSize !== $source->header->pageSize) {
            $operations[] = [
                'op' => 'page_size_change_requires_vacuum',
                'from' => $source->header->pageSize,
                'to' => $targetPageSize,
            ];
        }

        return [
            'status' => 'ready',
            'source_page_size' => $source->header->pageSize,
            'target_page_size' => $targetPageSize,
            'source_page_count' => $source->pageCount(),
            'target_page_count' => $targetPageCount,
            'source_auto_vacuum' => self::autoVacuumMode($source),
            'target_auto_vacuum' => $targetAutoVacuum,
            'incremental_vacuum' => $incrementalVacuum,
            'largest_root_page' => $largestRootPage,
            'bytes' => $bytes,
            'operations' => $operations,
            'dependencies' => ['sqlite-vacuum', 'sqlite-page-size', 'sqlite-auto-vacuum-header'],
        ];
    }

    private static function normalizePageSize(int|string $pageSize): int
    {
        if (is_string($pageSize)) {
            $pageSize = trim($pageSize);
            if ($pageSize === '') {
                throw new \InvalidArgumentException('SQLite page_size must not be empty');
            }
            $pageSize = (int) $pageSize;
        }
        if (!in_array($pageSize, self::VALID_PAGE_SIZES, true)) {
            throw new \InvalidArgumentException('SQLite page_size must be a power of two between 512 and 65536');
        }

        return $pageSize;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function normalizeAutoVacuum(int|string $autoVacuum): array
    {
        $mode = is_string($autoVacuum) ? strtolower(str_replace('-', '_', trim($autoVacuum))) : $autoVacuum;

        return match ($mode) {
            0, '0', 'none', 'off', 'false' => ['none', 0],
            1, '1', 'full', 'on', 'true' => ['full', 0],
            2, '2', 'incremental' => ['incremental', 1],
            default => throw new \InvalidArgumentException('SQLite auto_vacuum must be none, full, or incremental'),
        };
    }

    private static function autoVacuumMode(SQLiteDatabase $database): string
    {
        if (!$database->isAutoVacuum()) {
            return 'none';
        }

        return $database->isIncrementalVacuum() ? 'incremental' : 'full';
    }

    private static function databaseBytes(SQLiteDatabase $database): string
    {
        $pages = [];
        for ($pageNumber = 1; $pageNumber <= $database->pageCount(); $pageNumber++) {
            $pages[] = $database->page($pageNumber);
        }

        return implode('', $pages);
    }
}
