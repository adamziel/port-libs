<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePageCache
{
    /** @var array<int, string> */
    private array $pages = [];

    private function __construct(
        public readonly string $path,
        public readonly SQLiteHeader $header,
        public readonly int $fileSize,
        public readonly int $availablePages,
        public readonly ?int $declaredPages,
        public readonly bool $readOnly,
        public readonly ?string $vfs,
        public readonly array $open,
        public readonly array $dependencies
    ) {
    }

    public static function open(
        string $filename,
        bool $lockAvailable = true,
        ?SQLiteBusyHandler $busyHandler = null
    ): self {
        $inspection = SQLiteFileHeaderLoader::inspect($filename, $lockAvailable, $busyHandler);
        if (!$inspection['can_read_header'] || !$inspection['header'] instanceof SQLiteHeader) {
            $reason = $inspection['reason'] ?? $inspection['status'];
            throw new \InvalidArgumentException("SQLite page cache requires a readable database header: {$reason}");
        }

        $header = $inspection['header'];
        $fileSize = $inspection['file_size'];
        if (!is_int($fileSize) || $fileSize < $header->pageSize) {
            throw new \InvalidArgumentException('SQLite page cache requires a complete first page image');
        }

        return new self(
            $inspection['path'],
            $header,
            $fileSize,
            intdiv($fileSize, $header->pageSize),
            $header->databaseSizePages > 0 ? $header->databaseSizePages : null,
            (bool) $inspection['open']['read_only'],
            $inspection['open']['vfs'],
            $inspection['open'],
            self::dependencies($inspection['dependencies'])
        );
    }

    /**
     * @return array{status:string,path:string,page_size:int,file_size:int,available_pages:int,declared_pages:int|null,complete_declared_pages:bool|null,read_only:bool,vfs:string|null,dependencies:list<string>}
     */
    public function summary(): array
    {
        return [
            'status' => $this->declaredPages === null || $this->availablePages >= $this->declaredPages ? 'ready' : 'incomplete-declared-pages',
            'path' => $this->path,
            'page_size' => $this->header->pageSize,
            'file_size' => $this->fileSize,
            'available_pages' => $this->availablePages,
            'declared_pages' => $this->declaredPages,
            'complete_declared_pages' => $this->declaredPages === null ? null : $this->availablePages >= $this->declaredPages,
            'read_only' => $this->readOnly,
            'vfs' => $this->vfs,
            'dependencies' => $this->dependencies,
        ];
    }

    public function page(int $pageNumber): string
    {
        $this->assertPageNumber($pageNumber);
        if (isset($this->pages[$pageNumber])) {
            return $this->pages[$pageNumber];
        }

        $offset = ($pageNumber - 1) * $this->header->pageSize;
        $handle = @fopen($this->path, 'rb');
        if ($handle === false) {
            throw new \InvalidArgumentException("Unable to open SQLite database file for page read: {$this->path}");
        }

        try {
            if (fseek($handle, $offset) !== 0) {
                throw new \InvalidArgumentException("Unable to seek SQLite database page {$pageNumber}");
            }

            $page = fread($handle, $this->header->pageSize);
            if ($page === false) {
                throw new \InvalidArgumentException("Unable to read SQLite database page {$pageNumber}");
            }
        } finally {
            fclose($handle);
        }

        if (strlen($page) !== $this->header->pageSize) {
            throw new \InvalidArgumentException("SQLite database page {$pageNumber} is shorter than the declared page size");
        }

        $this->pages[$pageNumber] = $page;

        return $page;
    }

    /**
     * @return array<int, string>
     */
    public function pages(array $pageNumbers): array
    {
        $pages = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite page numbers must be integers');
            }
            $pages[$pageNumber] = $this->page($pageNumber);
        }

        return $pages;
    }

    public function hasCachedPage(int $pageNumber): bool
    {
        return isset($this->pages[$pageNumber]);
    }

    public function cachedPageCount(): int
    {
        return count($this->pages);
    }

    private function assertPageNumber(int $pageNumber): void
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite page numbers are one-based');
        }
        if ($pageNumber > $this->availablePages) {
            throw new \OutOfBoundsException("SQLite database page {$pageNumber} is not available in the file image");
        }
        if ($this->declaredPages !== null && $pageNumber > $this->declaredPages) {
            throw new \OutOfBoundsException("SQLite database page {$pageNumber} is beyond the declared database size");
        }
    }

    /**
     * @param list<string> $headerDependencies
     * @return list<string>
     */
    private static function dependencies(array $headerDependencies): array
    {
        $dependencies = $headerDependencies;
        $dependencies[] = 'bounded-page-cache';
        $dependencies[] = 'page-size-aligned-read';

        return array_values(array_unique($dependencies));
    }
}
