<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCacheSavepointFileHandlePlan
{
    /**
     * @param array<int,string> $currentPages
     * @param array<int,string> $nextPages
     * @return array{status:string,path:string,page_size:int,savepoint:string,current:array<string,mixed>,rollback:array<string,mixed>,next:array<string,mixed>,operations:list<array<string,mixed>>,dependencies:list<string>}
     */
    public static function currentNext(
        string $rootDirectory,
        string $databasePath,
        int $pageSize,
        string $savepoint,
        array $currentPages,
        array $nextPages,
    ): array {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite pager cache file-handle page size must be positive');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager cache file-handle savepoint name must not be empty');
        }
        if ($currentPages === []) {
            throw new \InvalidArgumentException('SQLite pager cache file-handle current write set must not be empty');
        }
        if ($nextPages === []) {
            throw new \InvalidArgumentException('SQLite pager cache file-handle next write set must not be empty');
        }

        self::assertPages($currentPages, $pageSize, 'current');
        self::assertPages($nextPages, $pageSize, 'next');

        $handle = new SQLiteVfsFileHandle($rootDirectory, $databasePath);
        $stack = new SQLiteSavepointStack();
        $stack->beginTransaction('pager-cache-file-handle');
        $stack->savepoint($savepoint);

        $operations = [];
        $currentCaptured = [];
        foreach ($currentPages as $pageNumber => $pageImage) {
            $before = self::readPageImage($handle, $pageNumber, $pageSize);
            $stack->recordPageImageWrite($pageNumber, $before);
            $write = $handle->writeAt(($pageNumber - 1) * $pageSize, $pageImage);
            $currentCaptured[] = [
                'page_number' => $pageNumber,
                'captured_bytes' => strlen($before),
                'zero_filled_short_read' => $before === str_repeat("\0", $pageSize),
                'written_bytes' => $write['bytes_written'],
            ];
            $operations[] = [
                'op' => 'capture_before_image',
                'page_number' => $pageNumber,
                'bytes' => strlen($before),
                'reason' => 'first_dirty_page_write_in_savepoint',
            ];
            $operations[] = [
                'op' => 'write_current_page',
                'page_number' => $pageNumber,
                'offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $write['bytes_written'],
                'reason' => 'apply_current_dirty_page_to_file_handle',
            ];
        }

        $currentFileBytes = self::readDatabaseImage($handle, $pageSize);
        $rollbackImage = $stack->rollbackToDatabaseImage($savepoint, $currentFileBytes, $pageSize);
        $rollbackPlan = $stack->rollbackToImagePlan($savepoint, $pageSize);
        foreach ($rollbackPlan['restore_pages'] as $restorePage) {
            $pageNumber = $restorePage['page_number'];
            $pageImage = substr($rollbackImage, ($pageNumber - 1) * $pageSize, $pageSize);
            $write = $handle->writeAt(($pageNumber - 1) * $pageSize, $pageImage);
            $operations[] = [
                'op' => 'restore_savepoint_page',
                'page_number' => $pageNumber,
                'offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $write['bytes_written'],
                'source_frame' => $restorePage['source_frame'],
                'reason' => 'rollback_current_savepoint_on_file_handle',
            ];
        }
        $stack->rollbackTo($savepoint);

        $nextCaptured = [];
        foreach ($nextPages as $pageNumber => $pageImage) {
            $before = self::readPageImage($handle, $pageNumber, $pageSize);
            $stack->recordPageImageWrite($pageNumber, $before);
            $write = $handle->writeAt(($pageNumber - 1) * $pageSize, $pageImage);
            $nextCaptured[] = [
                'page_number' => $pageNumber,
                'captured_bytes' => strlen($before),
                'zero_filled_short_read' => $before === str_repeat("\0", $pageSize),
                'captured_matches_rollback' => $before === substr($rollbackImage, ($pageNumber - 1) * $pageSize, $pageSize),
                'written_bytes' => $write['bytes_written'],
            ];
            $operations[] = [
                'op' => 'capture_next_before_image',
                'page_number' => $pageNumber,
                'bytes' => strlen($before),
                'reason' => 'first_dirty_page_write_after_rollback_to_current_savepoint',
            ];
            $operations[] = [
                'op' => 'write_next_page',
                'page_number' => $pageNumber,
                'offset' => ($pageNumber - 1) * $pageSize,
                'bytes' => $write['bytes_written'],
                'reason' => 'apply_next_dirty_page_to_file_handle',
            ];
        }

        $finalBytes = self::readDatabaseImage($handle, $pageSize);

        return [
            'status' => 'applied',
            'path' => $databasePath,
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'current' => [
                'written_page_numbers' => array_keys($currentPages),
                'captured_pages' => $currentCaptured,
                'database_bytes' => strlen($currentFileBytes),
                'pending_page_numbers' => $rollbackPlan['restored_page_numbers'],
            ],
            'rollback' => [
                'restored_page_numbers' => $rollbackPlan['restored_page_numbers'],
                'missing_page_numbers' => $rollbackPlan['missing_page_numbers'],
                'database_bytes' => strlen($rollbackImage),
                'transaction_active_after' => $stack->transactionActive(),
            ],
            'next' => [
                'written_page_numbers' => array_keys($nextPages),
                'captured_pages' => $nextCaptured,
                'pending_page_numbers' => $stack->pendingPageNumbers(),
                'database_bytes' => strlen($finalBytes),
            ],
            'operations' => $operations,
            'dependencies' => [
                'sqlite-pager-cache-savepoint-file-handle-current-next76',
                'vfs-file-handle-primitive',
                'sqlite-savepoint-page-image-rollback',
            ],
        ];
    }

    /**
     * @param array<int,string> $pages
     */
    private static function assertPages(array $pages, int $pageSize, string $label): void
    {
        foreach ($pages as $pageNumber => $pageImage) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException("SQLite pager cache {$label} page numbers are one-based integers");
            }
            if (!is_string($pageImage) || strlen($pageImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager cache {$label} page {$pageNumber} image must match the page size");
            }
        }
    }

    private static function readPageImage(SQLiteVfsFileHandle $handle, int $pageNumber, int $pageSize): string
    {
        return $handle->readAt(($pageNumber - 1) * $pageSize, $pageSize)['zero_filled_data'];
    }

    private static function readDatabaseImage(SQLiteVfsFileHandle $handle, int $pageSize): string
    {
        $stat = $handle->stat();
        $size = $stat['size'];
        if ($size % $pageSize !== 0) {
            $size += $pageSize - ($size % $pageSize);
        }

        return $handle->readAt(0, $size)['zero_filled_data'];
    }
}
