<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCreateTable
{
    /**
     * @return list<string>
     */
    public static function uniqueAutoIndexFirstColumns(string $sql): array
    {
        return array_map(
            static fn (array $columns): string => $columns[0]->columnName,
            self::automaticIndexColumns($sql, false),
        );
    }

    /**
     * @return list<string>
     */
    public static function automaticIndexFirstColumns(string $sql): array
    {
        return array_map(
            static fn (array $columns): string => $columns[0]->columnName,
            self::automaticIndexColumns($sql, true),
        );
    }

    /**
     * @return list<SQLiteIndexColumn>
     */
    public static function automaticIndexFirstColumnMetadata(string $sql): array
    {
        return array_map(
            static fn (array $columns): SQLiteIndexColumn => $columns[0],
            self::automaticIndexColumns($sql, true),
        );
    }

    /**
     * @return list<non-empty-list<SQLiteIndexColumn>>
     */
    public static function automaticIndexColumnMetadata(string $sql): array
    {
        return self::automaticIndexColumns($sql, true);
    }

    /**
     * @return list<non-empty-list<SQLiteIndexColumn>>
     */
    private static function automaticIndexColumns(string $sql, bool $includePrimaryKey): array
    {
        $body = self::tableBody($sql);
        if ($body === null) {
            return [];
        }

        $withoutRowid = self::isWithoutRowidTable($sql);
        $definitions = self::splitTopLevel($body, ',');
        $columnTypes = self::columnDeclaredTypes($definitions);
        $columnCollations = self::columnDeclaredCollations($definitions);
        $columns = [];
        $seen = [];

        $addConstraint = static function (array $constraintColumns) use (&$columns, &$seen): void {
            if ($constraintColumns === []) {
                return;
            }
            $key = implode("\0", array_map(
                static fn (SQLiteIndexColumn $column): string => strtolower($column->columnName) . "\1" . strtoupper($column->collation),
                $constraintColumns,
            ));
            if (isset($seen[$key])) {
                return;
            }

            $seen[$key] = true;
            $columns[] = $constraintColumns;
        };

        foreach ($definitions as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            $constraint = self::stripLeadingConstraint($definition);
            if (self::startsWithKeyword($constraint, 'UNIQUE')) {
                $list = self::parenthesizedBodyAfterKeyword($constraint, 'UNIQUE');
                $addConstraint($list === null ? [] : self::indexedColumnsInList($list, $columnCollations));
                continue;
            }
            if ($includePrimaryKey && self::startsWithPrimaryKey($constraint)) {
                $list = self::parenthesizedBodyAfterPrimaryKey($constraint);
                $primaryKeyColumns = $list === null ? [] : self::indexedColumnsInList($list, $columnCollations);
                if (!$withoutRowid && !self::isRowidAliasTablePrimaryKey($primaryKeyColumns, $columnTypes)) {
                    $addConstraint($primaryKeyColumns);
                }

                continue;
            }

            if (
                self::startsWithKeyword($constraint, 'PRIMARY')
                || self::startsWithKeyword($constraint, 'CHECK')
                || self::startsWithKeyword($constraint, 'FOREIGN')
            ) {
                continue;
            }

            $column = self::readIdentifier($definition, 0);
            if ($column === null) {
                continue;
            }

            $tail = substr($definition, $column[1]);
            $constraintColumns = [self::indexedColumnForDeclaredColumn($column[0], $columnCollations)];
            if (self::containsTopLevelKeyword($tail, 'UNIQUE')) {
                $addConstraint($constraintColumns);
            }
            if (
                $includePrimaryKey
                && !$withoutRowid
                && self::containsTopLevelPrimaryKey($tail)
                && !self::isRowidAliasColumnPrimaryKey($definition, $column[1], $tail)
            ) {
                $addConstraint([
                    self::indexedColumnForDeclaredColumn(
                        $column[0],
                        $columnCollations,
                        self::topLevelPrimaryKeyDirection($tail) === 'DESC',
                    ),
                ]);
            }
        }

        return $columns;
    }

    private static function tableBody(string $sql): ?string
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return null;
        }

        $close = self::matchingParen($sql, $open);
        if ($close === null) {
            return null;
        }

        return substr($sql, $open + 1, $close - $open - 1);
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $text, string $delimiter): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
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
            if ($char === $delimiter && $depth === 0) {
                $parts[] = substr($text, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $parts[] = substr($text, $start);

        return $parts;
    }

    private static function stripLeadingConstraint(string $definition): string
    {
        $trimmed = ltrim($definition);
        if (!self::startsWithKeyword($trimmed, 'CONSTRAINT')) {
            return $trimmed;
        }

        $offset = strlen('CONSTRAINT');
        $name = self::readIdentifier($trimmed, $offset);
        if ($name === null) {
            return $trimmed;
        }

        return ltrim(substr($trimmed, $name[1]));
    }

    private static function startsWithKeyword(string $text, string $keyword): bool
    {
        $text = ltrim($text);
        $length = strlen($keyword);
        if (strncasecmp($text, $keyword, $length) !== 0) {
            return false;
        }
        if (strlen($text) === $length) {
            return true;
        }

        return !self::isIdentifierChar($text[$length]);
    }

    private static function parenthesizedBodyAfterKeyword(string $text, string $keyword): ?string
    {
        $text = ltrim($text);
        $offset = strlen($keyword);
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        if (!isset($text[$offset]) || $text[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($text, $offset);
        if ($close === null) {
            return null;
        }

        return substr($text, $offset + 1, $close - $offset - 1);
    }

    /**
     * @param array<string, string> $columnCollations
     * @return list<SQLiteIndexColumn>
     */
    private static function indexedColumnsInList(string $list, array $columnCollations): array
    {
        $columns = [];
        foreach (self::splitTopLevel($list, ',') as $item) {
            $column = self::indexedColumnInListItem($item, $columnCollations);
            if ($column === null) {
                return [];
            }
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param array<string, string> $columnCollations
     */
    private static function indexedColumnInListItem(string $item, array $columnCollations): ?SQLiteIndexColumn
    {
        $item = trim($item);
        $identifier = self::readIdentifier($item, 0);
        if ($identifier === null) {
            return null;
        }

        $columnName = $identifier[0];
        $collation = $columnCollations[strtolower($columnName)] ?? 'BINARY';
        $descending = false;
        $offset = $identifier[1];
        while (trim(substr($item, $offset)) !== '') {
            $token = self::readIdentifier($item, $offset);
            if ($token === null) {
                return null;
            }

            $keyword = strtoupper($token[0]);
            $offset = $token[1];
            if ($keyword === 'COLLATE') {
                $collationToken = self::readIdentifier($item, $offset);
                if ($collationToken === null) {
                    return null;
                }

                $collation = strtoupper($collationToken[0]);
                $offset = $collationToken[1];
                continue;
            }
            if ($keyword === 'ASC') {
                $descending = false;
                continue;
            }
            if ($keyword === 'DESC') {
                $descending = true;
                continue;
            }

            return null;
        }

        return new SQLiteIndexColumn($columnName, $collation, $descending, false);
    }

    /**
     * @param array<string, string> $columnCollations
     */
    private static function indexedColumnForDeclaredColumn(
        string $columnName,
        array $columnCollations,
        bool $descending = false,
    ): SQLiteIndexColumn {
        return new SQLiteIndexColumn(
            $columnName,
            $columnCollations[strtolower($columnName)] ?? 'BINARY',
            $descending,
            false,
        );
    }

    private static function containsTopLevelKeyword(string $text, string $keyword): bool
    {
        $depth = 0;
        $length = strlen($text);
        $keywordLength = strlen($keyword);
        for ($i = 0; $i < $length; $i++) {
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
                return true;
            }
        }

        return false;
    }

    private static function containsTopLevelPrimaryKey(string $text): bool
    {
        return self::topLevelPrimaryKeyEndOffset($text) !== null;
    }

    private static function startsWithPrimaryKey(string $text): bool
    {
        $text = ltrim($text);
        if (!self::startsWithKeyword($text, 'PRIMARY')) {
            return false;
        }

        $offset = strlen('PRIMARY');
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }

        return self::startsWithKeyword(substr($text, $offset), 'KEY');
    }

    private static function parenthesizedBodyAfterPrimaryKey(string $text): ?string
    {
        $text = ltrim($text);
        if (!self::startsWithKeyword($text, 'PRIMARY')) {
            return null;
        }

        $offset = strlen('PRIMARY');
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        if (!self::startsWithKeyword(substr($text, $offset), 'KEY')) {
            return null;
        }
        $offset += strlen('KEY');
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        if (!isset($text[$offset]) || $text[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($text, $offset);
        if ($close === null) {
            return null;
        }

        return substr($text, $offset + 1, $close - $offset - 1);
    }

    private static function topLevelPrimaryKeyEndOffset(string $text): ?int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
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
            if ($depth !== 0 || !self::startsWithKeyword(substr($text, $i), 'PRIMARY')) {
                continue;
            }

            $offset = $i + strlen('PRIMARY');
            while (isset($text[$offset]) && ctype_space($text[$offset])) {
                $offset++;
            }
            if (self::startsWithKeyword(substr($text, $offset), 'KEY')) {
                return $offset + strlen('KEY');
            }
        }

        return null;
    }

    private static function topLevelPrimaryKeyDirection(string $text): ?string
    {
        $offset = self::topLevelPrimaryKeyEndOffset($text);
        if ($offset === null) {
            return null;
        }

        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }
        $direction = self::readIdentifier($text, $offset);
        if ($direction === null) {
            return null;
        }
        $value = strtoupper($direction[0]);

        return $value === 'ASC' || $value === 'DESC' ? $value : null;
    }

    /**
     * @param list<string> $definitions
     * @return array<string, string>
     */
    private static function columnDeclaredTypes(array $definitions): array
    {
        $types = [];
        foreach ($definitions as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }
            $constraint = self::stripLeadingConstraint($definition);
            if (
                self::startsWithKeyword($constraint, 'PRIMARY')
                || self::startsWithKeyword($constraint, 'UNIQUE')
                || self::startsWithKeyword($constraint, 'CHECK')
                || self::startsWithKeyword($constraint, 'FOREIGN')
            ) {
                continue;
            }

            $column = self::readIdentifier($definition, 0);
            if ($column === null) {
                continue;
            }
            $types[strtolower($column[0])] = self::columnDeclaredType($definition, $column[1]);
        }

        return $types;
    }

    private static function columnDeclaredType(string $definition, int $offset): string
    {
        $tail = ltrim(substr($definition, $offset));
        if ($tail === '') {
            return '';
        }
        if (
            preg_match(
                '/\b(CONSTRAINT|PRIMARY|NOT|NULL|UNIQUE|CHECK|DEFAULT|COLLATE|REFERENCES|GENERATED|AS)\b/i',
                $tail,
                $matches,
                PREG_OFFSET_CAPTURE,
            ) === 1
        ) {
            $tail = substr($tail, 0, $matches[0][1]);
        }

        return trim(preg_replace('/\s+/', ' ', $tail) ?? $tail);
    }

    /**
     * @param list<string> $definitions
     * @return array<string, string>
     */
    private static function columnDeclaredCollations(array $definitions): array
    {
        $collations = [];
        foreach ($definitions as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }
            $constraint = self::stripLeadingConstraint($definition);
            if (
                self::startsWithKeyword($constraint, 'PRIMARY')
                || self::startsWithKeyword($constraint, 'UNIQUE')
                || self::startsWithKeyword($constraint, 'CHECK')
                || self::startsWithKeyword($constraint, 'FOREIGN')
            ) {
                continue;
            }

            $column = self::readIdentifier($definition, 0);
            if ($column === null) {
                continue;
            }

            $collation = self::columnDeclaredCollation($definition, $column[1]);
            if ($collation !== null) {
                $collations[strtolower($column[0])] = $collation;
            }
        }

        return $collations;
    }

    private static function columnDeclaredCollation(string $definition, int $offset): ?string
    {
        $collation = null;
        $depth = 0;
        $length = strlen($definition);
        $keyword = 'COLLATE';
        $keywordLength = strlen($keyword);
        for ($i = $offset; $i < $length; $i++) {
            $char = $definition[$i];
            if ($char === "'" || $char === '"' || $char === '`') {
                $i = self::skipQuoted($definition, $i, $char);
                continue;
            }
            if ($char === '[') {
                $i = self::skipBracketQuoted($definition, $i);
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
                && strncasecmp(substr($definition, $i, $keywordLength), $keyword, $keywordLength) === 0
                && ($i === 0 || !self::isIdentifierChar($definition[$i - 1]))
                && (!isset($definition[$i + $keywordLength]) || !self::isIdentifierChar($definition[$i + $keywordLength]))
            ) {
                $token = self::readIdentifier($definition, $i + $keywordLength);
                if ($token === null) {
                    return $collation;
                }

                $collation = strtoupper($token[0]);
                $i = $token[1] - 1;
            }
        }

        return $collation;
    }

    private static function isRowidAliasColumnPrimaryKey(string $definition, int $columnNameEnd, string $tail): bool
    {
        if (!self::isExactIntegerType(self::columnDeclaredType($definition, $columnNameEnd))) {
            return false;
        }

        return self::topLevelPrimaryKeyDirection($tail) !== 'DESC';
    }

    /**
     * @param list<SQLiteIndexColumn> $primaryKeyColumns
     * @param array<string, string> $columnTypes
     */
    private static function isRowidAliasTablePrimaryKey(array $primaryKeyColumns, array $columnTypes): bool
    {
        if (count($primaryKeyColumns) !== 1) {
            return false;
        }

        return self::isExactIntegerType($columnTypes[strtolower($primaryKeyColumns[0]->columnName)] ?? '');
    }

    private static function isExactIntegerType(string $type): bool
    {
        $type = trim($type);
        if (
            (str_starts_with($type, '"') && str_ends_with($type, '"'))
            || (str_starts_with($type, '`') && str_ends_with($type, '`'))
            || (str_starts_with($type, '[') && str_ends_with($type, ']'))
        ) {
            $type = substr($type, 1, -1);
        }

        return strcasecmp($type, 'INTEGER') === 0;
    }

    private static function isWithoutRowidTable(string $sql): bool
    {
        $open = strpos($sql, '(');
        if ($open === false) {
            return false;
        }
        $close = self::matchingParen($sql, $open);
        if ($close === null) {
            return false;
        }

        return preg_match('/\bWITHOUT\s+ROWID\b/i', substr($sql, $close + 1)) === 1;
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readIdentifier(string $text, int $offset): ?array
    {
        $length = strlen($text);
        while ($offset < $length && ctype_space($text[$offset])) {
            $offset++;
        }
        if ($offset >= $length) {
            return null;
        }

        $quote = $text[$offset];
        if ($quote === '"' || $quote === '`' || $quote === "'") {
            $end = self::skipQuoted($text, $offset, $quote);

            return [str_replace($quote . $quote, $quote, substr($text, $offset + 1, $end - $offset - 1)), $end + 1];
        }
        if ($quote === '[') {
            $end = self::skipBracketQuoted($text, $offset);

            return [substr($text, $offset + 1, $end - $offset - 1), $end + 1];
        }
        if (!preg_match('/[A-Za-z_][A-Za-z0-9_$]*/A', substr($text, $offset), $matches)) {
            return null;
        }

        return [$matches[0], $offset + strlen($matches[0])];
    }

    private static function matchingParen(string $text, int $openOffset): ?int
    {
        $depth = 0;
        $length = strlen($text);
        for ($i = $openOffset; $i < $length; $i++) {
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
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function skipQuoted(string $text, int $offset, string $quote): int
    {
        $length = strlen($text);
        for ($i = $offset + 1; $i < $length; $i++) {
            if ($text[$i] !== $quote) {
                continue;
            }
            if (isset($text[$i + 1]) && $text[$i + 1] === $quote) {
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

        return $end === false ? strlen($text) - 1 : $end;
    }

    private static function isIdentifierChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '$';
    }
}
