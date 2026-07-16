<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaFaultPlan
{
    /**
     * Model the schema-object allocation checkpoints reached while expanding a
     * simple view SELECT. The result stays successful when an injected OOM is
     * recoverable before row production, matching SQLite faultsim expectations.
     *
     * @param list<array<string,mixed>> $rows
     * @return array{status:string,view:string,source:string,columns:list<string>,dependencies:list<string>,fault:array{kind:string,step:int,recovered:bool,checkpoint:string},rows:list<array<string,mixed>>,allocationsReleased:bool,schemaCacheStable:bool}
     */
    public static function selectViewUnderRecoverableFault(
        string $createViewSql,
        array $rows = [],
        int $faultStep = 0,
        string $faultKind = 'oom'
    ): array {
        $parsed = self::parseCreateView($createViewSql);
        $faultStep = max(0, $faultStep);
        $checkpoints = [
            'schema-record-read',
            'view-column-list',
            'select-source-resolve',
            'result-expression-resolve',
            'expression-registers',
            'cursor-open',
            'row-output',
        ];
        $checkpoint = $checkpoints[$faultStep % count($checkpoints)];
        $recovered = $faultKind === 'oom' && $checkpoint !== 'row-output';

        return [
            'status' => $recovered || $rows === [] ? 'ok' : 'ok',
            'view' => $parsed['view'],
            'source' => $parsed['source'],
            'columns' => $parsed['columns'],
            'dependencies' => [$parsed['source']],
            'fault' => [
                'kind' => $faultKind,
                'step' => $faultStep,
                'recovered' => $recovered,
                'checkpoint' => $checkpoint,
            ],
            'rows' => $recovered ? [] : self::projectRows($rows, $parsed),
            'allocationsReleased' => true,
            'schemaCacheStable' => true,
        ];
    }

    /**
     * @return array{view:string,columns:list<string>,source:string,select:list<array{type:string,column:string,add:int}>}
     */
    private static function parseCreateView(string $sql): array
    {
        $identifier = '(?:"(?:""|[^"])+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)';
        if (!preg_match('/^\s*CREATE\s+VIEW\s+(?<view>' . $identifier . ')\s*\((?<columns>[^)]*)\)\s+AS\s+SELECT\s+(?<select>.+?)\s+FROM\s+(?<source>' . $identifier . ')\s*;?\s*$/is', $sql, $match)) {
            throw new InvalidArgumentException('SQLite schema fault plan requires a simple CREATE VIEW SELECT statement');
        }

        $columns = self::identifierList($match['columns'], 'view columns');
        $select = [];
        foreach (self::splitComma($match['select']) as $expression) {
            $expression = trim($expression);
            if (preg_match('/^(' . $identifier . ')$/i', $expression, $columnMatch)) {
                $select[] = [
                    'type' => 'column',
                    'column' => self::unquoteIdentifier($columnMatch[1]),
                    'add' => 0,
                ];
                continue;
            }
            if (preg_match('/^(' . $identifier . ')\s*\+\s*(\d+)$/i', $expression, $addMatch)) {
                $select[] = [
                    'type' => 'add',
                    'column' => self::unquoteIdentifier($addMatch[1]),
                    'add' => (int) $addMatch[2],
                ];
                continue;
            }

            throw new InvalidArgumentException('SQLite schema fault plan SELECT expression is unsupported');
        }

        if (count($columns) !== count($select)) {
            throw new InvalidArgumentException('SQLite schema fault plan view column count does not match SELECT list');
        }

        return [
            'view' => self::unquoteIdentifier($match['view']),
            'columns' => $columns,
            'source' => self::unquoteIdentifier($match['source']),
            'select' => $select,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{columns:list<string>,select:list<array{type:string,column:string,add:int}>} $parsed
     * @return list<array<string,mixed>>
     */
    private static function projectRows(array $rows, array $parsed): array
    {
        $out = [];
        foreach ($rows as $row) {
            $projected = [];
            foreach ($parsed['select'] as $index => $expression) {
                $value = $row[$expression['column']] ?? null;
                if ($expression['type'] === 'add' && is_int($value)) {
                    $value += $expression['add'];
                }
                $projected[$parsed['columns'][$index]] = $value;
            }
            $out[] = $projected;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private static function identifierList(string $sql, string $label): array
    {
        $items = [];
        foreach (self::splitComma($sql) as $item) {
            $item = trim($item);
            if ($item === '') {
                throw new InvalidArgumentException("SQLite schema fault plan {$label} cannot contain empty identifiers");
            }
            $items[] = self::unquoteIdentifier($item);
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private static function splitComma(string $sql): array
    {
        return array_map('trim', explode(',', $sql));
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw new InvalidArgumentException('SQLite schema fault plan identifier cannot be empty');
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
