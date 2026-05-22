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
        $body = self::tableBody($sql);
        if ($body === null) {
            return [];
        }

        $columns = [];
        foreach (self::splitTopLevel($body, ',') as $definition) {
            $definition = trim($definition);
            if ($definition === '') {
                continue;
            }

            $constraint = self::stripLeadingConstraint($definition);
            if (self::startsWithKeyword($constraint, 'UNIQUE')) {
                $list = self::parenthesizedBodyAfterKeyword($constraint, 'UNIQUE');
                $firstColumn = $list === null ? null : self::firstIdentifierInList($list);
                if ($firstColumn !== null) {
                    $columns[] = $firstColumn;
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
            if (self::containsTopLevelKeyword($tail, 'UNIQUE')) {
                $columns[] = $column[0];
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

    private static function firstIdentifierInList(string $list): ?string
    {
        $items = self::splitTopLevel($list, ',');
        if ($items === []) {
            return null;
        }

        $identifier = self::readIdentifier(trim($items[0]), 0);

        return $identifier[0] ?? null;
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
