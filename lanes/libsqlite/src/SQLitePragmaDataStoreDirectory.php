<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaDataStoreDirectory
{
    private ?string $directory = null;

    public function __construct(?string $directory = null)
    {
        if ($directory !== null) {
            $this->directory = $this->normalizeDirectory($directory);
        }
    }

    /**
     * @return array{
     *     status:string,
     *     pragma:'data_store_directory',
     *     mode:'query'|'assignment',
     *     directory:string|null,
     *     changed:bool,
     *     rows:list<array{data_store_directory:string}>,
     *     reason:string
     * }
     */
    public function execute(string $sql): array
    {
        $value = self::parse($sql);
        if ($value === null) {
            return [
                'status' => 'ok',
                'pragma' => 'data_store_directory',
                'mode' => 'query',
                'directory' => $this->directory,
                'changed' => false,
                'rows' => $this->directory === null ? [] : [['data_store_directory' => $this->directory]],
                'reason' => $this->directory === null ? 'data_store_directory_unset' : 'data_store_directory_set',
            ];
        }

        $previous = $this->directory;
        $this->directory = $value === '' ? null : $this->normalizeDirectory($value);

        return [
            'status' => 'ok',
            'pragma' => 'data_store_directory',
            'mode' => 'assignment',
            'directory' => $this->directory,
            'changed' => $previous !== $this->directory,
            'rows' => [],
            'reason' => $this->directory === null ? 'data_store_directory_cleared' : 'data_store_directory_assigned',
        ];
    }

    /**
     * @return array{seq:int,name:string,file:string}
     */
    public function databaseListRow(string $databasePath, string $schema = 'main', int $sequence = 0): array
    {
        $name = self::normalizeIdentifier($schema, 'SQLite database-list schema name');
        if ($sequence < 0) {
            throw new InvalidArgumentException('SQLite database-list sequence must be non-negative');
        }

        return [
            'seq' => $sequence,
            'name' => $name,
            'file' => $this->resolveDatabasePath($databasePath),
        ];
    }

    /**
     * @return array{
     *     status:string,
     *     pragma:'database_list',
     *     data_store_directory:string|null,
     *     rows:list<array{seq:int,name:string,file:string}>
     * }
     */
    public function databaseList(string $databasePath, string $schema = 'main', int $sequence = 0): array
    {
        return [
            'status' => 'ok',
            'pragma' => 'database_list',
            'data_store_directory' => $this->directory,
            'rows' => [$this->databaseListRow($databasePath, $schema, $sequence)],
        ];
    }

    public function resolveDatabasePath(string $databasePath): string
    {
        $path = self::normalizePath($databasePath, 'SQLite database path');
        if ($this->directory === null || $path === ':memory:' || self::isAbsolutePath($path)) {
            return $path;
        }

        return self::joinPath($this->directory, $path);
    }

    public function directory(): ?string
    {
        return $this->directory;
    }

    private function normalizeDirectory(string $directory): string
    {
        $path = self::normalizePath($directory, 'SQLite data_store_directory path');
        if ($path === ':memory:') {
            throw new InvalidArgumentException('SQLite data_store_directory cannot be an in-memory database name');
        }

        return self::stripTrailingSeparators($path);
    }

    private static function normalizeIdentifier(string $identifier, string $label): string
    {
        $trimmed = trim($identifier);
        if ($trimmed === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $trimmed) !== 1) {
            throw new InvalidArgumentException($label . ' must be an SQLite identifier');
        }

        return $trimmed;
    }

    /**
     * @return string|null The right-hand-side value, or null for a query form.
     */
    public static function parse(string $sql): ?string
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        if (preg_match('/^pragma\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?data_store_directory(?<tail>.*)$/is', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('Unsupported SQLite PRAGMA data_store_directory SQL');
        }
        if (($matches['schema'] ?? '') !== '') {
            throw new InvalidArgumentException('SQLite PRAGMA data_store_directory is connection-wide, not schema-qualified');
        }

        $tail = trim($matches['tail']);
        if ($tail === '') {
            return null;
        }

        if (str_starts_with($tail, '=')) {
            return self::decodePragmaValue(substr($tail, 1));
        }

        if (str_starts_with($tail, '(') && str_ends_with($tail, ')')) {
            $inside = trim(substr($tail, 1, -1));
            return $inside === '' ? null : self::decodePragmaValue($inside);
        }

        throw new InvalidArgumentException('Unsupported SQLite PRAGMA data_store_directory assignment syntax');
    }

    private static function decodePragmaValue(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('SQLite PRAGMA data_store_directory value cannot be omitted');
        }

        $first = $trimmed[0];
        $last = $trimmed[strlen($trimmed) - 1];
        if ($first === "'" && $last === "'") {
            return str_replace("''", "'", substr($trimmed, 1, -1));
        }
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($trimmed, 1, -1));
        }

        if (str_contains($trimmed, ' ') || str_contains($trimmed, "\t")) {
            throw new InvalidArgumentException('SQLite PRAGMA data_store_directory unquoted value cannot contain whitespace');
        }

        return $trimmed;
    }

    private static function normalizePath(string $path, string $label): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            throw new InvalidArgumentException($label . ' cannot be empty');
        }
        if (str_contains($trimmed, "\0")) {
            throw new InvalidArgumentException($label . ' cannot contain NUL bytes');
        }

        return $trimmed;
    }

    private static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    private static function joinPath(string $directory, string $relativePath): string
    {
        $separator = str_contains($directory, '\\') && !str_contains($directory, '/') ? '\\' : '/';

        return self::stripTrailingSeparators($directory) . $separator . ltrim($relativePath, "/\\");
    }

    private static function stripTrailingSeparators(string $path): string
    {
        if ($path === '/' || preg_match('/^[A-Za-z]:[\/\\\\]$/', $path) === 1) {
            return $path;
        }

        $stripped = rtrim($path, "/\\");

        return $stripped === '' ? $path[0] : $stripped;
    }
}
