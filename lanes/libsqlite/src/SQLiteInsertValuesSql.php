<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteInsertValuesSql
{
    /**
     * @return array{target:string,columns:?list<string>,tuples:list<list<string>>}
     */
    public static function parse(string $sql): array
    {
        $sql = trim(rtrim(trim($sql), ';'));
        $offset = 0;
        if (self::tryReadKeyword($sql, $offset, 'replace') === false) {
            self::readKeyword($sql, $offset, 'insert');
            self::readOptionalConflictClause($sql, $offset);
        }
        self::readKeyword($sql, $offset, 'into');
        $target = self::readIdentifier($sql, $offset, 'SQLite INSERT target table');
        $offset = self::skipWhitespace($sql, $offset);

        $columns = null;
        if (($sql[$offset] ?? null) === '(') {
            [$columnSql, $offset] = self::consumeParenthesized($sql, $offset, 'SQLite INSERT column list');
            $columns = [];
            foreach (self::splitTopLevel($columnSql, ',') as $column) {
                $columns[] = self::readCompleteIdentifier($column, 'SQLite INSERT target column');
            }
            if ($columns === []) {
                throw new \InvalidArgumentException('SQLite INSERT target column list cannot be empty');
            }
            if (count(array_unique($columns)) !== count($columns)) {
                throw new \InvalidArgumentException('SQLite INSERT target columns must be unique');
            }
        }

        self::readKeyword($sql, $offset, 'values');
        $tuples = self::readTuples($sql, $offset);
        if ($tuples === []) {
            throw new \InvalidArgumentException('SQLite INSERT VALUES requires at least one tuple');
        }

        return [
            'target' => $target,
            'columns' => $columns,
            'tuples' => $tuples,
        ];
    }

    public static function startsWithInsertKeyword(string $sql): bool
    {
        $sql = ltrim($sql);
        foreach (['insert', 'replace'] as $keyword) {
            if (strtolower(substr($sql, 0, strlen($keyword))) !== $keyword) {
                continue;
            }

            $next = $sql[strlen($keyword)] ?? '';

            return $next === '' || (!ctype_alnum($next) && $next !== '_');
        }

        return false;
    }

    private static function readKeyword(string $sql, int &$offset, string $keyword): void
    {
        $offset = self::skipWhitespace($sql, $offset);
        $length = strlen($keyword);
        if (strtolower(substr($sql, $offset, $length)) !== $keyword) {
            throw new \InvalidArgumentException('SQLite INSERT SQL expected ' . strtoupper($keyword));
        }

        $next = $sql[$offset + $length] ?? '';
        if ($next !== '' && (ctype_alnum($next) || $next === '_')) {
            throw new \InvalidArgumentException('SQLite INSERT SQL expected ' . strtoupper($keyword));
        }
        $offset += $length;
    }

    private static function tryReadKeyword(string $sql, int &$offset, string $keyword): bool
    {
        $checkpoint = $offset;
        try {
            self::readKeyword($sql, $offset, $keyword);

            return true;
        } catch (\InvalidArgumentException) {
            $offset = $checkpoint;

            return false;
        }
    }

    private static function readOptionalConflictClause(string $sql, int &$offset): void
    {
        if (self::tryReadKeyword($sql, $offset, 'or') === false) {
            return;
        }

        foreach (['rollback', 'abort', 'replace', 'fail', 'ignore'] as $algorithm) {
            if (self::tryReadKeyword($sql, $offset, $algorithm)) {
                return;
            }
        }

        throw new \InvalidArgumentException('SQLite INSERT conflict algorithm is malformed');
    }

    private static function readIdentifier(string $sql, int &$offset, string $label): string
    {
        $offset = self::skipWhitespace($sql, $offset);
        $first = $sql[$offset] ?? '';
        if ($first === '' || (!ctype_alpha($first) && $first !== '_')) {
            throw new \InvalidArgumentException("{$label} is malformed");
        }

        $start = $offset;
        $offset++;
        $length = strlen($sql);
        while ($offset < $length) {
            $char = $sql[$offset];
            if (!ctype_alnum($char) && $char !== '_') {
                break;
            }
            $offset++;
        }

        return substr($sql, $start, $offset - $start);
    }

    private static function readCompleteIdentifier(string $sql, string $label): string
    {
        $sql = trim($sql);
        $offset = 0;
        $identifier = self::readIdentifier($sql, $offset, $label);
        $offset = self::skipWhitespace($sql, $offset);
        if ($offset !== strlen($sql)) {
            throw new \InvalidArgumentException("{$label} is malformed");
        }

        return $identifier;
    }

    /**
     * @return list<list<string>>
     */
    private static function readTuples(string $sql, int $offset): array
    {
        $tuples = [];
        $length = strlen($sql);
        while (true) {
            $offset = self::skipWhitespace($sql, $offset);
            if ($offset >= $length) {
                break;
            }

            if (($sql[$offset] ?? null) !== '(') {
                throw new \InvalidArgumentException('SQLite INSERT VALUES tuple is malformed');
            }
            [$tupleSql, $offset] = self::consumeParenthesized($sql, $offset, 'SQLite INSERT VALUES tuple');
            $values = self::splitTopLevel($tupleSql, ',');
            foreach ($values as $value) {
                if (trim($value) === '') {
                    throw new \InvalidArgumentException('SQLite INSERT VALUES tuple is malformed');
                }
            }
            $tuples[] = $values;

            $offset = self::skipWhitespace($sql, $offset);
            if ($offset >= $length) {
                break;
            }
            if (($sql[$offset] ?? null) !== ',') {
                throw new \InvalidArgumentException('SQLite INSERT VALUES tuple list is malformed');
            }
            $offset++;
        }

        return $tuples;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function consumeParenthesized(string $sql, int $offset, string $label): array
    {
        $close = self::matchingParen($sql, $offset);
        if ($close === null) {
            throw new \InvalidArgumentException("{$label} is malformed");
        }

        return [substr($sql, $offset + 1, $close - $offset - 1), $close + 1];
    }

    private static function matchingParen(string $sql, int $offset): ?int
    {
        if (($sql[$offset] ?? null) !== '(') {
            return null;
        }

        $depth = 0;
        $quote = false;
        $length = strlen($sql);
        for ($i = $offset; $i < $length; $i++) {
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

    private static function skipWhitespace(string $sql, int $offset): int
    {
        $length = strlen($sql);
        while ($offset < $length && ctype_space($sql[$offset])) {
            $offset++;
        }

        return $offset;
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
}
