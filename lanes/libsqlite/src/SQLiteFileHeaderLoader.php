<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteFileHeaderLoader
{
    /**
     * @return array{status:string,can_open:bool,can_read_header:bool,path:string,bytes_read:int,file_size:int|null,minimum_first_page_bytes:int|null,minimum_declared_bytes:int|null,complete_first_page:bool|null,complete_declared_pages:bool|null,header:SQLiteHeader|null,open:array<string, mixed>,reason:string|null,dependencies:list<string>}
     */
    public static function inspect(
        string $filename,
        bool $lockAvailable = true,
        ?SQLiteBusyHandler $busyHandler = null
    ): array {
        $uri = SQLiteFileUri::parse($filename);
        $path = (string) $uri['path'];
        $memory = ($uri['mode'] ?? null) === 'memory' || $path === ':memory:';
        $fileExists = !$memory && is_file($path);
        $directory = $path === '' ? '.' : dirname($path);
        $directoryWritable = is_dir($directory) && is_writable($directory);
        $open = SQLiteOpenPlan::forFilename($filename, $fileExists, $directoryWritable, $lockAvailable, $busyHandler);

        if (!$open['can_open']) {
            return self::result($open['status'], false, $path, 0, $fileExists ? self::fileSize($path) : null, null, null, null, null, null, $open, $open['reason']);
        }

        if ($open['memory']) {
            return self::result('memory-open', false, $path, 0, null, null, null, null, null, null, $open, 'in-memory databases do not have a file header');
        }

        if (!is_readable($path)) {
            return self::result('unreadable', false, $path, 0, self::fileSize($path), null, null, null, null, null, $open, 'database file is not readable');
        }

        $prefix = self::readPrefix($path, 100);
        $bytesRead = strlen($prefix);
        if ($bytesRead < 100) {
            return self::result('short-header', false, $path, $bytesRead, self::fileSize($path), null, null, null, null, null, $open, 'SQLite database header requires at least 100 bytes');
        }

        try {
            $header = SQLiteHeader::parse($prefix);
        } catch (\InvalidArgumentException $exception) {
            return self::result('invalid-header', false, $path, $bytesRead, self::fileSize($path), null, null, null, null, null, $open, $exception->getMessage());
        }

        $fileSize = self::fileSize($path);
        $minimumFirstPageBytes = $header->pageSize;
        $minimumDeclaredBytes = $header->databaseSizePages > 0 ? $header->pageSize * $header->databaseSizePages : null;
        $completeFirstPage = $fileSize !== null && $fileSize >= $minimumFirstPageBytes;
        $completeDeclaredPages = $minimumDeclaredBytes === null ? null : ($fileSize !== null && $fileSize >= $minimumDeclaredBytes);
        $status = $completeFirstPage ? 'header-ready' : 'incomplete-first-page';

        return self::result(
            $status,
            $completeFirstPage,
            $path,
            $bytesRead,
            $fileSize,
            $minimumFirstPageBytes,
            $minimumDeclaredBytes,
            $completeFirstPage,
            $completeDeclaredPages,
            $header,
            $open,
            $completeFirstPage ? null : 'database file is smaller than its first page'
        );
    }

    private static function readPrefix(string $path, int $bytes): string
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException("Unable to open SQLite database file for header read: {$path}");
        }

        try {
            $data = fread($handle, $bytes);
            if ($data === false) {
                throw new \InvalidArgumentException("Unable to read SQLite database header: {$path}");
            }

            return $data;
        } finally {
            fclose($handle);
        }
    }

    private static function fileSize(string $path): ?int
    {
        $size = @filesize($path);

        return $size === false ? null : $size;
    }

    /**
     * @param array<string, mixed> $open
     * @return array{status:string,can_open:bool,can_read_header:bool,path:string,bytes_read:int,file_size:int|null,minimum_first_page_bytes:int|null,minimum_declared_bytes:int|null,complete_first_page:bool|null,complete_declared_pages:bool|null,header:SQLiteHeader|null,open:array<string, mixed>,reason:string|null,dependencies:list<string>}
     */
    private static function result(
        string $status,
        bool $canReadHeader,
        string $path,
        int $bytesRead,
        ?int $fileSize,
        ?int $minimumFirstPageBytes,
        ?int $minimumDeclaredBytes,
        ?bool $completeFirstPage,
        ?bool $completeDeclaredPages,
        ?SQLiteHeader $header,
        array $open,
        ?string $reason
    ): array {
        return [
            'status' => $status,
            'can_open' => (bool) $open['can_open'],
            'can_read_header' => $canReadHeader,
            'path' => $path,
            'bytes_read' => $bytesRead,
            'file_size' => $fileSize,
            'minimum_first_page_bytes' => $minimumFirstPageBytes,
            'minimum_declared_bytes' => $minimumDeclaredBytes,
            'complete_first_page' => $completeFirstPage,
            'complete_declared_pages' => $completeDeclaredPages,
            'header' => $header,
            'open' => $open,
            'reason' => $reason,
            'dependencies' => self::dependencies($open, $header, $bytesRead),
        ];
    }

    /**
     * @param array<string, mixed> $open
     * @return list<string>
     */
    private static function dependencies(array $open, ?SQLiteHeader $header, int $bytesRead): array
    {
        $dependencies = $open['dependencies'];
        $dependencies[] = 'bounded-file-header-read';
        if ($bytesRead >= 100) {
            $dependencies[] = 'sqlite-header-parse';
        }
        if ($header !== null) {
            $dependencies[] = 'sqlite-file-size-check';
        }

        return array_values(array_unique($dependencies));
    }
}
