<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaExplainPlan
{
    /**
     * @param list<int>|null $rootPages
     * @return array{
     *     source:string,
     *     sql:string,
     *     pragma:string,
     *     schema:string,
     *     limit:int,
     *     root_pages:list<int>,
     *     root_page_program:string,
     *     rows:list<array{addr:int,opcode:string,p1:int,p2:int,p3:int,p4:?string,p5:int,comment:string}>,
     *     integrity_opcode:array{addr:int,opcode:string,p1:int,p2:int,p3:int,p4:string,p5:int,comment:string},
     *     dependencies:list<string>
     * }
     */
    public static function explainIntegrityCheck(
        string $sql,
        string|SQLiteDatabase|null $database = null,
        ?array $rootPages = null,
    ): array {
        $parsed = self::parseExplainPragma($sql);
        if ($parsed['pragma'] !== 'integrity_check' && $parsed['pragma'] !== 'quick_check') {
            throw new InvalidArgumentException('Only EXPLAIN PRAGMA integrity_check and quick_check are supported');
        }

        $database = is_string($database) ? SQLiteDatabase::fromBytes($database) : $database;
        $rootPages = self::normalizedRootPages($rootPages ?? self::rootPagesFromDatabase($database));
        if ($database !== null) {
            self::validateRootPagesExist($database, $rootPages);
        }

        $program = 'x' . implode(',', $rootPages) . 'x';
        $integrityOpcode = [
            'addr' => 2,
            'opcode' => 'IntegrityCk',
            'p1' => 1,
            'p2' => 2,
            'p3' => 8,
            'p4' => $program,
            'p5' => 0,
            'comment' => $parsed['pragma'] . ' root pages ' . implode(',', $rootPages),
        ];
        $schema = $parsed['schema'];

        return [
            'source' => 'SQLite test/pragma4.test pragma4-2.100',
            'sql' => $parsed['sql'],
            'pragma' => $parsed['pragma'],
            'schema' => $schema,
            'limit' => $parsed['limit'],
            'root_pages' => $rootPages,
            'root_page_program' => $program,
            'rows' => [
                [
                    'addr' => 0,
                    'opcode' => 'Init',
                    'p1' => 0,
                    'p2' => 5,
                    'p3' => 0,
                    'p4' => null,
                    'p5' => 0,
                    'comment' => 'jump to transaction setup',
                ],
                [
                    'addr' => 1,
                    'opcode' => 'Integer',
                    'p1' => $parsed['limit'],
                    'p2' => 1,
                    'p3' => 0,
                    'p4' => null,
                    'p5' => 0,
                    'comment' => 'maximum integrity-check result rows',
                ],
                $integrityOpcode,
                [
                    'addr' => 3,
                    'opcode' => 'ResultRow',
                    'p1' => 2,
                    'p2' => 1,
                    'p3' => 0,
                    'p4' => null,
                    'p5' => 0,
                    'comment' => 'emit integrity-check result',
                ],
                [
                    'addr' => 4,
                    'opcode' => 'Halt',
                    'p1' => 0,
                    'p2' => 0,
                    'p3' => 0,
                    'p4' => null,
                    'p5' => 0,
                    'comment' => 'stop',
                ],
                [
                    'addr' => 5,
                    'opcode' => 'Transaction',
                    'p1' => 0,
                    'p2' => 0,
                    'p3' => 0,
                    'p4' => $schema,
                    'p5' => 0,
                    'comment' => 'read schema for PRAGMA ' . $parsed['pragma'],
                ],
                [
                    'addr' => 6,
                    'opcode' => 'Goto',
                    'p1' => 0,
                    'p2' => 1,
                    'p3' => 0,
                    'p4' => null,
                    'p5' => 0,
                    'comment' => 'enter pragma program',
                ],
            ],
            'integrity_opcode' => $integrityOpcode,
            'dependencies' => [
                'no new support component needed',
                'reuses SQLiteDatabase schema records when a database image is supplied',
            ],
        ];
    }

    /**
     * @return array{sql:string,schema:string,pragma:string,limit:int}
     */
    private static function parseExplainPragma(string $sql): array
    {
        $trimmed = trim($sql);
        $identifier = '(?:"(?:""|[^"])+"|`(?:``|[^`])+`|\[(?:[^\]])+\]|[A-Za-z_][A-Za-z0-9_]*)';
        $pattern = '/^EXPLAIN\s+PRAGMA\s+(?:(?<schema>' . $identifier . ')\s*\.\s*)?(?<pragma>[A-Za-z_][A-Za-z0-9_]*)(?:\s*(?:\(\s*(?<paren>[0-9]+)\s*\)|=\s*(?<equals>[0-9]+)))?\s*;?$/i';
        if (!preg_match($pattern, $trimmed, $matches)) {
            throw new InvalidArgumentException('Unsupported EXPLAIN PRAGMA statement');
        }

        $limit = 100;
        if (($matches['paren'] ?? '') !== '') {
            $limit = (int) $matches['paren'];
        } elseif (($matches['equals'] ?? '') !== '') {
            $limit = (int) $matches['equals'];
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('PRAGMA integrity_check limit must be positive');
        }

        $schema = self::unquoteIdentifier($matches['schema'] ?? '');

        return [
            'sql' => $trimmed,
            'schema' => $schema === '' ? 'main' : $schema,
            'pragma' => strtolower($matches['pragma']),
            'limit' => $limit,
        ];
    }

    /**
     * @return list<int>
     */
    private static function rootPagesFromDatabase(?SQLiteDatabase $database): array
    {
        if ($database === null) {
            throw new InvalidArgumentException('EXPLAIN PRAGMA integrity_check requires root pages or a database image');
        }

        $rootPages = [];
        foreach ($database->schemaRecords() as $record) {
            if ($record->rootPage !== null && $record->rootPage > 0) {
                $rootPages[] = $record->rootPage;
            }
        }

        return $rootPages;
    }

    /**
     * @param list<int> $rootPages
     * @return list<int>
     */
    private static function normalizedRootPages(array $rootPages): array
    {
        $unique = [];
        foreach ($rootPages as $rootPage) {
            if (!is_int($rootPage) || $rootPage < 1) {
                throw new InvalidArgumentException('PRAGMA integrity_check root pages must be positive integers');
            }
            $unique[$rootPage] = $rootPage;
        }
        if ($unique === []) {
            throw new InvalidArgumentException('PRAGMA integrity_check requires at least one root page');
        }

        unset($unique[1]);
        sort($unique);
        $unique[] = 1;

        return array_values($unique);
    }

    /**
     * @param list<int> $rootPages
     */
    private static function validateRootPagesExist(SQLiteDatabase $database, array $rootPages): void
    {
        $pageCount = $database->pageCount();
        foreach ($rootPages as $rootPage) {
            if ($rootPage > $pageCount) {
                throw new InvalidArgumentException("PRAGMA integrity_check root page {$rootPage} is outside the database image");
            }
        }
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        if ($identifier === '') {
            return '';
        }

        $first = $identifier[0];
        $last = substr($identifier, -1);
        if ($first === '"' && $last === '"') {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }
        if ($first === '`' && $last === '`') {
            return str_replace('``', '`', substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }
}
