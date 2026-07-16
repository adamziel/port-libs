<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteUpsertConflictTargetExpressionPlan
{
    /**
     * @param list<string> $uniqueExpressions
     * @return array{conflict_target:string,normalized_target:string,matched_unique_expression:?string,valid:bool,error:?string,dependencies:list<string>}
     */
    public static function analyze(array $uniqueExpressions, string $conflictTarget): array
    {
        if ($uniqueExpressions === [] || !array_is_list($uniqueExpressions)) {
            throw new InvalidArgumentException('SQLite UPSERT unique expression list must be a non-empty list');
        }

        $normalizedTarget = self::normalizeExpression($conflictTarget);
        $error = null;
        $valid = true;
        if (str_contains($normalizedTarget, '?')) {
            $valid = false;
            $error = 'ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint';
        }

        $matched = null;
        if ($valid) {
            foreach ($uniqueExpressions as $expression) {
                if (!is_string($expression) || trim($expression) === '') {
                    throw new InvalidArgumentException('SQLite UPSERT unique expression must be a non-empty string');
                }
                $normalizedExpression = self::normalizeExpression($expression);
                if ($normalizedExpression === $normalizedTarget) {
                    $matched = $expression;
                    break;
                }
            }
            if ($matched === null) {
                $valid = false;
                $error = 'ON CONFLICT clause does not match any PRIMARY KEY or UNIQUE constraint';
            }
        }

        return [
            'conflict_target' => $conflictTarget,
            'normalized_target' => $normalizedTarget,
            'matched_unique_expression' => $matched,
            'valid' => $valid,
            'error' => $error,
            'dependencies' => [
                'upsert1.test-1200',
                'upsert1.test-1210',
                'sqlite-upsert-conflict-target-expression-identity',
                'sqlite-upsert-bound-parameter-conflict-target-rejection',
            ],
        ];
    }

    private static function normalizeExpression(string $expression): string
    {
        $expression = trim($expression);
        if ($expression === '') {
            throw new InvalidArgumentException('SQLite UPSERT conflict target expression must be non-empty');
        }

        $expression = preg_replace('/\s+/', '', strtolower($expression));
        if (!is_string($expression) || $expression === '') {
            throw new InvalidArgumentException('SQLite UPSERT conflict target expression must be non-empty');
        }

        while (str_starts_with($expression, '(') && str_ends_with($expression, ')') && self::outerParensWrapWholeExpression($expression)) {
            $expression = substr($expression, 1, -1);
        }

        if (preg_match('/^[a-z_][a-z0-9_]*(?:\+[0-9]+|\+\?(?:[0-9]+|[a-z_][a-z0-9_]*)?)?$/', $expression) !== 1) {
            throw new InvalidArgumentException('SQLite UPSERT conflict target expression is outside the bounded analyzer');
        }

        return $expression;
    }

    private static function outerParensWrapWholeExpression(string $expression): bool
    {
        $depth = 0;
        $last = strlen($expression) - 1;
        for ($i = 0; $i <= $last; ++$i) {
            $char = $expression[$i];
            if ($char === '(') {
                ++$depth;
                continue;
            }
            if ($char === ')') {
                --$depth;
                if ($depth === 0 && $i !== $last) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }
}
