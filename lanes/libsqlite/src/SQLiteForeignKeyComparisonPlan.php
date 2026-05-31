<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteForeignKeyComparisonPlan
{
    /**
     * @param list<mixed> $parents
     * @param list<mixed> $children
     * @return array<string,mixed>
     */
    public static function compare(
        array $parents,
        array $children,
        mixed $candidate,
        string $operation,
        string $parentAffinity = 'none',
        string $parentCollation = 'binary'
    ): array {
        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['insert-child', 'delete-parent', 'update-child'], true)) {
            throw new \InvalidArgumentException('SQLite foreign key comparison operation is unsupported');
        }

        $parentAffinity = strtolower(trim($parentAffinity));
        if (!in_array($parentAffinity, ['none', 'text', 'numeric', 'integer'], true)) {
            throw new \InvalidArgumentException('SQLite foreign key parent affinity is unsupported');
        }

        $parentCollation = strtolower(trim($parentCollation));
        if (!in_array($parentCollation, ['binary', 'nocase', 'rtrim'], true)) {
            throw new \InvalidArgumentException('SQLite foreign key parent collation is unsupported');
        }

        $candidateAfterAffinity = self::applyAffinity($candidate, $parentAffinity);
        $matches = self::matchingParents($parents, $candidateAfterAffinity, $parentCollation);
        $status = $matches === [] ? 'constraint-failed' : 'ok';
        if ($operation === 'delete-parent') {
            $referencingChildren = self::matchingParents($children, self::applyAffinity($candidate, $parentAffinity), $parentCollation);
            $status = $referencingChildren === [] ? 'ok' : 'constraint-failed';
        }

        return [
            'source' => 'e_fkey.test e_fkey-15.1..17.4',
            'operation' => $operation,
            'parent_affinity' => $parentAffinity,
            'parent_collation' => $parentCollation,
            'candidate_before_affinity' => self::typedValue($candidate),
            'candidate_after_parent_affinity' => self::typedValue($candidateAfterAffinity),
            'matching_parent_indexes' => $matches,
            'status' => $status,
            'constraint_failed' => $status === 'constraint-failed',
            'null_child_short_circuit' => $candidate === null && $operation !== 'delete-parent',
            'dependencies' => [
                'sqlite-e-fkey-parent-affinity-applied-to-child-key',
                'sqlite-e-fkey-parent-collation-controls-text-comparison',
                'sqlite-e-fkey-storage-class-comparison-preserves-blob-boundaries',
            ],
        ];
    }

    private static function applyAffinity(mixed $value, string $affinity): mixed
    {
        if ($value === null || $affinity === 'none') {
            return $value;
        }

        if ($affinity === 'text') {
            return self::isBlob($value) ? $value : (string) $value;
        }

        if (($affinity === 'numeric' || $affinity === 'integer') && is_string($value) && is_numeric($value)) {
            $number = $value + 0;
            if ($affinity === 'integer' || (float) $number === (float) (int) $number) {
                return (int) $number;
            }

            return (float) $number;
        }

        return $value;
    }

    /**
     * @param list<mixed> $parents
     * @return list<int>
     */
    private static function matchingParents(array $parents, mixed $candidate, string $collation): array
    {
        if ($candidate === null) {
            return [];
        }

        $matches = [];
        foreach (array_values($parents) as $index => $parent) {
            if (self::valuesEqual($parent, $candidate, $collation)) {
                $matches[] = $index;
            }
        }

        return $matches;
    }

    private static function valuesEqual(mixed $left, mixed $right, string $collation): bool
    {
        if (self::isBlob($left) || self::isBlob($right)) {
            return self::isBlob($left) && self::isBlob($right) && $left === $right;
        }

        if (is_string($left) && is_string($right)) {
            if ($collation === 'nocase') {
                return strcasecmp($left, $right) === 0;
            }
            if ($collation === 'rtrim') {
                return rtrim($left) === rtrim($right);
            }

            return $left === $right;
        }

        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return (float) $left === (float) $right;
        }

        return $left === $right;
    }

    private static function typedValue(mixed $value): array
    {
        return [
            'type' => self::isBlob($value) ? 'blob' : get_debug_type($value),
            'value' => $value,
        ];
    }

    private static function isBlob(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, "blob:");
    }
}
