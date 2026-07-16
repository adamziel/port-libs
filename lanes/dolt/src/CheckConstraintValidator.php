<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class CheckConstraintValidator
{
    /**
     * @param list<array<string, scalar|null>> $rows
     * @return list<array{table_name:string, row_index:int, violation_type:string, constraint_name:string, expression:string, row:array<string, scalar|null>}>
     */
    public function violations(TableSchema $schema, array $rows, string $tableName): array
    {
        if ($tableName === '') {
            throw new \InvalidArgumentException('Check constraint table name must be non-empty.');
        }

        $violations = [];
        foreach (array_values($rows) as $rowIndex => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('Check constraint rows must contain row arrays.');
            }
            foreach ($schema->checks() as $check) {
                if (!$check['enforced']) {
                    continue;
                }
                if ($this->evaluate($check['expression'], $row) !== false) {
                    continue;
                }

                $violations[] = [
                    'table_name' => $tableName,
                    'row_index' => $rowIndex,
                    'violation_type' => 'check constraint',
                    'constraint_name' => $check['name'],
                    'expression' => $check['expression'],
                    'row' => $row,
                ];
            }
        }

        return $violations;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function evaluate(string $expression, array $row): ?bool
    {
        $expression = $this->stripOuterParentheses(trim($expression));
        $orParts = $this->splitTopLevelKeyword($expression, 'or');
        if (count($orParts) > 1) {
            $hasUnknown = false;
            foreach ($orParts as $part) {
                $value = $this->evaluate($part, $row);
                if ($value === true) {
                    return true;
                }
                $hasUnknown = $hasUnknown || $value === null;
            }

            return $hasUnknown ? null : false;
        }

        $andParts = $this->splitTopLevelKeyword($expression, 'and');
        if (count($andParts) > 1) {
            $hasUnknown = false;
            foreach ($andParts as $part) {
                $value = $this->evaluate($part, $row);
                if ($value === false) {
                    return false;
                }
                $hasUnknown = $hasUnknown || $value === null;
            }

            return $hasUnknown ? null : true;
        }

        if (preg_match('/^(.+?)\s+is\s+(not\s+)?null$/i', $expression, $matches) === 1) {
            $value = $this->value($matches[1], $row);
            $isNull = $value === null;

            return isset($matches[2]) && trim($matches[2]) !== '' ? !$isNull : $isNull;
        }

        if (preg_match('/^(.+?)\s+in\s*\((.*)\)$/i', $expression, $matches) === 1) {
            $left = $this->value($matches[1], $row);
            if ($left === null) {
                return null;
            }
            foreach ($this->splitTopLevelComma($matches[2]) as $candidate) {
                $right = $this->value($candidate, $row);
                if ($right !== null && $this->valuesEqual($left, $right)) {
                    return true;
                }
            }

            return false;
        }

        if (preg_match('/^(.+?)\s*(>=|<=|<>|!=|=|>|<)\s*(.+)$/', $expression, $matches) === 1) {
            $left = $this->value($matches[1], $row);
            $right = $this->value($matches[3], $row);
            if ($left === null || $right === null) {
                return null;
            }

            return $this->compare($left, $right, $matches[2]);
        }

        throw new \InvalidArgumentException("Unsupported check expression: {$expression}");
    }

    private function compare(int|float|string|bool $left, int|float|string|bool $right, string $operator): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            $comparison = $left <=> $right;
        } else {
            $comparison = (string) $left <=> (string) $right;
        }

        return match ($operator) {
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            '=', '==' => $comparison === 0,
            '<>', '!=' => $comparison !== 0,
            default => throw new \InvalidArgumentException("Unsupported check comparison operator: {$operator}"),
        };
    }

    private function valuesEqual(int|float|string|bool $left, int|float|string|bool $right): bool
    {
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left == $right;
        }

        return (string) $left === (string) $right;
    }

    /**
     * @param array<string, scalar|null> $row
     */
    private function value(string $token, array $row): int|float|string|bool|null
    {
        $token = $this->stripOuterParentheses(trim($token));
        if ($token === '') {
            throw new \InvalidArgumentException('Empty check expression value.');
        }
        if (preg_match('/^`((?:``|[^`])+)`$/', $token, $matches) === 1) {
            return $row[str_replace('``', '`', $matches[1])] ?? null;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) === 1 && array_key_exists($token, $row)) {
            return $row[$token];
        }
        if (strcasecmp($token, 'null') === 0) {
            return null;
        }
        if (strcasecmp($token, 'true') === 0) {
            return true;
        }
        if (strcasecmp($token, 'false') === 0) {
            return false;
        }
        if (preg_match('/^-?\d+$/', $token) === 1) {
            return (int) $token;
        }
        if (preg_match('/^-?\d+\.\d+$/', $token) === 1) {
            return (float) $token;
        }
        if (preg_match("/^'(.*)'$/s", $token, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }

        throw new \InvalidArgumentException("Unsupported check expression value: {$token}");
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelKeyword(string $expression, string $keyword): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $inString = false;
        $inIdentifier = false;
        $length = strlen($expression);
        $keywordLength = strlen($keyword);
        $lower = strtolower($expression);
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if ($inString) {
                if ($char === "'" && ($i + 1 >= $length || $expression[$i + 1] !== "'")) {
                    $inString = false;
                } elseif ($char === "'" && $i + 1 < $length && $expression[$i + 1] === "'") {
                    $i++;
                }
                continue;
            }
            if ($inIdentifier) {
                if ($char === '`') {
                    $inIdentifier = false;
                }
                continue;
            }
            if ($char === "'") {
                $inString = true;
                continue;
            }
            if ($char === '`') {
                $inIdentifier = true;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($depth !== 0 || substr($lower, $i, $keywordLength) !== $keyword) {
                continue;
            }
            $before = $i === 0 ? '' : $lower[$i - 1];
            $afterIndex = $i + $keywordLength;
            $after = $afterIndex >= $length ? '' : $lower[$afterIndex];
            if ($this->isIdentifierChar($before) || $this->isIdentifierChar($after)) {
                continue;
            }

            $parts[] = trim(substr($expression, $start, $i - $start));
            $start = $i + $keywordLength;
            $i += $keywordLength - 1;
        }

        if ($parts === []) {
            return [$expression];
        }

        $parts[] = trim(substr($expression, $start));

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function splitTopLevelComma(string $expression): array
    {
        $parts = [];
        $start = 0;
        $depth = 0;
        $inString = false;
        $length = strlen($expression);
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if ($inString) {
                if ($char === "'" && ($i + 1 >= $length || $expression[$i + 1] !== "'")) {
                    $inString = false;
                } elseif ($char === "'" && $i + 1 < $length && $expression[$i + 1] === "'") {
                    $i++;
                }
                continue;
            }
            if ($char === "'") {
                $inString = true;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                continue;
            }
            if ($char !== ',' || $depth !== 0) {
                continue;
            }
            $parts[] = trim(substr($expression, $start, $i - $start));
            $start = $i + 1;
        }

        $parts[] = trim(substr($expression, $start));

        return $parts;
    }

    private function stripOuterParentheses(string $expression): string
    {
        while (strlen($expression) >= 2 && $expression[0] === '(' && $expression[strlen($expression) - 1] === ')') {
            if (!$this->outerParenthesesWrapExpression($expression)) {
                break;
            }
            $expression = trim(substr($expression, 1, -1));
        }

        return $expression;
    }

    private function outerParenthesesWrapExpression(string $expression): bool
    {
        $depth = 0;
        $inString = false;
        $length = strlen($expression);
        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if ($inString) {
                if ($char === "'" && ($i + 1 >= $length || $expression[$i + 1] !== "'")) {
                    $inString = false;
                } elseif ($char === "'" && $i + 1 < $length && $expression[$i + 1] === "'") {
                    $i++;
                }
                continue;
            }
            if ($char === "'") {
                $inString = true;
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0 && $i < $length - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    private function isIdentifierChar(string $char): bool
    {
        return $char !== '' && preg_match('/[A-Za-z0-9_]/', $char) === 1;
    }
}
