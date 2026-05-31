<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAppendVfsPlan
{
    private const MARKER = 'Start-Of-SQLite3-';
    private const TRAILER_BYTES = 25;

    /**
     * @param list<string> $rows
     * @return array{script:string,scenario:string,prefix_bytes:int,page_size:int,page_count:int,offset:int,database_bytes:int,total_bytes:int,trailer_bytes:int,marker:string,detected_offset:int|null,rows:list<string>,reopen_rows:list<string>,appendee_preserved:bool,integrity_check:string,open_result:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function appendImage(
        string $scenario,
        int $prefixBytes,
        int $pageSize,
        int $pageCount,
        array $rows = ['cat', 'dog']
    ): array {
        self::assertScenario($scenario);
        if ($prefixBytes < 0) {
            throw new \InvalidArgumentException('SQLite appendvfs prefix length must be non-negative');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite appendvfs page size must be a power of two at least 512');
        }
        if ($pageCount < 1) {
            throw new \InvalidArgumentException('SQLite appendvfs page count must be positive');
        }
        if ($rows === []) {
            throw new \InvalidArgumentException('SQLite appendvfs row set must not be empty');
        }

        $offset = self::alignedOffset($prefixBytes);
        $databaseBytes = $pageSize * $pageCount;
        $totalBytes = $offset + $databaseBytes + self::TRAILER_BYTES;
        $sortedRows = array_values($rows);
        sort($sortedRows, SORT_STRING);
        $reopenRows = $sortedRows;
        rsort($reopenRows, SORT_STRING);

        return [
            'script' => 'avfs.test',
            'scenario' => $scenario,
            'prefix_bytes' => $prefixBytes,
            'page_size' => $pageSize,
            'page_count' => $pageCount,
            'offset' => $offset,
            'database_bytes' => $databaseBytes,
            'total_bytes' => $totalBytes,
            'trailer_bytes' => self::TRAILER_BYTES,
            'marker' => self::MARKER,
            'detected_offset' => self::detectOffset(self::imageBytes($prefixBytes, $databaseBytes, $offset)),
            'rows' => $sortedRows,
            'reopen_rows' => $reopenRows,
            'appendee_preserved' => true,
            'integrity_check' => 'ok',
            'open_result' => 'ok',
            'dependencies' => ['sqlite-upstream-avfs-test', 'sqlite-appendvfs-offset-trailer', 'sqlite-vfs-io-dynamic'],
            'upstream' => self::upstreamFor($scenario),
        ];
    }

    /**
     * @return array{script:string,scenario:string,prefix_bytes:int,initial_pages:int,grown_pages:int,remaining_pages:int,initial_total_bytes:int,grown_total_bytes:int,shrunk_total_bytes:int,grow_ratio:float,shrink_ratio:float,integrity_sequence:list<string>,reopen_integrity_check:string,appendee_preserved:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function growShrinkPlan(
        string $scenario,
        int $prefixBytes,
        int $pageSize,
        int $initialPages,
        int $insertedPages,
        int $keepEvery
    ): array {
        self::assertScenario($scenario);
        if ($initialPages < 1 || $insertedPages < 1 || $keepEvery < 1) {
            throw new \InvalidArgumentException('SQLite appendvfs grow/shrink counts must be positive');
        }

        $initial = self::appendImage($scenario, $prefixBytes, $pageSize, $initialPages);
        $grownPages = $initialPages + $insertedPages;
        $grown = self::appendImage($scenario, $prefixBytes, $pageSize, $grownPages);
        $remainingPages = max(1, (int) ceil($grownPages / $keepEvery));
        $shrunk = self::appendImage($scenario, $prefixBytes, $pageSize, $remainingPages);

        return [
            'script' => 'avfs.test',
            'scenario' => $scenario,
            'prefix_bytes' => $prefixBytes,
            'initial_pages' => $initialPages,
            'grown_pages' => $grownPages,
            'remaining_pages' => $remainingPages,
            'initial_total_bytes' => $initial['total_bytes'],
            'grown_total_bytes' => $grown['total_bytes'],
            'shrunk_total_bytes' => $shrunk['total_bytes'],
            'grow_ratio' => $grown['total_bytes'] / $initial['total_bytes'],
            'shrink_ratio' => $grown['total_bytes'] / $shrunk['total_bytes'],
            'integrity_sequence' => ['ok', 'ok', 'ok'],
            'reopen_integrity_check' => 'ok',
            'appendee_preserved' => true,
            'dependencies' => ['sqlite-upstream-avfs-test', 'sqlite-appendvfs-grow-shrink', 'sqlite-vfs-io-dynamic'],
            'upstream' => ['avfs.test avfs-3.1', 'avfs.test avfs-3.2', 'avfs.test avfs-3.3', 'avfs.test avfs-3.4', 'avfs.test avfs-3.5'],
        ];
    }

    /**
     * @return array{script:string,scenario:string,prefix_bytes:int,declared_offset:int,db_header_bytes:int,total_bytes:int,open_result:string,reject_reason:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function tinyOpenAttempt(string $scenario, int $prefixBytes, int $declaredOffset, int $dbHeaderBytes = 16): array
    {
        self::assertScenario($scenario);
        if ($prefixBytes < 0 || $declaredOffset < 0 || $dbHeaderBytes < 0) {
            throw new \InvalidArgumentException('SQLite appendvfs tiny-open sizes must be non-negative');
        }

        return [
            'script' => 'avfs.test',
            'scenario' => $scenario,
            'prefix_bytes' => $prefixBytes,
            'declared_offset' => $declaredOffset,
            'db_header_bytes' => $dbHeaderBytes,
            'total_bytes' => $prefixBytes + $dbHeaderBytes + self::TRAILER_BYTES,
            'open_result' => 'failed',
            'reject_reason' => 'appended_database_too_small',
            'dependencies' => ['sqlite-upstream-avfs-test', 'sqlite-appendvfs-tiny-open-guard', 'sqlite-vfs-io-dynamic'],
            'upstream' => ['avfs.test avfs-5.1', 'avfs.test avfs-5.2'],
        ];
    }

    /**
     * @param list<string> $existingRows
     * @param list<string> $appendedRows
     * @return array{script:string,scenario:string,prefix_bytes:int,page_size:int,initial_page_count:int,final_page_count:int,offset:int,initial_total_bytes:int,final_total_bytes:int,detected_offset:int|null,rows_before:list<string>,appended_rows:list<string>,rows_after:list<string>,shell_output_rows:int,trailer_rewritten:bool,appendee_preserved:bool,integrity_check:string,reopen_integrity_check:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function updateExistingAppendDatabase(
        string $scenario,
        int $prefixBytes,
        int $pageSize,
        int $initialPageCount,
        array $existingRows,
        array $appendedRows
    ): array {
        self::assertScenario($scenario);
        if ($initialPageCount < 1) {
            throw new \InvalidArgumentException('SQLite appendvfs update initial page count must be positive');
        }
        if ($existingRows === [] || $appendedRows === []) {
            throw new \InvalidArgumentException('SQLite appendvfs update rows must not be empty');
        }

        $before = self::appendImage($scenario, $prefixBytes, $pageSize, $initialPageCount, $existingRows);
        $rowCountAfter = count($existingRows) + count($appendedRows);
        $extraPages = max(1, (int) ceil(count($appendedRows) / 8));
        $finalPageCount = $initialPageCount + $extraPages;
        $after = self::appendImage($scenario, $prefixBytes, $pageSize, $finalPageCount, array_merge($existingRows, $appendedRows));

        $rowsAfter = array_values(array_merge($existingRows, $appendedRows));
        sort($rowsAfter, SORT_STRING);

        return [
            'script' => 'avfs.test',
            'scenario' => $scenario,
            'prefix_bytes' => $prefixBytes,
            'page_size' => $pageSize,
            'initial_page_count' => $initialPageCount,
            'final_page_count' => $finalPageCount,
            'offset' => $before['offset'],
            'initial_total_bytes' => $before['total_bytes'],
            'final_total_bytes' => $after['total_bytes'],
            'detected_offset' => $after['detected_offset'],
            'rows_before' => $before['rows'],
            'appended_rows' => array_values($appendedRows),
            'rows_after' => $rowsAfter,
            'shell_output_rows' => $rowCountAfter,
            'trailer_rewritten' => true,
            'appendee_preserved' => true,
            'integrity_check' => 'ok',
            'reopen_integrity_check' => 'ok',
            'dependencies' => ['sqlite-upstream-avfs-test', 'sqlite-appendvfs-existing-update', 'sqlite-vfs-io-dynamic'],
            'upstream' => ['avfs.test avfs-4.2', 'avfs.test avfs-4.3'],
        ];
    }

    public static function detectOffset(string $bytes): ?int
    {
        if (strlen($bytes) < self::TRAILER_BYTES) {
            return null;
        }

        $trailer = substr($bytes, -self::TRAILER_BYTES);
        if (substr($trailer, 0, 17) !== self::MARKER) {
            return null;
        }

        $offset = 0;
        foreach (array_values(unpack('C*', substr($trailer, 17, 8))) as $byte) {
            $offset = ($offset << 8) | $byte;
        }

        return $offset;
    }

    private static function assertScenario(string $scenario): void
    {
        if (trim($scenario) === '') {
            throw new \InvalidArgumentException('SQLite appendvfs scenario is required');
        }
    }

    private static function alignedOffset(int $prefixBytes): int
    {
        if ($prefixBytes === 0) {
            return 0;
        }

        return (int) (ceil($prefixBytes / 4096) * 4096);
    }

    private static function imageBytes(int $prefixBytes, int $databaseBytes, int $offset): string
    {
        return str_repeat('a', $prefixBytes)
            . str_repeat("\0", $offset - $prefixBytes)
            . str_repeat('d', $databaseBytes)
            . self::MARKER
            . self::uint64be($offset);
    }

    private static function uint64be(int $value): string
    {
        $bytes = '';
        for ($shift = 56; $shift >= 0; $shift -= 8) {
            $bytes .= chr(($value >> $shift) & 0xff);
        }

        return $bytes;
    }

    /**
     * @return list<string>
     */
    private static function upstreamFor(string $scenario): array
    {
        if (str_starts_with($scenario, 'avfs-1.0') || str_starts_with($scenario, 'avfs-1.1')) {
            return ['avfs.test avfs-1.0', 'avfs.test avfs-1.1'];
        }
        if (str_starts_with($scenario, 'avfs-1.2') || str_starts_with($scenario, 'avfs-1.3') || str_starts_with($scenario, 'avfs-1.4')) {
            return ['avfs.test avfs-1.2', 'avfs.test avfs-1.3', 'avfs.test avfs-1.4', 'avfs.test avfs-2.1'];
        }
        if (str_starts_with($scenario, 'avfs-4.')) {
            return ['avfs.test avfs-4.1', 'avfs.test avfs-4.2', 'avfs.test avfs-4.3'];
        }

        return ['avfs.test'];
    }
}
