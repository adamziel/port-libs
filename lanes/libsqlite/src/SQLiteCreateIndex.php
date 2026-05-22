<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCreateIndex
{
    public static function firstColumn(string $sql): ?SQLiteIndexColumn
    {
        $columns = self::parseColumns($sql, 1);

        return $columns[0] ?? null;
    }

    /**
     * @return null|list<SQLiteIndexColumn>
     */
    public static function columns(string $sql): ?array
    {
        return self::parseColumns($sql, null);
    }

    /**
     * @return null|list<SQLiteIndexColumn>
     */
    private static function parseColumns(string $sql, ?int $limit): ?array
    {
        $onOffset = self::findTopLevelKeyword($sql, 'ON');
        if ($onOffset === null) {
            return null;
        }

        $offset = $onOffset + 2;
        $table = self::readIdentifier($sql, $offset);
        if ($table === null) {
            return null;
        }
        $offset = self::skipWhitespace($sql, $table[1]);
        if (isset($sql[$offset]) && $sql[$offset] === '.') {
            $schemaTable = self::readIdentifier($sql, $offset + 1);
            if ($schemaTable === null) {
                return null;
            }
            $offset = self::skipWhitespace($sql, $schemaTable[1]);
        }
        if (!isset($sql[$offset]) || $sql[$offset] !== '(') {
            return null;
        }

        $close = self::matchingParen($sql, $offset);
        if ($close === null) {
            return null;
        }

        $tail = substr($sql, $close + 1);
        $whereOffset = self::findTopLevelKeyword($tail, 'WHERE');
        $partial = $whereOffset !== null;
        $partialPredicate = $whereOffset === null
            ? null
            : self::parsePartialPredicate(substr($tail, $whereOffset + strlen('WHERE')));

        $columns = [];
        foreach (self::topLevelTerms(substr($sql, $offset + 1, $close - $offset - 1)) as $term) {
            if ($limit !== null && count($columns) >= $limit) {
                break;
            }
            $column = self::parseIndexedColumn($term);
            if ($column === null) {
                return null;
            }

            $columns[] = new SQLiteIndexColumn(
                $column['name'],
                $column['collation'],
                $column['descending'],
                $partial,
                $partialPredicate,
            );
        }

        return $columns === [] ? null : $columns;
    }

    /**
     * @return null|array{name:string,collation:string,descending:bool}
     */
    private static function parseIndexedColumn(string $term): ?array
    {
        $term = trim($term);
        $identifier = self::readIdentifier($term, 0);
        if ($identifier === null) {
            return null;
        }

        $offset = self::skipWhitespace($term, $identifier[1]);
        if (isset($term[$offset]) && $term[$offset] === '(') {
            return null;
        }

        $collation = 'BINARY';
        $descending = false;
        while ($offset < strlen($term)) {
            $token = self::readIdentifier($term, $offset);
            if ($token === null) {
                return null;
            }
            $keyword = strtoupper($token[0]);
            $offset = self::skipWhitespace($term, $token[1]);

            if ($keyword === 'COLLATE') {
                $collationToken = self::readIdentifier($term, $offset);
                if ($collationToken === null) {
                    return null;
                }
                $collation = strtoupper($collationToken[0]);
                $offset = self::skipWhitespace($term, $collationToken[1]);
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

        return [
            'name' => $identifier[0],
            'collation' => $collation,
            'descending' => $descending,
        ];
    }

    /**
     * @return list<string>
     */
    private static function topLevelTerms(string $text): array
    {
        $terms = [];
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
            if ($char === ',' && $depth === 0) {
                $terms[] = substr($text, $start, $i - $start);
                $start = $i + 1;
            }
        }
        $terms[] = substr($text, $start);

        return $terms;
    }

    private static function parsePartialPredicate(string $where): ?SQLiteIndexPredicate
    {
        $where = trim(self::stripOuterParens($where));
        $identifier = self::readPossiblyQualifiedIdentifier($where, 0);
        if ($identifier === null) {
            return null;
        }

        $offset = self::skipWhitespace($where, $identifier[1]);
        $is = self::readIdentifier($where, $offset);
        if ($is !== null && strcasecmp($is[0], 'IS') === 0) {
            $not = self::readIdentifier($where, $is[1]);
            if ($not === null || strcasecmp($not[0], 'NOT') !== 0) {
                return null;
            }

            $null = self::readIdentifier($where, $not[1]);
            if ($null === null || strcasecmp($null[0], 'NULL') !== 0) {
                return null;
            }

            if (trim(substr($where, $null[1])) !== '') {
                return null;
            }

            return new SQLiteIndexPredicate($identifier[0], SQLiteIndexPredicate::IS_NOT_NULL);
        }

        $equalsOffset = self::readEqualsOperator($where, $offset);
        if ($equalsOffset === null) {
            return null;
        }
        $literal = self::readLiteral($where, $equalsOffset);
        if ($literal === null || trim(substr($where, $literal[1])) !== '') {
            return null;
        }

        return new SQLiteIndexPredicate($identifier[0], SQLiteIndexPredicate::EQUALS, $literal[0]);
    }

    private static function readEqualsOperator(string $text, int $offset): ?int
    {
        $offset = self::skipWhitespace($text, $offset);
        if (substr($text, $offset, 2) === '==') {
            return $offset + 2;
        }
        if (($text[$offset] ?? null) === '=') {
            return $offset + 1;
        }

        return null;
    }

    /**
     * @return null|array{0:mixed,1:int}
     */
    private static function readLiteral(string $text, int $offset): ?array
    {
        $offset = self::skipWhitespace($text, $offset);
        if ($offset >= strlen($text)) {
            return null;
        }
        if ($text[$offset] === "'") {
            $end = self::skipQuoted($text, $offset, "'");
            if ($end <= $offset || $text[$end] !== "'") {
                return null;
            }

            return [str_replace("''", "'", substr($text, $offset + 1, $end - $offset - 1)), $end + 1];
        }
        if (preg_match('/[+-]?(?:\d+\.\d*|\.\d+)(?:[eE][+-]?\d+)?/A', substr($text, $offset), $matches)) {
            return [(float) $matches[0], $offset + strlen($matches[0])];
        }
        if (preg_match('/[+-]?\d+/A', substr($text, $offset), $matches)) {
            return [(int) $matches[0], $offset + strlen($matches[0])];
        }

        return null;
    }

    private static function stripOuterParens(string $text): string
    {
        $text = trim($text);
        while ($text !== '' && $text[0] === '(') {
            $close = self::matchingParen($text, 0);
            if ($close !== strlen($text) - 1) {
                return $text;
            }
            $text = trim(substr($text, 1, -1));
        }

        return $text;
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readPossiblyQualifiedIdentifier(string $text, int $offset): ?array
    {
        $identifier = self::readIdentifier($text, $offset);
        if ($identifier === null) {
            return null;
        }

        $lastName = $identifier[0];
        $offset = self::skipWhitespace($text, $identifier[1]);
        while (isset($text[$offset]) && $text[$offset] === '.') {
            $next = self::readIdentifier($text, $offset + 1);
            if ($next === null) {
                return null;
            }
            $lastName = $next[0];
            $offset = self::skipWhitespace($text, $next[1]);
        }

        return [$lastName, $offset];
    }

    private static function findTopLevelKeyword(string $text, string $keyword): ?int
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
                return $i;
            }
        }

        return null;
    }

    /**
     * @return null|array{0:string,1:int}
     */
    private static function readIdentifier(string $text, int $offset): ?array
    {
        $offset = self::skipWhitespace($text, $offset);
        if ($offset >= strlen($text)) {
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

    private static function skipWhitespace(string $text, int $offset): int
    {
        while (isset($text[$offset]) && ctype_space($text[$offset])) {
            $offset++;
        }

        return $offset;
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
