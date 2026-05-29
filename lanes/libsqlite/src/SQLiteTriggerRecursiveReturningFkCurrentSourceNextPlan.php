<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTriggerRecursiveReturningFkCurrentSourceNextPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,deferred?:bool} $foreignKey
     * @param array<string,mixed> $currentStatement
     * @param array<string,mixed> $nextStatement
     * @return array<string,mixed>
     */
    public static function handoff(array $parents, array $children, array $foreignKey, array $currentStatement, array $nextStatement): array
    {
        if (!class_exists(SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::class)) {
            require_once __DIR__ . '/SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan.php';
        }

        $currentToken = self::source((string) ($currentStatement['current_source'] ?? 'current'));
        $nextToken = self::source((string) ($nextStatement['current_source'] ?? ($currentStatement['next_source'] ?? 'next')));
        $finalToken = self::source((string) ($nextStatement['next_source'] ?? ($nextToken . ':after')));
        $current = SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update(
            $parents,
            $children,
            $foreignKey,
            array_replace($currentStatement, ['current_source' => $currentToken, 'next_source' => $nextToken]),
        );

        $currentAllowsNext = $current['status'] === 'commit-ok';
        $next = null;
        if ($currentAllowsNext) {
            $next = SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update(
                array_values($current['next_parent']),
                array_values($current['next_child']),
                $foreignKey,
                array_replace($nextStatement, ['current_source' => $nextToken, 'next_source' => $finalToken]),
            );
        }

        $admitted = $currentAllowsNext && is_array($next) && $next['status'] === 'commit-ok';
        $blockedBy = 'none';
        if (!$currentAllowsNext) {
            $blockedBy = $current['status'] === 'rolled-back' ? 'current-deferred-fk-rollback' : 'current-deferred-fk-block';
        } elseif (is_array($next) && $next['status'] !== 'commit-ok') {
            $blockedBy = $next['status'] === 'rolled-back' ? 'next-deferred-fk-rollback' : 'next-deferred-fk-block';
        }

        return [
            'status' => $admitted ? 'next-source-committed' : 'next-source-blocked',
            'current_source' => $currentToken,
            'next_source' => $nextToken,
            'final_source' => $admitted ? $finalToken : $nextToken,
            'current_plan' => $current,
            'next_plan' => $next,
            'current_returning_rows' => array_values($current['current_returning_rows']),
            'current_trigger_returning_rows' => array_values($current['trigger_returning_rows']),
            'attempted_next_returning_rows' => is_array($next) ? array_values($next['current_returning_rows']) : [],
            'next_returning_rows' => $admitted && is_array($next) ? array_values($next['next_returning_rows']) : [],
            'current_foreign_key_violations' => array_values($current['foreign_key_violations']),
            'next_foreign_key_violations' => is_array($next) ? array_values($next['foreign_key_violations']) : [],
            'current_rowids' => array_values($current['current_rowids']),
            'next_rowids' => is_array($next) ? array_values($next['current_rowids']) : array_values($current['next_rowids']),
            'final_rowids' => $admitted && is_array($next) ? array_values($next['next_rowids']) : array_values($current['next_rowids']),
            'next_source_admitted' => $currentAllowsNext,
            'next_source_committed' => $admitted,
            'blocked_by' => $blockedBy,
            'yield_boundary' => $admitted ? 'current-fk-valid-next-source-returning-commit' : 'current-fk-blocks-next-source-returning',
            'combined_changes' => (int) $current['next_changes'] + (is_array($next) ? (int) $next['next_changes'] : 0),
            'combined_returning_count' => count($current['current_returning_rows']) + ($admitted && is_array($next) ? count($next['next_returning_rows']) : 0),
            'dependencies' => [
                'sqlite-trigger-recursive-deferred-returning-current-source-next121',
                'sqlite-current-source-fk-admission-before-next-source',
                'sqlite-trigger-recursive-returning-fk-current-source-next133',
            ],
        ];
    }

    private static function source(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.:@-]+$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite trigger recursive RETURNING FK next133 source token is malformed');
        }

        return $value;
    }
}
