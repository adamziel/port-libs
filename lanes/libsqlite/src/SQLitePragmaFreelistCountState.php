<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaFreelistCountState
{
    /** @var array<string,array{freelist_count:int,page_count:int,auto_vacuum:int}> */
    private array $schemas = [];

    /**
     * @param array<string,array{freelist_count?:int,page_count?:int,auto_vacuum?:int}> $schemas
     */
    public function __construct(array $schemas = [])
    {
        $schemas += [
            'main' => [],
            'temp' => ['page_count' => 0, 'freelist_count' => 0, 'auto_vacuum' => 0],
        ];

        foreach ($schemas as $schema => $state) {
            $this->schemas[self::normalizeSchema((string) $schema)] = [
                'freelist_count' => self::nonNegativeInt($state['freelist_count'] ?? 0, 'freelist_count'),
                'page_count' => self::nonNegativeInt($state['page_count'] ?? 0, 'page_count'),
                'auto_vacuum' => self::autoVacuumMode($state['auto_vacuum'] ?? 0),
            ];
        }
    }

    /**
     * @return array{status:string,pragma:'freelist_count',schema:string,value:int,changed:bool,write_ignored:bool,rows:list<array{freelist_count:int}>,header:array{freelist_page_count:int,page_count:int,auto_vacuum:int},dependencies:list<string>}
     */
    public function execute(string $sql): array
    {
        $parsed = self::parse($sql);
        $schema = $parsed['schema'];
        $state = $this->schemas[$schema] ?? ['freelist_count' => 0, 'page_count' => 0, 'auto_vacuum' => 0];

        return [
            'status' => 'ok',
            'pragma' => 'freelist_count',
            'schema' => $schema,
            'value' => $state['freelist_count'],
            'changed' => false,
            'write_ignored' => $parsed['value'] !== null,
            'rows' => [['freelist_count' => $state['freelist_count']]],
            'header' => [
                'freelist_page_count' => $state['freelist_count'],
                'page_count' => $state['page_count'],
                'auto_vacuum' => $state['auto_vacuum'],
            ],
            'dependencies' => ['sqlite-pragma-freelist-count-state'],
        ];
    }

    /**
     * @return array{schema:string,pragma:'freelist_count',value:?string}
     */
    public static function parse(string $sql): array
    {
        $trimmed = rtrim(trim($sql), " \t\r\n;");
        $identifier = '(?:[A-Za-z_][A-Za-z0-9_]*|`[^`]+`|\[[^\]]+\])';
        if (preg_match('/^pragma\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?freelist_count\s*(?:(?:=\s*(?<eq>.+))|(?:\((?<paren>.*)\)))?$/is', $trimmed, $matches) !== 1) {
            throw new InvalidArgumentException('SQLite PRAGMA freelist_count state only supports PRAGMA freelist_count');
        }

        return [
            'schema' => isset($matches['schema']) && $matches['schema'] !== '' ? self::normalizeSchema(self::unquoteIdentifier($matches['schema'])) : 'main',
            'pragma' => 'freelist_count',
            'value' => isset($matches['eq']) && $matches['eq'] !== '' ? trim($matches['eq']) : (isset($matches['paren']) && $matches['paren'] !== '' ? trim($matches['paren']) : null),
        ];
    }

    private static function normalizeSchema(string $schema): string
    {
        $schema = strtolower(trim($schema));
        if ($schema === '') {
            throw new InvalidArgumentException('SQLite PRAGMA freelist_count schema cannot be empty');
        }

        return $schema;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ((str_starts_with($identifier, '`') && str_ends_with($identifier, '`'))
            || (str_starts_with($identifier, '[') && str_ends_with($identifier, ']'))
        ) {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function nonNegativeInt(mixed $value, string $field): int
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value) === 1)) {
            throw new InvalidArgumentException("SQLite PRAGMA freelist_count {$field} must be a non-negative integer");
        }
        $int = (int) $value;
        if ($int < 0) {
            throw new InvalidArgumentException("SQLite PRAGMA freelist_count {$field} must be non-negative");
        }

        return $int;
    }

    private static function autoVacuumMode(mixed $value): int
    {
        $mode = self::nonNegativeInt($value, 'auto_vacuum');
        if ($mode > 2) {
            throw new InvalidArgumentException('SQLite PRAGMA freelist_count auto_vacuum must be 0, 1, or 2');
        }

        return $mode;
    }
}
