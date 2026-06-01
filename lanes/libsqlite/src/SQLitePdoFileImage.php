<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePdoFileImage
{
    private const PAGE_SIZE = 4096;

    /**
     * @param array<string,list<string>> $columns
     * @param array<string,list<array<string,mixed>>> $tables
     * @param array<string,string> $tableSql
     * @param array<string,string|null> $rowidAliases
     */
    public static function encode(
        array $columns,
        array $tables,
        array $tableSql,
        array $rowidAliases,
        int $schemaCookie,
        int $fileChangeCounter,
    ): string {
        $tableNames = array_keys($columns);
        $rootPages = [];
        foreach ($tableNames as $index => $table) {
            $rootPages[$table] = $index + 2;
        }

        $schemaCells = [];
        foreach ($tableNames as $index => $table) {
            $schemaCells[] = SQLiteTableLeafCell::encode(
                $index + 1,
                SQLiteRecord::encode([
                    'table',
                    $table,
                    $table,
                    $rootPages[$table],
                    $tableSql[$table] ?? self::createSqlFor($table, $columns[$table]),
                ]),
                self::PAGE_SIZE,
            );
        }

        $pageCount = count($tableNames) + 1;
        $firstPage = SQLiteTableLeafPage::assemble(
            $schemaCells,
            self::PAGE_SIZE,
            100,
            self::databaseHeader($pageCount, $schemaCookie, $fileChangeCounter),
        );

        $pages = [1 => $firstPage];
        foreach ($tableNames as $table) {
            $rowCellEntries = [];
            $rowidAlias = $rowidAliases[$table] ?? null;
            foreach ($tables[$table] ?? [] as $index => $row) {
                $rowId = $rowidAlias === null ? $index + 1 : ($row[$rowidAlias] ?? null);
                if (!is_int($rowId) || $rowId < 0) {
                    throw new \InvalidArgumentException("SQLitePDO file persistence requires a non-negative integer rowid for {$table}");
                }

                $values = [];
                foreach ($columns[$table] as $column) {
                    $values[] = $column === $rowidAlias ? null : ($row[$column] ?? null);
                }

                $rowCellEntries[] = [
                    'rowid' => $rowId,
                    'cell' => SQLiteTableLeafCell::encode(
                        $rowId,
                        SQLiteRecord::encode($values),
                        self::PAGE_SIZE,
                    ),
                ];
            }

            usort($rowCellEntries, static fn (array $left, array $right): int => $left['rowid'] <=> $right['rowid']);
            $rowCells = array_map(static fn (array $entry): string => $entry['cell'], $rowCellEntries);

            $pages[$rootPages[$table]] = SQLiteTableLeafPage::assemble($rowCells, self::PAGE_SIZE);
        }

        ksort($pages);

        return implode('', $pages);
    }

    /**
     * @return array{
     *     columns:array<string,list<string>>,
     *     tables:array<string,list<array<string,mixed>>>,
     *     tableSql:array<string,string>,
     *     rowidAliases:array<string,string|null>,
     *     schemaCookie:int,
     *     fileChangeCounter:int
     * }
     */
    public static function decode(string $path): array
    {
        $database = SQLiteDatabase::fromFile($path);
        $columns = [];
        $tables = [];
        $tableSql = [];
        $rowidAliases = [];

        foreach ($database->schemaRecords() as $record) {
            if ($record->type !== 'table' || $record->rootPage === null || $record->sql === null) {
                continue;
            }
            if (str_starts_with(strtolower($record->name), 'sqlite_')) {
                continue;
            }

            $definition = self::parseCreateTableDefinition($record->sql);
            $columns[$record->name] = $definition['columns'];
            $tableSql[$record->name] = $record->sql;
            $rowidAliases[$record->name] = $definition['rowidAlias'];
            $tables[$record->name] = [];

            foreach ($database->tableRows($record->rootPage) as $tableRow) {
                $values = $tableRow->values();
                $row = [];
                foreach ($definition['columns'] as $index => $column) {
                    $row[$column] = $column === $definition['rowidAlias']
                        ? $tableRow->rowId
                        : ($values[$index] ?? null);
                }
                $tables[$record->name][] = $row;
            }
        }

        return [
            'columns' => $columns,
            'tables' => $tables,
            'tableSql' => $tableSql,
            'rowidAliases' => $rowidAliases,
            'schemaCookie' => $database->header->schemaCookie,
            'fileChangeCounter' => $database->header->fileChangeCounter,
        ];
    }

    /**
     * @return array{columns:list<string>,rowidAlias:string|null}
     */
    public static function parseCreateTableDefinition(string $sql): array
    {
        $sql = trim($sql);
        if (preg_match('/^create\s+table\s+[A-Za-z_][A-Za-z0-9_]*\s*\(/i', $sql) === 1
            && !str_ends_with($sql, ')')
        ) {
            throw new \InvalidArgumentException('incomplete input');
        }

        if (preg_match('/^create\s+table\s+([A-Za-z_][A-Za-z0-9_]*)\s*\((.*)\)$/is', $sql, $match) !== 1) {
            throw new \InvalidArgumentException('SQLitePDO CREATE TABLE support requires a simple column list');
        }

        $columns = [];
        $rowidAlias = null;
        foreach (self::splitTopLevel($match[2], ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '' || preg_match('/^(?:constraint\s+\S+\s+)?(?:primary|unique|check|foreign)\b/i', $definition) === 1) {
                continue;
            }
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(.*)$/s', $definition, $columnMatch) !== 1) {
                throw new \InvalidArgumentException('unrecognized token: "' . self::firstSqlToken($definition) . '"');
            }

            $column = $columnMatch[1];
            $tail = $columnMatch[2];
            $columns[] = $column;
            if (
                $rowidAlias === null
                && preg_match('/^\s+integer(?:\s|$)/i', $tail) === 1
                && preg_match('/\bprimary\s+key\b/i', $tail) === 1
                && preg_match('/\bprimary\s+key\s+desc\b/i', $tail) !== 1
            ) {
                $rowidAlias = $column;
            }
        }

        if ($columns === []) {
            throw new \InvalidArgumentException('near ")": syntax error');
        }

        return ['columns' => $columns, 'rowidAlias' => $rowidAlias];
    }

    private static function firstSqlToken(string $sql): string
    {
        if (preg_match('/^\s*([^\s,)]+)/', $sql, $match) === 1) {
            return $match[1];
        }

        return trim($sql);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $sql, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote) {
                if ($char === "'" && ($sql[$i + 1] ?? null) === "'") {
                    $i++;
                } elseif ($char === "'") {
                    $quote = false;
                }
                continue;
            }
            if ($char === "'") {
                $quote = true;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($char === $delimiter && $depth === 0) {
                $parts[] = trim(substr($sql, $start, $i - $start));
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($sql, $start));

        return $parts;
    }

    /**
     * This temporary SQL reconstruction is only used if an older in-memory
     * state lacks the original CREATE TABLE text before the full SQL AST writer
     * is available. Persisted file-backed PDO paths store native SQLite pages.
     *
     * @param list<string> $columns
     */
    private static function createSqlFor(string $table, array $columns): string
    {
        return 'CREATE TABLE ' . $table . ' (' . implode(', ', array_map(
            static fn (string $column): string => $column . ' BLOB',
            $columns,
        )) . ')';
    }

    private static function databaseHeader(int $pageCount, int $schemaCookie, int $fileChangeCounter): string
    {
        $header = str_repeat("\0", self::PAGE_SIZE);
        $header = substr_replace($header, "SQLite format 3\0", 0, 16);
        $header = substr_replace($header, pack('n', self::PAGE_SIZE), 16, 2);
        $header[18] = "\x01";
        $header[19] = "\x01";
        $header[20] = "\x00";
        $header[21] = "\x40";
        $header[22] = "\x20";
        $header[23] = "\x20";
        $header = substr_replace($header, pack('N', max(1, $fileChangeCounter)), 24, 4);
        $header = substr_replace($header, pack('N', $pageCount), 28, 4);
        $header = substr_replace($header, pack('N', 0), 32, 4);
        $header = substr_replace($header, pack('N', 0), 36, 4);
        $header = substr_replace($header, pack('N', max(0, $schemaCookie)), 40, 4);
        $header = substr_replace($header, pack('N', 4), 44, 4);
        $header = substr_replace($header, pack('N', 0), 48, 4);
        $header = substr_replace($header, pack('N', 0), 52, 4);
        $header = substr_replace($header, pack('N', 1), 56, 4);
        $header = substr_replace($header, pack('N', 0), 60, 4);
        $header = substr_replace($header, pack('N', 0), 64, 4);
        $header = substr_replace($header, pack('N', 0), 68, 4);
        $header = substr_replace($header, pack('N', max(1, $fileChangeCounter)), 92, 4);
        $header = substr_replace($header, pack('N', 3046000), 96, 4);

        return $header;
    }
}
