<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyReplaceCounterPlan
{
    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @param array{parent_key:string,child_key:string,deferred?:bool,without_rowid_parent?:bool,prior_deleted_parent_keys?:list<mixed>,trigger_replace_parent?:array<string,mixed>|null} $foreignKey
     * @param array<string,mixed> $replacement
     * @return array<string,mixed>
     */
    public static function replaceParent(array $parents, array $children, array $foreignKey, array $replacement): array
    {
        $parentKey = self::identifier((string) ($foreignKey['parent_key'] ?? ''), 'parent key');
        $childKey = self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key');
        if (!array_key_exists($parentKey, $replacement)) {
            throw new \InvalidArgumentException('SQLite FK REPLACE replacement is missing parent key');
        }

        $parents = array_values($parents);
        $children = array_values($children);
        $deferred = (bool) ($foreignKey['deferred'] ?? false);
        $deleted = [];
        $priorDeletes = [];
        foreach ((array) ($foreignKey['prior_deleted_parent_keys'] ?? []) as $priorKey) {
            foreach ($parents as $index => $row) {
                if (($row[$parentKey] ?? null) === $priorKey) {
                    $priorDeletes[] = ['index' => $index, 'row' => $row, 'reason' => 'statement-delete'];
                    unset($parents[$index]);
                }
            }
            $parents = array_values($parents);
        }
        $key = $replacement[$parentKey];
        foreach ($parents as $index => $row) {
            if (($row[$parentKey] ?? null) === $key) {
                $deleted[] = ['index' => $index, 'row' => $row, 'reason' => 'replace-conflict'];
                unset($parents[$index]);
            }
        }
        $parents = array_values($parents);
        $parents[] = $replacement;

        $trigger = isset($foreignKey['trigger_replace_parent']) && is_array($foreignKey['trigger_replace_parent'])
            ? $foreignKey['trigger_replace_parent']
            : null;
        $triggerEffects = [];
        if ($trigger !== null && $deleted !== []) {
            $triggerKey = $trigger[$parentKey] ?? null;
            if ($triggerKey === null) {
                throw new \InvalidArgumentException('SQLite FK REPLACE trigger replacement is missing parent key');
            }
            foreach ($parents as $index => $row) {
                if (($row[$parentKey] ?? null) === $triggerKey) {
                    $triggerEffects[] = ['event' => 'implicit-delete-trigger', 'deleted_key' => $key, 'replaced_key' => $triggerKey];
                    unset($parents[$index]);
                }
            }
            $parents = array_values($parents);
            $parents[] = $trigger;
        }

        $violations = self::violations($parents, $children, $parentKey, $childKey, $deferred ? 'deferred-commit' : 'statement');
        $status = $violations === [] ? 'commit-ok' : ($deferred ? 'deferred-commit-blocked' : 'statement-blocked');

        return [
            'status' => $status,
            'parent' => $parents,
            'child' => $children,
            'prior_deletes' => $priorDeletes,
            'implicit_deletes' => $deleted,
            'trigger_effects' => $triggerEffects,
            'foreign_key_violations' => $violations,
            'deferred_counter' => $deferred ? count($violations) : 0,
            'statement_counter' => $deferred ? 0 : count($violations),
            'uses_statement_journal' => count($deleted) > 0 && (bool) ($foreignKey['without_rowid_parent'] ?? false),
            'dependencies' => [
                'sqlite-fkey8-replace-implicit-delete-counter',
                'sqlite-without-rowid-replace-conflict',
                'sqlite-trigger-implicit-delete-replace',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $children
     * @param array{child_key:string,deferred?:bool} $foreignKey
     * @param array<string,mixed> $replacement
     * @return array<string,mixed>
     */
    public static function replaceChild(array $children, array $foreignKey, array $replacement): array
    {
        $childKey = self::identifier((string) ($foreignKey['child_key'] ?? ''), 'child key');
        if (!array_key_exists($childKey, $replacement)) {
            throw new \InvalidArgumentException('SQLite FK REPLACE child replacement is missing key');
        }

        $children = array_values($children);
        $deleted = [];
        foreach ($children as $index => $row) {
            if (($row[$childKey] ?? null) === $replacement[$childKey]) {
                $deleted[] = ['index' => $index, 'row' => $row, 'reason' => 'replace-conflict'];
                unset($children[$index]);
            }
        }
        $children = array_values($children);
        $children[] = $replacement;

        return [
            'status' => 'commit-ok',
            'child' => $children,
            'implicit_deletes' => $deleted,
            'foreign_key_violations' => [],
            'deferred_counter' => 0,
            'statement_counter' => 0,
            'dependencies' => [
                'sqlite-fkey8-replace-child-counter-cancellation',
                'sqlite-without-rowid-replace-conflict',
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $parents
     * @param list<array<string,mixed>> $children
     * @return list<array<string,mixed>>
     */
    private static function violations(array $parents, array $children, string $parentKey, string $childKey, string $phase): array
    {
        $parentKeys = array_column($parents, $parentKey);
        $violations = [];
        foreach ($children as $index => $child) {
            $key = $child[$childKey] ?? null;
            if ($key === null || in_array($key, $parentKeys, true)) {
                continue;
            }
            $violations[] = ['child_index' => $index, 'child_key' => $key, 'parent' => $parentKey, 'phase' => $phase];
        }

        return $violations;
    }

    private static function identifier(string $value, string $label): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite FK REPLACE {$label} is malformed");
        }

        return $value;
    }
}
