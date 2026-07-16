<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaRuntimeCatalog
{
    /** @var list<string> */
    private array $collations;

    /** @var list<string> */
    private array $modules;

    /** @var list<array{name: string, builtin: int, type: string, enc: string, narg: int, flags: int}> */
    private array $functions;

    /**
     * @param list<string> $collations
     * @param list<string> $modules
     * @param list<array{name: string, builtin?: int, type?: string, enc?: string, narg: int, flags?: int}> $functions
     */
    public function __construct(array $collations = [], array $modules = [], array $functions = [])
    {
        $this->collations = self::uniqueNames($collations === [] ? ['RTRIM', 'NOCASE', 'BINARY'] : $collations);
        $this->modules = self::uniqueNames($modules === [] ? ['json_tree', 'json_each', 'fts5', 'rtree'] : $modules);
        $this->functions = self::normalizeFunctions($functions === [] ? self::defaultFunctions() : $functions);
    }

    public function addCollation(string $name): void
    {
        $this->collations = self::appendUniqueName($this->collations, $name);
    }

    public function addModule(string $name): void
    {
        $this->modules = self::appendUniqueName($this->modules, $name);
    }

    public function addFunction(string $name, int $narg, int $flags = 0, string $type = 's', string $encoding = 'utf8', int $builtin = 0): void
    {
        $normalized = self::normalizeFunction([
            'name' => $name,
            'builtin' => $builtin,
            'type' => $type,
            'enc' => $encoding,
            'narg' => $narg,
            'flags' => $flags,
        ]);

        foreach ($this->functions as $offset => $function) {
            if (
                strcasecmp($function['name'], $normalized['name']) === 0
                && $function['narg'] === $normalized['narg']
                && strtolower($function['type']) === strtolower($normalized['type'])
            ) {
                $this->functions[$offset] = $normalized;
                return;
            }
        }

        $this->functions[] = $normalized;
    }

    /**
     * @return array{status: string, pragma: 'collation_list'|'module_list'|'function_list', rows: list<array<string,int|string>>}
     */
    public function execute(string $sql): array
    {
        $pragma = self::parsePragma($sql);

        return [
            'status' => 'ok',
            'pragma' => $pragma,
            'schema' => 'main',
            'target' => '',
            'rows' => match ($pragma) {
                'collation_list' => $this->collationRows(),
                'module_list' => $this->moduleRows(),
                'function_list' => $this->functionRows(),
            },
        ];
    }

    public function executeCursor(string $sql): SQLitePragmaRowCursor
    {
        return new SQLitePragmaRowCursor($this->execute($sql));
    }

    /**
     * @return list<array{seq: int, name: string}>
     */
    public function collationRows(): array
    {
        $rows = [];
        foreach ($this->collations as $seq => $name) {
            $rows[] = ['seq' => $seq, 'name' => $name];
        }

        return $rows;
    }

    /**
     * @return list<array{name: string}>
     */
    public function moduleRows(): array
    {
        return array_map(static fn (string $name): array => ['name' => $name], $this->modules);
    }

    /**
     * @return list<array{name: string, builtin: int, type: string, enc: string, narg: int, flags: int}>
     */
    public function functionRows(): array
    {
        return $this->functions;
    }

    /**
     * @return 'collation_list'|'module_list'|'function_list'
     */
    public static function parsePragma(string $sql): string
    {
        $trimmed = rtrim(trim($sql), ';');
        if (!preg_match('/^pragma\s+(?:main\s*\.\s*)?(?<pragma>collation_list|module_list|function_list)\s*(?:\(\s*\)|=\s*)?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only PRAGMA collation_list, module_list, and function_list are supported');
        }

        return strtolower($matches['pragma']);
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private static function uniqueNames(array $names): array
    {
        $unique = [];
        foreach ($names as $name) {
            $unique = self::appendUniqueName($unique, $name);
        }

        return $unique;
    }

    /**
     * @param list<string> $names
     * @return list<string>
     */
    private static function appendUniqueName(array $names, string $name): array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidArgumentException('SQLite runtime PRAGMA names cannot be empty');
        }

        foreach ($names as $existing) {
            if (strcasecmp($existing, $trimmed) === 0) {
                return $names;
            }
        }

        $names[] = $trimmed;

        return $names;
    }

    /**
     * @param list<array{name: string, builtin?: int, type?: string, enc?: string, narg: int, flags?: int}> $functions
     * @return list<array{name: string, builtin: int, type: string, enc: string, narg: int, flags: int}>
     */
    private static function normalizeFunctions(array $functions): array
    {
        $normalized = [];
        foreach ($functions as $function) {
            $row = self::normalizeFunction($function);
            $key = strtolower($row['name']) . "\0" . $row['type'] . "\0" . $row['narg'];
            $normalized[$key] = $row;
        }

        return array_values($normalized);
    }

    /**
     * @param array{name: string, builtin?: int, type?: string, enc?: string, narg: int, flags?: int} $function
     * @return array{name: string, builtin: int, type: string, enc: string, narg: int, flags: int}
     */
    private static function normalizeFunction(array $function): array
    {
        $name = trim($function['name']);
        if ($name === '') {
            throw new InvalidArgumentException('SQLite function name cannot be empty');
        }
        if (!isset($function['narg'])) {
            throw new InvalidArgumentException("SQLite function {$name} needs an argument count");
        }

        return [
            'name' => strtolower($name),
            'builtin' => (int) ($function['builtin'] ?? 1),
            'type' => strtolower((string) ($function['type'] ?? 's')),
            'enc' => strtolower((string) ($function['enc'] ?? 'utf8')),
            'narg' => (int) $function['narg'],
            'flags' => (int) ($function['flags'] ?? 0),
        ];
    }

    /**
     * @return list<array{name: string, builtin: int, type: string, enc: string, narg: int, flags: int}>
     */
    private static function defaultFunctions(): array
    {
        return [
            ['name' => 'json_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 0x200800],
            ['name' => 'jsonb_extract', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 0x200800],
            ['name' => 'json_valid', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => -1, 'flags' => 0x200800],
            ['name' => 'json_error_position', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0x200800],
            ['name' => 'lower', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0x200800],
            ['name' => 'upper', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0x200800],
            ['name' => 'length', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 1, 'flags' => 0x200800],
            ['name' => 'like', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 0x200800],
            ['name' => 'like', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 3, 'flags' => 0x200800],
            ['name' => 'glob', 'builtin' => 1, 'type' => 's', 'enc' => 'utf8', 'narg' => 2, 'flags' => 0x200800],
        ];
    }
}
