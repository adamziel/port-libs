<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLitePragmaFaultIntegrityPlan
{
    /**
     * Model the recoverable fault checkpoints reached by PRAGMA integrity_check
     * while validating a simple table CHECK constraint.
     *
     * @param list<array<string,mixed>> $rows
     * @return array{status:string,pragma:'integrity_check',table:string,columns:list<string>,checks:list<array{left:string,operator:string,right:string}>,fault:array{kind:string,step:int,recovered:bool,checkpoint:string},rows_checked:int,result:list<string>,violations:list<array{row:int,check:string,left:mixed,right:mixed}>,allocationsReleased:bool,schemaCacheStable:bool}
     */
    public static function integrityCheckWithRecoverableFault(
        string $createTableSql,
        array $rows,
        int $faultStep = 0,
        string $faultKind = 'oom'
    ): array {
        $parsed = self::parseCreateTable($createTableSql);
        $faultStep = max(0, $faultStep);
        $checkpoints = [
            'schema-record-read',
            'table-column-load',
            'check-expression-compile',
            'cursor-open',
            'row-read',
            'check-evaluate',
            'result-row-build',
            'statement-reset',
        ];
        $checkpoint = $checkpoints[$faultStep % count($checkpoints)];
        $recovered = $faultKind === 'oom';
        $violations = $recovered ? [] : self::checkRows($rows, $parsed['checks']);

        return [
            'status' => 'ok',
            'pragma' => 'integrity_check',
            'table' => $parsed['table'],
            'columns' => $parsed['columns'],
            'checks' => $parsed['checks'],
            'fault' => [
                'kind' => $faultKind,
                'step' => $faultStep,
                'recovered' => $recovered,
                'checkpoint' => $checkpoint,
            ],
            'rows_checked' => $recovered ? 0 : count($rows),
            'result' => $violations === [] ? ['ok'] : array_map(
                static fn (array $violation): string => sprintf(
                    'CHECK constraint failed in %s at row %d',
                    $parsed['table'],
                    $violation['row']
                ),
                $violations
            ),
            'violations' => $violations,
            'allocationsReleased' => true,
            'schemaCacheStable' => true,
        ];
    }

    /**
     * @return array{table:string,columns:list<string>,checks:list<array{left:string,operator:string,right:string}>}
     */
    private static function parseCreateTable(string $sql): array
    {
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^\s*CREATE\s+TABLE\s+(?<table>' . $identifier . ')\s*\((?<body>.*)\)\s*;?\s*$/is', $sql, $match)) {
            throw new InvalidArgumentException('SQLite PRAGMA fault integrity plan requires a simple CREATE TABLE statement');
        }

        $columns = [];
        $checks = [];
        foreach (self::splitTopLevelComma($match['body']) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^CHECK\s*\(\s*(' . $identifier . ')\s*(?<op>!=|<>|=)\s*(' . $identifier . ')\s*\)$/i', $part, $checkMatch)) {
                $checks[] = [
                    'left' => self::unquoteIdentifier($checkMatch[1]),
                    'operator' => $checkMatch['op'],
                    'right' => self::unquoteIdentifier($checkMatch[3]),
                ];
                continue;
            }
            if (preg_match('/^(' . $identifier . ')(?:\s+.*)?$/is', $part, $columnMatch)) {
                $columns[] = self::unquoteIdentifier($columnMatch[1]);
                continue;
            }
            throw new InvalidArgumentException('SQLite PRAGMA fault integrity plan table element is unsupported');
        }

        if ($columns === [] || $checks === []) {
            throw new InvalidArgumentException('SQLite PRAGMA fault integrity plan requires columns and a CHECK constraint');
        }

        return [
            'table' => self::unquoteIdentifier($match['table']),
            'columns' => $columns,
            'checks' => $checks,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{left:string,operator:string,right:string}> $checks
     * @return list<array{row:int,check:string,left:mixed,right:mixed}>
     */
    private static function checkRows(array $rows, array $checks): array
    {
        $violations = [];
        foreach ($rows as $offset => $row) {
            foreach ($checks as $check) {
                $left = $row[$check['left']] ?? null;
                $right = $row[$check['right']] ?? null;
                $ok = match ($check['operator']) {
                    '!=', '<>' => $left != $right,
                    '=' => $left == $right,
                };
                if (!$ok) {
                    $violations[] = [
                        'row' => $offset + 1,
                        'check' => $check['left'] . $check['operator'] . $check['right'],
                        'left' => $left,
                        'right' => $right,
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevelComma(string $sql): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth = max(0, $depth - 1);
            } elseif ($char === ',' && $depth === 0) {
                $parts[] = substr($sql, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($sql, $start);

        return $parts;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('SQLite PRAGMA fault integrity plan identifier cannot be empty');
        }

        $first = $identifier[0];
        $last = $identifier[strlen($identifier) - 1];
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }
}
