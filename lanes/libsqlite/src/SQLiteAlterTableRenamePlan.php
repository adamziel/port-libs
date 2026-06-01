<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteAlterTableRenamePlan
{
    /**
     * Rewrite schema SQL for ALTER TABLE old RENAME TO new.
     *
     * This is intentionally bounded to sqlite_schema text used by focused
     * trigger/view/table/index corpus tests. It preserves string literals,
     * comments, and object names for dependent triggers/views/indexes while
     * rewriting table references and qualified references.
     */
    public static function renameTableSql(string $sql, string $oldName, string $newName): string
    {
        self::assertIdentifier($oldName, 'old table name');
        self::assertIdentifier($newName, 'new table name');

        $tokens = self::tokens($sql);
        $result = '';
        $previousKeyword = null;

        foreach ($tokens as $index => $token) {
            if ($token['type'] === 'identifier') {
                $identifier = (string) $token['identifier'];
                if (
                    strcasecmp($identifier, $oldName) === 0
                    && !self::isObjectNamePosition($tokens, $index, $previousKeyword)
                ) {
                    $result .= self::renderIdentifier($newName, $token['quote']);
                } else {
                    $result .= $token['text'];
                }
            } else {
                $result .= $token['text'];
            }

            if ($token['type'] === 'keyword') {
                $previousKeyword = strtoupper($token['identifier']);
            } elseif ($token['type'] === 'identifier') {
                $previousKeyword = null;
            }
        }

        return $result;
    }

    /**
     * Rewrite schema SQL for ALTER TABLE table RENAME COLUMN old TO new.
     *
     * This bounded pass mirrors SQLite's schema-text rewrite for focused
     * sqlite_schema rows: table column declarations, index expressions and
     * predicates, view SELECT text, trigger UPDATE OF lists, and OLD/NEW
     * trigger references are rewritten while object names, string literals,
     * and comments are preserved.
     */
    public static function renameColumnSql(string $sql, string $tableName, string $oldName, string $newName): string
    {
        self::assertIdentifier($tableName, 'table name');
        self::assertColumnIdentifier($oldName, 'old column name');
        self::assertColumnIdentifier($newName, 'new column name');

        $tokens = self::tokens($sql);
        $result = '';
        $previousKeyword = null;

        foreach ($tokens as $index => $token) {
            if ($token['type'] === 'identifier') {
                $identifier = (string) $token['identifier'];
                if (
                    strcasecmp($identifier, $oldName) === 0
                    && !self::isObjectNamePosition($tokens, $index, $previousKeyword)
                    && !self::isFunctionNamePosition($tokens, $index)
                    && !self::isExplicitAliasPosition($tokens, $index)
                    && !self::isImplicitAliasPosition($tokens, $index)
                    && !self::isSourceNamePosition($tokens, $index)
                    && !self::isQualifierPosition($tokens, $index)
                ) {
                    $result .= self::renderIdentifier($newName, $token['quote']);
                } else {
                    $result .= $token['text'];
                }
            } else {
                $result .= $token['text'];
            }

            if ($token['type'] === 'keyword') {
                $previousKeyword = strtoupper($token['identifier']);
            } elseif ($token['type'] === 'identifier') {
                $previousKeyword = null;
            }
        }

        return $result;
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function isObjectNamePosition(array $tokens, int $index, ?string $previousKeyword): bool
    {
        if ($previousKeyword === null) {
            return false;
        }

        if (!in_array($previousKeyword, ['TRIGGER', 'VIEW', 'INDEX'], true)) {
            return false;
        }

        $nextKeyword = self::nextKeyword($tokens, $index + 1);
        if ($previousKeyword === 'TRIGGER') {
            return $nextKeyword === 'BEFORE'
                || $nextKeyword === 'AFTER'
                || $nextKeyword === 'INSTEAD'
                || $nextKeyword === 'ON';
        }

        if ($previousKeyword === 'VIEW') {
            return $nextKeyword === 'AS';
        }

        return $nextKeyword === 'ON';
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function nextKeyword(array $tokens, int $offset): ?string
    {
        $count = count($tokens);
        for ($i = $offset; $i < $count; $i++) {
            if ($tokens[$i]['type'] === 'keyword') {
                return strtoupper((string) $tokens[$i]['identifier']);
            }

            if ($tokens[$i]['type'] === 'identifier') {
                return null;
            }
        }

        return null;
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function isFunctionNamePosition(array $tokens, int $index): bool
    {
        $next = self::nextSignificant($tokens, $index + 1);
        if ($next === null || $next['text'] !== '(') {
            return false;
        }

        $previous = self::previousSignificant($tokens, $index - 1);
        return $previous === null || $previous['text'] !== '.';
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function isExplicitAliasPosition(array $tokens, int $index): bool
    {
        $previous = self::previousSignificant($tokens, $index - 1);

        return $previous !== null
            && $previous['type'] === 'keyword'
            && strcasecmp((string) $previous['identifier'], 'AS') === 0;
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function isImplicitAliasPosition(array $tokens, int $index): bool
    {
        $previous = self::previousSignificant($tokens, $index - 1);
        $next = self::nextSignificant($tokens, $index + 1);

        if ($previous === null || $next === null) {
            return false;
        }

        if (!self::canPrecedeImplicitAlias($previous)) {
            return false;
        }

        if ($next['type'] === 'keyword' && in_array(strtoupper((string) $next['identifier']), [
            'FROM', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET',
            'UNION', 'INTERSECT', 'EXCEPT', 'WINDOW',
        ], true)) {
            return true;
        }

        return in_array($next['text'], [',', ')', ';'], true);
    }

    /**
     * @param array{type:string,text:string,identifier?:string,quote?:string|null} $token
     */
    private static function canPrecedeImplicitAlias(array $token): bool
    {
        if ($token['type'] === 'identifier' || $token['type'] === 'literal') {
            return true;
        }

        if ($token['type'] === 'keyword') {
            return in_array(strtoupper((string) $token['identifier']), ['NULL', 'TRUE', 'FALSE'], true);
        }

        return in_array($token['text'], [')', ']'], true);
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function isSourceNamePosition(array $tokens, int $index): bool
    {
        $previous = self::previousSignificant($tokens, $index - 1);
        if ($previous !== null && $previous['type'] === 'keyword' && in_array(strtoupper((string) $previous['identifier']), [
            'FROM', 'JOIN', 'UPDATE', 'INTO',
        ], true)) {
            return true;
        }

        $asIndex = self::nextSignificantIndex($tokens, $index + 1);
        if ($asIndex !== null && $tokens[$asIndex]['type'] === 'keyword' && strtoupper((string) $tokens[$asIndex]['identifier']) === 'AS') {
            $afterAs = self::nextSignificant($tokens, $asIndex + 1);
            return $afterAs !== null && $afterAs['text'] === '(';
        }

        if ($previous !== null && $previous['type'] === 'identifier') {
            $previousIndex = self::previousSignificantIndex($tokens, $index - 1);
            $beforePrevious = $previousIndex === null ? null : self::previousSignificant($tokens, $previousIndex - 1);
            return $beforePrevious !== null
                && $beforePrevious['type'] === 'keyword'
                && in_array(strtoupper((string) $beforePrevious['identifier']), ['FROM', 'JOIN'], true);
        }

        if ($previous !== null && $previous['text'] === ')') {
            return true;
        }

        return false;
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function isQualifierPosition(array $tokens, int $index): bool
    {
        $next = self::nextSignificant($tokens, $index + 1);

        return $next !== null && $next['text'] === '.';
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     * @return array{type:string,text:string,identifier?:string,quote?:string|null}|null
     */
    private static function nextSignificant(array $tokens, int $offset): ?array
    {
        $index = self::nextSignificantIndex($tokens, $offset);

        return $index === null ? null : $tokens[$index];
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function nextSignificantIndex(array $tokens, int $offset): ?int
    {
        $count = count($tokens);
        for ($i = $offset; $i < $count; $i++) {
            if (!self::isTrivia($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     * @return array{type:string,text:string,identifier?:string,quote?:string|null}|null
     */
    private static function previousSignificant(array $tokens, int $offset): ?array
    {
        $index = self::previousSignificantIndex($tokens, $offset);

        return $index === null ? null : $tokens[$index];
    }

    /**
     * @param list<array{type:string,text:string,identifier?:string,quote?:string|null}> $tokens
     */
    private static function previousSignificantIndex(array $tokens, int $offset): ?int
    {
        for ($i = $offset; $i >= 0; $i--) {
            if (!self::isTrivia($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array{type:string,text:string,identifier?:string,quote?:string|null} $token
     */
    private static function isTrivia(array $token): bool
    {
        return $token['type'] === 'comment'
            || ($token['type'] === 'other' && trim($token['text']) === '');
    }

    /**
     * @return list<array{type:string,text:string,identifier?:string,quote?:string|null}>
     */
    private static function tokens(string $sql): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;

        while ($offset < $length) {
            $char = $sql[$offset];

            if ($char === "'") {
                [$text, $offset] = self::consumeQuoted($sql, $offset, "'");
                $tokens[] = ['type' => 'literal', 'text' => $text];
                continue;
            }

            if ($char === '"' || $char === '`') {
                [$text, $offset] = self::consumeQuoted($sql, $offset, $char);
                $tokens[] = ['type' => 'identifier', 'text' => $text, 'identifier' => str_replace($char . $char, $char, substr($text, 1, -1)), 'quote' => $char];
                continue;
            }

            if ($char === '[') {
                $end = strpos($sql, ']', $offset + 1);
                if ($end === false) {
                    throw new \InvalidArgumentException('SQLite ALTER TABLE rename encountered an unterminated bracket identifier');
                }
                $text = substr($sql, $offset, $end - $offset + 1);
                $tokens[] = ['type' => 'identifier', 'text' => $text, 'identifier' => substr($text, 1, -1), 'quote' => '['];
                $offset = $end + 1;
                continue;
            }

            if ($char === '-' && ($sql[$offset + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $offset + 2);
                if ($end === false) {
                    $tokens[] = ['type' => 'comment', 'text' => substr($sql, $offset)];
                    break;
                }
                $tokens[] = ['type' => 'comment', 'text' => substr($sql, $offset, $end - $offset + 1)];
                $offset = $end + 1;
                continue;
            }

            if ($char === '/' && ($sql[$offset + 1] ?? '') === '*') {
                $end = strpos($sql, '*/', $offset + 2);
                if ($end === false) {
                    throw new \InvalidArgumentException('SQLite ALTER TABLE rename encountered an unterminated block comment');
                }
                $tokens[] = ['type' => 'comment', 'text' => substr($sql, $offset, $end - $offset + 2)];
                $offset = $end + 2;
                continue;
            }

            if (preg_match('/[A-Za-z_]/', $char) === 1) {
                $end = $offset + 1;
                while ($end < $length && preg_match('/[A-Za-z0-9_$]/', $sql[$end]) === 1) {
                    $end++;
                }
                $text = substr($sql, $offset, $end - $offset);
                $type = self::isKeyword($text) ? 'keyword' : 'identifier';
                $tokens[] = ['type' => $type, 'text' => $text, 'identifier' => $text, 'quote' => null];
                $offset = $end;
                continue;
            }

            $tokens[] = ['type' => 'other', 'text' => $char];
            $offset++;
        }

        return $tokens;
    }

    /**
     * @return array{0:string,1:int}
     */
    private static function consumeQuoted(string $sql, int $offset, string $quote): array
    {
        $length = strlen($sql);
        $cursor = $offset + 1;

        while ($cursor < $length) {
            if ($sql[$cursor] === $quote) {
                if (($sql[$cursor + 1] ?? '') === $quote) {
                    $cursor += 2;
                    continue;
                }

                return [substr($sql, $offset, $cursor - $offset + 1), $cursor + 1];
            }
            $cursor++;
        }

        throw new \InvalidArgumentException('SQLite ALTER TABLE rename encountered an unterminated quoted token');
    }

    private static function renderIdentifier(string $identifier, mixed $quote): string
    {
        if ($quote === '"') {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        if ($quote === '`') {
            return '`' . str_replace('`', '``', $identifier) . '`';
        }

        if ($quote === '[') {
            return '[' . str_replace(']', ']]', $identifier) . ']';
        }

        if (!self::isBareIdentifier($identifier)) {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }

        return $identifier;
    }

    private static function assertIdentifier(string $identifier, string $label): void
    {
        if (!self::isBareIdentifier($identifier)) {
            throw new \InvalidArgumentException("SQLite ALTER TABLE rename {$label} is malformed");
        }
    }

    private static function assertColumnIdentifier(string $identifier, string $label): void
    {
        if (
            $identifier === ''
            || preg_match('/[^\x20-\x7E]/', $identifier) === 1
            || preg_match('/^[A-Za-z_][A-Za-z0-9_$]*(?: [A-Za-z0-9_$]+)*$/', $identifier) !== 1
        ) {
            throw new \InvalidArgumentException("SQLite ALTER TABLE rename {$label} is malformed");
        }
    }

    private static function isBareIdentifier(string $identifier): bool
    {
        return $identifier !== ''
            && preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $identifier) === 1;
    }

    private static function isKeyword(string $token): bool
    {
        return in_array(strtoupper($token), [
            'AFTER', 'AS', 'BEFORE', 'CREATE', 'DELETE', 'FROM', 'INDEX', 'INSERT',
            'INSTEAD', 'INTO', 'JOIN', 'ON', 'REFERENCES', 'SELECT', 'TABLE',
            'TRIGGER', 'UPDATE', 'VIEW', 'WHERE', 'GROUP', 'HAVING', 'ORDER',
            'LIMIT', 'OFFSET', 'UNION', 'INTERSECT', 'EXCEPT', 'WINDOW', 'WITH',
            'RECURSIVE', 'NULL', 'TRUE', 'FALSE', 'BY', 'PARTITION', 'OVER',
            'DISTINCT',
        ], true);
    }
}
