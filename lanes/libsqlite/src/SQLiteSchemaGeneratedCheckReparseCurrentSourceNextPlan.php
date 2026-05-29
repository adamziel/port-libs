<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan
{
    /**
     * @param list<SQLiteSchemaRecord> $currentRecords
     * @param list<SQLiteSchemaRecord> $nextRecords
     * @return array<string,mixed>
     */
    public static function currentNext(array $currentRecords, array $nextRecords, string $tableName, array $options = []): array
    {
        $schemaVersionBefore = self::nonNegativeInt($options['schema_version_before'] ?? 0, 'schema_version_before');
        $schemaVersionAfter = self::nonNegativeInt($options['schema_version_after'] ?? ($schemaVersionBefore + 1), 'schema_version_after');
        $current = self::snapshot(self::findTable($currentRecords, $tableName));
        $next = self::snapshot(self::findTable($nextRecords, $tableName));

        $changedFields = [];
        foreach (['columns', 'generatedColumns', 'generatedChecks', 'tableChecks', 'generatedCheckReferences', 'status'] as $field) {
            if (($current[$field] ?? null) !== ($next[$field] ?? null)) {
                $changedFields[] = $field;
            }
        }

        $schemaCookieChanged = $schemaVersionBefore !== $schemaVersionAfter;
        $generatedChecksAdded = self::diffChecks($next['generatedChecks'], $current['generatedChecks']);
        $generatedChecksRemoved = self::diffChecks($current['generatedChecks'], $next['generatedChecks']);
        $tableChecksAdded = self::diffStrings($next['tableChecks'], $current['tableChecks']);
        $tableChecksRemoved = self::diffStrings($current['tableChecks'], $next['tableChecks']);
        $requiresReparse = $schemaCookieChanged && $changedFields !== [];

        return [
            'operation' => 'schema-generated-check-reparse-current-source',
            'table' => $next['table'],
            'schema_version_before' => $schemaVersionBefore,
            'schema_version_after' => $schemaVersionAfter,
            'schema_cookie_changed' => $schemaCookieChanged,
            'current' => $current,
            'next' => $next,
            'changed' => $changedFields !== [],
            'changedFields' => $changedFields,
            'generatedAdded' => self::diffStrings($next['generatedColumns'], $current['generatedColumns']),
            'generatedRemoved' => self::diffStrings($current['generatedColumns'], $next['generatedColumns']),
            'generatedChecksAdded' => $generatedChecksAdded,
            'generatedChecksRemoved' => $generatedChecksRemoved,
            'tableChecksAdded' => $tableChecksAdded,
            'tableChecksRemoved' => $tableChecksRemoved,
            'requiresReparse' => $requiresReparse,
            'status' => $requiresReparse ? 'reparse-required' : 'stable',
            'dependencies' => [
                'sqlite-schema-ddl-reparse',
                'sqlite-generated-column-catalog',
                'sqlite-check-constraint-catalog',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function snapshot(SQLiteSchemaRecord $record): array
    {
        if ($record->sql === null || trim($record->sql) === '') {
            throw new InvalidArgumentException('SQLite generated CHECK reparse requires table SQL');
        }

        $parsed = self::parseCreateTable($record->sql);
        $generatedColumns = [];
        $generatedChecks = [];
        foreach ($parsed['columns'] as $column) {
            if (!$column['generated']) {
                continue;
            }
            $generatedColumns[] = $column['name'];
            foreach ($column['checks'] as $check) {
                $generatedChecks[] = [
                    'column' => $column['name'],
                    'storage' => $column['storage'],
                    'expression' => $column['expression'],
                    'check' => $check,
                    'references' => self::referencedColumns($check, array_column($parsed['columns'], 'name')),
                ];
            }
        }

        return [
            'table' => $parsed['table'],
            'columns' => array_column($parsed['columns'], 'name'),
            'generatedColumns' => $generatedColumns,
            'generatedDetails' => array_values(array_filter($parsed['columns'], static fn (array $column): bool => $column['generated'])),
            'generatedChecks' => $generatedChecks,
            'tableChecks' => $parsed['tableChecks'],
            'generatedCheckReferences' => self::uniqueReferences($generatedChecks),
            'status' => $generatedChecks === [] ? 'no-generated-checks' : 'resolved',
        ];
    }

    /**
     * @param list<SQLiteSchemaRecord> $records
     */
    private static function findTable(array $records, string $tableName): SQLiteSchemaRecord
    {
        foreach ($records as $record) {
            if (strtolower($record->type) === 'table' && strcasecmp($record->name, $tableName) === 0) {
                return $record;
            }
        }

        throw new InvalidArgumentException("SQLite schema table not found: {$tableName}");
    }

    /**
     * @return array{table:string,columns:list<array{name:string,type:string,generated:bool,storage:?string,expression:?string,checks:list<string>}>,tableChecks:list<string>}
     */
    private static function parseCreateTable(string $sql): array
    {
        if (!preg_match('/\bcreate\s+(?:temp(?:orary)?\s+)?table\s+(?:if\s+not\s+exists\s+)?(?:(?<schema>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s*\.\s*)?(?<table>"[^"]+"|`[^`]+`|\[[^\]]+\]|\w+)\s*\((?<body>.*)\)/is', $sql, $matches)) {
            throw new InvalidArgumentException('SQLite generated CHECK reparse requires CREATE TABLE SQL');
        }

        $columns = [];
        $tableChecks = [];
        foreach (self::splitTopLevel($matches['body'], ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }
            $constraint = self::stripLeadingConstraint($definition);
            if (self::startsWithKeyword($constraint, 'CHECK')) {
                $check = self::parenthesizedBodyAfterKeyword($constraint, 'CHECK');
                if ($check !== null) {
                    $tableChecks[] = trim($check);
                }
                continue;
            }
            if (
                self::startsWithKeyword($constraint, 'PRIMARY')
                || self::startsWithKeyword($constraint, 'UNIQUE')
                || self::startsWithKeyword($constraint, 'FOREIGN')
            ) {
                continue;
            }

            $identifier = self::readIdentifier($definition, 0);
            if ($identifier === null) {
                continue;
            }

            $tail = substr($definition, $identifier[1]);
            $expression = self::generatedExpression($tail);
            $columns[] = [
                'name' => $identifier[0],
                'type' => self::declaredType($tail),
                'generated' => $expression !== null,
                'storage' => $expression === null ? null : (self::containsTopLevelKeyword($tail, 'STORED') ? 'STORED' : 'VIRTUAL'),
                'expression' => $expression,
                'checks' => self::columnChecks($tail),
            ];
        }

        return [
            'table' => self::unquoteIdentifier($matches['table']),
            'columns' => $columns,
            'tableChecks' => $tableChecks,
        ];
    }

    /**
     * @return list<string>
     */
    private static function columnChecks(string $tail): array
    {
        $checks = [];
        $offset = 0;
        while (($checkOffset = self::topLevelKeywordOffset($tail, 'CHECK', $offset)) !== null) {
            $open = strpos($tail, '(', $checkOffset + strlen('CHECK'));
            if ($open === false) {
                break;
            }
            $close = self::matchingParen($tail, $open);
            if ($close === null) {
                break;
            }
            $checks[] = trim(substr($tail, $open + 1, $close - $open - 1));
            $offset = $close + 1;
        }

        return $checks;
    }

    private static function generatedExpression(string $tail): ?string
    {
        $offset = self::topLevelKeywordOffset($tail, 'AS');
        if ($offset === null) {
            return null;
        }
        $before = substr($tail, 0, $offset);
        if (!self::containsTopLevelKeyword($before, 'GENERATED') && trim($before) !== '') {
            $last = trim((string) preg_replace('/\s+/', ' ', $before));
            if (!preg_match('/(?:^|\s)(?:text|integer|real|blob|numeric|varchar|boolean)\b/i', $last)) {
                return null;
            }
        }
        $open = strpos($tail, '(', $offset + 2);
        if ($open === false) {
            return null;
        }
        $close = self::matchingParen($tail, $open);
        if ($close === null) {
            return null;
        }

        return trim(substr($tail, $open + 1, $close - $open - 1));
    }

    private static function declaredType(string $tail): string
    {
        $tail = ltrim($tail);
        if ($tail === '') {
            return '';
        }
        if (preg_match('/\b(CONSTRAINT|PRIMARY|NOT|NULL|UNIQUE|CHECK|DEFAULT|COLLATE|REFERENCES|GENERATED|AS)\b/i', $tail, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $tail = substr($tail, 0, $matches[0][1]);
        }

        return trim((string) preg_replace('/\s+/', ' ', $tail));
    }

    /**
     * @param list<string> $columns
     * @return list<string>
     */
    private static function referencedColumns(string $expression, array $columns): array
    {
        $available = array_fill_keys(array_map('strtolower', $columns), true);
        preg_match_all('/(?<![.\w])("[^"]+"|`[^`]+`|\[[^\]]+\]|[A-Za-z_][A-Za-z0-9_]*)/i', $expression, $matches);
        $references = [];
        foreach ($matches[1] ?? [] as $token) {
            $name = self::unquoteIdentifier($token);
            $lower = strtolower($name);
            if (!isset($available[$lower])) {
                continue;
            }
            $references[$lower] = $name;
        }

        return array_values($references);
    }

    /**
     * @param list<array{references:list<string>}> $checks
     * @return list<string>
     */
    private static function uniqueReferences(array $checks): array
    {
        $references = [];
        foreach ($checks as $check) {
            foreach ($check['references'] as $reference) {
                $references[strtolower($reference)] = $reference;
            }
        }
        ksort($references);

        return array_values($references);
    }

    /**
     * @param list<array<string,mixed>> $left
     * @param list<array<string,mixed>> $right
     * @return list<array<string,mixed>>
     */
    private static function diffChecks(array $left, array $right): array
    {
        $seen = [];
        foreach ($right as $check) {
            $seen[self::checkKey($check)] = true;
        }

        return array_values(array_filter($left, static fn (array $check): bool => !isset($seen[self::checkKey($check)])));
    }

    /**
     * @param array<string,mixed> $check
     */
    private static function checkKey(array $check): string
    {
        return strtolower((string) ($check['column'] ?? '')) . "\0" . (string) ($check['check'] ?? '');
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     * @return list<string>
     */
    private static function diffStrings(array $left, array $right): array
    {
        $seen = array_fill_keys($right, true);

        return array_values(array_filter($left, static fn (string $value): bool => !isset($seen[$value])));
    }

    private static function stripLeadingConstraint(string $definition): string
    {
        $trimmed = ltrim($definition);
        if (!self::startsWithKeyword($trimmed, 'CONSTRAINT')) {
            return $trimmed;
        }
        $offset = strlen('CONSTRAINT');
        $name = self::readIdentifier($trimmed, $offset);

        return $name === null ? $trimmed : ltrim(substr($trimmed, $name[1]));
    }

    private static function parenthesizedBodyAfterKeyword(string $text, string $keyword): ?string
    {
        $offset = self::topLevelKeywordOffset($text, $keyword);
        if ($offset === null) {
            return null;
        }
        $open = strpos($text, '(', $offset + strlen($keyword));
        if ($open === false) {
            return null;
        }
        $close = self::matchingParen($text, $open);

        return $close === null ? null : substr($text, $open + 1, $close - $open - 1);
    }

    private static function containsTopLevelKeyword(string $text, string $keyword): bool
    {
        return self::topLevelKeywordOffset($text, $keyword) !== null;
    }

    private static function topLevelKeywordOffset(string $text, string $keyword, int $start = 0): ?int
    {
        $depth = 0;
        $length = strlen($text);
        $keywordLength = strlen($keyword);
        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($text, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($text, $i);
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')' && $depth > 0) {
                $depth--;
                continue;
            }
            if (
                $depth === 0
                && strncasecmp(substr($text, $i, $keywordLength), $keyword, $keywordLength) === 0
                && ($i === 0 || !self::isIdentifierChar($text[$i - 1]))
                && (!isset($text[$i + $keywordLength]) || !self::isIdentifierChar($text[$i + $keywordLength]))
            ) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $delimiter): array
    {
        $parts = [];
        $current = '';
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === $quote) {
                    if (($value[$i + 1] ?? '') === $quote) {
                        $current .= $value[++$i];
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                $current .= $char;
                continue;
            }
            if ($char === '[') {
                $end = strpos($value, ']', $i + 1);
                if ($end === false) {
                    throw new InvalidArgumentException('Unterminated bracket quoted identifier');
                }
                $current .= substr($value, $i, $end - $i + 1);
                $i = $end;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }
            if ($char === $delimiter && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return $parts;
    }

    private static function matchingParen(string $value, int $open): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($value);
        for ($i = $open; $i < $length; $i++) {
            $char = $value[$i];
            if ($quote !== null) {
                if ($char === $quote) {
                    if (($value[$i + 1] ?? '') === $quote) {
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }
            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0:string,1:int}|null
     */
    private static function readIdentifier(string $text, int $offset): ?array
    {
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        if (!isset($text[$offset])) {
            return null;
        }
        $char = $text[$offset];
        if ($char === '"' || $char === '`') {
            $end = self::skipQuoted($text, $offset, $char);

            return [self::unquoteIdentifier(substr($text, $offset, $end - $offset + 1)), $end + 1];
        }
        if ($char === '[') {
            $end = self::skipBracketQuoted($text, $offset);

            return [self::unquoteIdentifier(substr($text, $offset, $end - $offset + 1)), $end + 1];
        }
        if (!preg_match('/[A-Za-z_]/', $char)) {
            return null;
        }
        $end = $offset + 1;
        while (isset($text[$end]) && self::isIdentifierChar($text[$end])) {
            $end++;
        }

        return [substr($text, $offset, $end - $offset), $end];
    }

    private static function startsWithKeyword(string $text, string $keyword): bool
    {
        $text = ltrim($text);
        $length = strlen($keyword);
        if (strncasecmp($text, $keyword, $length) !== 0) {
            return false;
        }

        return strlen($text) === $length || !self::isIdentifierChar($text[$length]);
    }

    private static function skipQuoted(string $text, int $offset, string $quote): int
    {
        $length = strlen($text);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($text[$i] !== $quote) {
                continue;
            }
            if (($text[$i + 1] ?? '') === $quote) {
                $i++;
                continue;
            }

            return $i;
        }

        return $length - 1;
    }

    private static function skipBracketQuoted(string $text, int $offset): int
    {
        $end = strpos($text, ']', $offset + 1);
        if ($end === false) {
            throw new InvalidArgumentException('Unterminated bracket quoted identifier');
        }

        return $end;
    }

    private static function unquoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return '';
        }
        $first = $identifier[0];
        $last = substr($identifier, -1);
        if (($first === '"' && $last === '"') || ($first === '`' && $last === '`')) {
            return str_replace($first . $first, $first, substr($identifier, 1, -1));
        }
        if ($first === '[' && $last === ']') {
            return substr($identifier, 1, -1);
        }

        return $identifier;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_';
    }

    private static function nonNegativeInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value < 0) {
            throw new InvalidArgumentException("SQLite {$name} must be a non-negative integer");
        }

        return $value;
    }
}
