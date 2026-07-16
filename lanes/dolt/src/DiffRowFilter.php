<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class DiffRowFilter
{
    /** @var list<array{type:string, value:mixed}> */
    private array $tokens = [];
    private int $position = 0;
    private string $source = '';

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return list<array<string, scalar|null>>
     */
    public function apply(array $rows, ?string $where = null, ?int $limit = null): array
    {
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('Diff row limit must be a non-negative integer.');
        }

        $rows = array_values($rows);
        if ($where !== null && trim($where) !== '') {
            $rows = $this->filterWhere($rows, $where);
        }

        if ($limit !== null) {
            return array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return list<array<string, scalar|null>>
     */
    private function filterWhere(array $rows, string $where): array
    {
        $this->tokens = $this->tokenize($where);
        $this->position = 0;
        $this->source = $where;

        try {
            $expression = $this->parseExpression();
            $this->expect('eof');
        } catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException("Invalid where clause: {$where}", 0, $e);
        }

        $columns = $this->availableColumns($rows);
        if ($columns !== []) {
            $this->validateColumns($expression, $columns);
        }

        $filtered = [];
        foreach ($rows as $row) {
            if ($this->evaluate($expression, $row, $columns)) {
                $filtered[] = $row;
            }
        }

        return $filtered;
    }

    /**
     * @return list<array{type:string, value:mixed}>
     */
    private function tokenize(string $where): array
    {
        $tokens = [];
        $length = strlen($where);
        $i = 0;

        while ($i < $length) {
            $ch = $where[$i];
            if (ctype_space($ch)) {
                $i++;
                continue;
            }

            if ($ch === '(') {
                $tokens[] = ['type' => 'lparen', 'value' => $ch];
                $i++;
                continue;
            }
            if ($ch === ')') {
                $tokens[] = ['type' => 'rparen', 'value' => $ch];
                $i++;
                continue;
            }

            if ($ch === '`') {
                [$identifier, $i] = $this->readBacktickIdentifier($where, $i + 1);
                $tokens[] = ['type' => 'ident', 'value' => $identifier];
                continue;
            }

            if ($ch === '\'' || $ch === '"') {
                [$string, $i] = $this->readQuotedString($where, $i + 1, $ch);
                $tokens[] = ['type' => 'string', 'value' => $string];
                continue;
            }

            if ($ch === '-' || ctype_digit($ch)) {
                [$number, $i] = $this->readNumber($where, $i);
                $tokens[] = ['type' => 'number', 'value' => $number];
                continue;
            }

            if ($this->isIdentifierStart($ch)) {
                $start = $i;
                $i++;
                while ($i < $length && $this->isIdentifierPart($where[$i])) {
                    $i++;
                }
                $identifier = substr($where, $start, $i - $start);
                $upper = strtoupper($identifier);
                if (in_array($upper, ['AND', 'OR', 'IS', 'NOT', 'NULL'], true)) {
                    $tokens[] = ['type' => 'keyword', 'value' => $upper];
                } else {
                    $tokens[] = ['type' => 'ident', 'value' => $identifier];
                }
                continue;
            }

            $twoChar = substr($where, $i, 2);
            if (in_array($twoChar, ['<=', '>=', '<>', '!='], true)) {
                $tokens[] = ['type' => 'op', 'value' => $twoChar];
                $i += 2;
                continue;
            }
            if (in_array($ch, ['=', '<', '>'], true)) {
                $tokens[] = ['type' => 'op', 'value' => $ch];
                $i++;
                continue;
            }

            throw new \InvalidArgumentException('Unexpected token.');
        }

        $tokens[] = ['type' => 'eof', 'value' => null];

        return $tokens;
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function readBacktickIdentifier(string $where, int $i): array
    {
        $identifier = '';
        $length = strlen($where);
        while ($i < $length) {
            if ($where[$i] === '`') {
                if (($where[$i + 1] ?? '') === '`') {
                    $identifier .= '`';
                    $i += 2;
                    continue;
                }

                if ($identifier === '') {
                    throw new \InvalidArgumentException('Empty identifier.');
                }

                return [$identifier, $i + 1];
            }
            $identifier .= $where[$i];
            $i++;
        }

        throw new \InvalidArgumentException('Unterminated identifier.');
    }

    /**
     * @return array{0:string, 1:int}
     */
    private function readQuotedString(string $where, int $i, string $quote): array
    {
        $value = '';
        $length = strlen($where);
        while ($i < $length) {
            if ($where[$i] === $quote) {
                if (($where[$i + 1] ?? '') === $quote) {
                    $value .= $quote;
                    $i += 2;
                    continue;
                }

                return [$value, $i + 1];
            }
            if ($where[$i] === '\\' && $i + 1 < $length) {
                $value .= $where[$i + 1];
                $i += 2;
                continue;
            }

            $value .= $where[$i];
            $i++;
        }

        throw new \InvalidArgumentException('Unterminated string.');
    }

    /**
     * @return array{0:int|float, 1:int}
     */
    private function readNumber(string $where, int $i): array
    {
        $start = $i;
        $length = strlen($where);
        if ($where[$i] === '-') {
            $i++;
            if ($i >= $length || !ctype_digit($where[$i])) {
                throw new \InvalidArgumentException('Invalid number.');
            }
        }

        while ($i < $length && ctype_digit($where[$i])) {
            $i++;
        }
        if (($where[$i] ?? '') === '.') {
            $i++;
            if ($i >= $length || !ctype_digit($where[$i])) {
                throw new \InvalidArgumentException('Invalid number.');
            }
            while ($i < $length && ctype_digit($where[$i])) {
                $i++;
            }
        }

        $raw = substr($where, $start, $i - $start);

        return [str_contains($raw, '.') ? (float) $raw : (int) $raw, $i];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseExpression(): array
    {
        return $this->parseOr();
    }

    /**
     * @return array<string, mixed>
     */
    private function parseOr(): array
    {
        $left = $this->parseAnd();
        while ($this->matchKeyword('OR')) {
            $left = ['kind' => 'or', 'left' => $left, 'right' => $this->parseAnd()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseAnd(): array
    {
        $left = $this->parsePrimary();
        while ($this->matchKeyword('AND')) {
            $left = ['kind' => 'and', 'left' => $left, 'right' => $this->parsePrimary()];
        }

        return $left;
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePrimary(): array
    {
        if ($this->match('lparen') !== null) {
            $expression = $this->parseExpression();
            $this->expect('rparen');

            return $expression;
        }

        return $this->parsePredicate();
    }

    /**
     * @return array<string, mixed>
     */
    private function parsePredicate(): array
    {
        $column = $this->expect('ident')['value'];
        if ($this->matchKeyword('IS')) {
            $not = $this->matchKeyword('NOT');
            $this->expectKeyword('NULL');

            return ['kind' => 'is_null', 'column' => $column, 'not' => $not];
        }

        $operator = $this->expect('op')['value'];
        $value = $this->parseValue();

        return ['kind' => 'comparison', 'column' => $column, 'operator' => $operator, 'value' => $value];
    }

    private function parseValue(): int|float|string|null
    {
        $token = $this->peek();
        if ($token['type'] === 'number' || $token['type'] === 'string') {
            $this->position++;

            return $token['value'];
        }
        if ($token['type'] === 'keyword' && $token['value'] === 'NULL') {
            $this->position++;

            return null;
        }

        throw new \InvalidArgumentException('Expected literal.');
    }

    /**
     * @param array<string, scalar|null> $row
     * @param array<string, non-empty-string> $columns
     * @param array<string, mixed> $expression
     */
    private function evaluate(array $expression, array $row, array $columns): bool
    {
        return match ($expression['kind']) {
            'and' => $this->evaluate($expression['left'], $row, $columns)
                && $this->evaluate($expression['right'], $row, $columns),
            'or' => $this->evaluate($expression['left'], $row, $columns)
                || $this->evaluate($expression['right'], $row, $columns),
            'is_null' => $this->evaluateNullPredicate($expression, $row, $columns),
            'comparison' => $this->evaluateComparison($expression, $row, $columns),
            default => throw new \LogicException('Unknown expression kind.'),
        };
    }

    /**
     * @param array<string, mixed> $expression
     * @param array<string, scalar|null> $row
     * @param array<string, non-empty-string> $columns
     */
    private function evaluateNullPredicate(array $expression, array $row, array $columns): bool
    {
        $isNull = $this->columnValue($row, $expression['column'], $columns) === null;

        return $expression['not'] ? !$isNull : $isNull;
    }

    /**
     * @param array<string, mixed> $expression
     * @param array<string, scalar|null> $row
     * @param array<string, non-empty-string> $columns
     */
    private function evaluateComparison(array $expression, array $row, array $columns): bool
    {
        $left = $this->columnValue($row, $expression['column'], $columns);
        $right = $expression['value'];
        if ($left === null || $right === null) {
            return false;
        }

        [$left, $right, $numeric] = $this->normalizeComparisonValues($left, $right);
        $comparison = $numeric ? ($left <=> $right) : strcmp((string) $left, (string) $right);

        return match ($expression['operator']) {
            '=' => $comparison === 0,
            '!=', '<>' => $comparison !== 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            default => throw new \LogicException('Unknown comparison operator.'),
        };
    }

    /**
     * @return array{0:int|float|string|bool, 1:int|float|string|bool, 2:bool}
     */
    private function normalizeComparisonValues(int|float|string|bool $left, int|float|string|bool $right): array
    {
        if ($this->isNumericComparable($left) && $this->isNumericComparable($right)) {
            return [(float) $left, (float) $right, true];
        }

        return [$left, $right, false];
    }

    private function isNumericComparable(int|float|string|bool $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && is_numeric($value);
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @return array<string, non-empty-string>
     */
    private function availableColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                if ($column !== '') {
                    $columns[strtolower($column)] = $column;
                }
            }
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $expression
     * @param array<string, non-empty-string> $columns
     */
    private function validateColumns(array $expression, array $columns): void
    {
        if ($expression['kind'] === 'and' || $expression['kind'] === 'or') {
            $this->validateColumns($expression['left'], $columns);
            $this->validateColumns($expression['right'], $columns);
            return;
        }

        $column = strtolower((string) $expression['column']);
        if (!isset($columns[$column])) {
            throw new \InvalidArgumentException("Column {$expression['column']} could not be found.");
        }
    }

    /**
     * @param array<string, scalar|null> $row
     * @param array<string, non-empty-string> $columns
     */
    private function columnValue(array $row, string $column, array $columns): int|float|string|bool|null
    {
        $key = strtolower($column);
        if (!isset($columns[$key])) {
            throw new \InvalidArgumentException("Column {$column} could not be found.");
        }

        return $row[$columns[$key]] ?? null;
    }

    /**
     * @return array{type:string, value:mixed}|null
     */
    private function match(string $type): ?array
    {
        if ($this->peek()['type'] !== $type) {
            return null;
        }

        return $this->tokens[$this->position++];
    }

    private function matchKeyword(string $keyword): bool
    {
        $token = $this->peek();
        if ($token['type'] !== 'keyword' || $token['value'] !== $keyword) {
            return false;
        }

        $this->position++;

        return true;
    }

    /**
     * @return array{type:string, value:mixed}
     */
    private function expect(string $type): array
    {
        $token = $this->match($type);
        if ($token === null) {
            throw new \InvalidArgumentException("Expected {$type}.");
        }

        return $token;
    }

    private function expectKeyword(string $keyword): void
    {
        if (!$this->matchKeyword($keyword)) {
            throw new \InvalidArgumentException("Expected {$keyword}.");
        }
    }

    /**
     * @return array{type:string, value:mixed}
     */
    private function peek(): array
    {
        return $this->tokens[$this->position] ?? ['type' => 'eof', 'value' => null];
    }

    private function isIdentifierStart(string $ch): bool
    {
        return ctype_alpha($ch) || $ch === '_';
    }

    private function isIdentifierPart(string $ch): bool
    {
        return ctype_alnum($ch) || $ch === '_';
    }
}
