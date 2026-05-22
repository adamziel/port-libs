<?php

declare(strict_types=1);

namespace PortLibs\Dolt;

final class TableSchema
{
    public const DIFF_NONE = 'none';
    public const DIFF_ADDED = 'added';
    public const DIFF_REMOVED = 'removed';
    public const DIFF_MODIFIED = 'modified';
    public const WARNING_UNKNOWN = 1105;
    public const WARNING_TRUNCATED = 1292;
    public const PRIMARY_KEY_CHANGE_WARNING = 'cannot render full diff between commits %s and %s due to primary key set change';
    public const DATATYPE_COERCION_FAILURE_WARNING = "unable to coerce value from field '%s' into latest column schema";

    /** @var list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}> */
    private array $columns;

    /**
     * @param list<array{name:string, tag:int, type:string, primaryKey?:bool, constraints?:list<string>}> $columns
     */
    public function __construct(array $columns)
    {
        $this->columns = $this->normalizeColumns($columns);
    }

    /**
     * @param list<array{name:string, tag:int, type:string, primaryKey?:bool, constraints?:list<string>}> $columns
     */
    public static function fromColumns(array $columns): self
    {
        return new self($columns);
    }

    /**
     * Dolt's DiffSchColumns pairs columns by stable column tag and then marks
     * equal-tag columns as modified when name, type, PK membership, or
     * constraints changed.
     *
     * @return list<array{diff_type:string, tag:int, from:array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}|null, to:array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}|null}>
     */
    public static function diffColumns(self $from, self $to): array
    {
        $unionTags = $from->tags();
        foreach ($to->tags() as $tag) {
            if (!in_array($tag, $unionTags, true)) {
                $unionTags[] = $tag;
            }
        }

        $diffs = [];
        foreach ($unionTags as $tag) {
            $fromColumn = $from->columnByTag($tag);
            $toColumn = $to->columnByTag($tag);
            if ($fromColumn === null) {
                $diffType = self::DIFF_ADDED;
            } elseif ($toColumn === null) {
                $diffType = self::DIFF_REMOVED;
            } elseif ($fromColumn !== $toColumn) {
                $diffType = self::DIFF_MODIFIED;
            } else {
                $diffType = self::DIFF_NONE;
            }

            $diffs[] = [
                'diff_type' => $diffType,
                'tag' => $tag,
                'from' => $fromColumn,
                'to' => $toColumn,
            ];
        }

        return $diffs;
    }

    public static function schemasOverlap(self $from, self $to): bool
    {
        $overlaps = 0;
        foreach ($from->tags() as $tag) {
            $fromColumn = $from->columnByTag($tag);
            $toColumn = $to->columnByTag($tag);
            if ($fromColumn === null || $toColumn === null) {
                continue;
            }
            if ($fromColumn['name'] === $toColumn['name']) {
                $overlaps++;
            }
        }

        return $overlaps > 0;
    }

    public static function primaryKeySetsDiffable(?self $from, ?self $to): bool
    {
        if ($from === null && $to === null) {
            return false;
        }
        if ($from === null || $to === null || $from->columns === [] || $to->columns === []) {
            return true;
        }
        if ($from->isKeyless() && $to->isKeyless()) {
            return true;
        }

        $fromPk = $from->primaryKeyColumns();
        $toPk = $to->primaryKeyColumns();
        if (count($fromPk) !== count($toPk)) {
            return false;
        }

        foreach ($fromPk as $i => $fromColumn) {
            $toColumn = $toPk[$i];
            if ($fromColumn['tag'] !== $toColumn['tag']) {
                return false;
            }
            if ($fromColumn['primaryKey'] !== $toColumn['primaryKey']) {
                return false;
            }
            if ($fromColumn['type'] !== $toColumn['type']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Dolt uses this mapping as a fallback when primary-key sets changed: PK
     * columns map by stable tag, or by case-insensitive same name and SQL type;
     * non-PK columns map by name only.
     */
    public static function canMapSchemaBasedOnTagAndName(self $input, self $output): bool
    {
        if ($input->columns === [] || $output->columns === []) {
            return true;
        }

        foreach ($input->primaryKeyColumns() as $column) {
            $target = $output->primaryKeyColumnByTag($column['tag'])
                ?? $output->primaryKeyColumnByNameCaseInsensitive($column['name'], $column['type']);
            if ($target === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{simple:bool, fuzzy:bool, warning:array{code:int, message:string}|null}
     */
    public static function partitionDiffability(?self $from, ?self $to, string $fromCommit = 'FROM', string $toCommit = 'TO'): array
    {
        if ($to === null) {
            return [
                'simple' => false,
                'fuzzy' => false,
                'warning' => self::primaryKeyChangeWarning($fromCommit, $toCommit),
            ];
        }
        if ($from === null || self::primaryKeySetsDiffable($from, $to)) {
            return ['simple' => true, 'fuzzy' => false, 'warning' => null];
        }

        $warning = self::primaryKeyChangeWarning($fromCommit, $toCommit);
        if (self::canMapSchemaBasedOnTagAndName($from, $to)) {
            return ['simple' => false, 'fuzzy' => true, 'warning' => $warning];
        }

        return ['simple' => false, 'fuzzy' => false, 'warning' => $warning];
    }

    /**
     * @return list<int>
     */
    public static function primaryKeyOrdinalMapping(self $from, self $to): array
    {
        if (!self::primaryKeySetsDiffable($from, $to)) {
            throw new \InvalidArgumentException('Primary key sets are not diffable.');
        }

        $mapping = [];
        $toOrdinals = [];
        foreach ($to->primaryKeyColumns() as $i => $column) {
            $toOrdinals[$column['tag']] = $i;
        }
        foreach ($from->primaryKeyColumns() as $column) {
            $mapping[] = $toOrdinals[$column['tag']] ?? -1;
        }

        return $mapping;
    }

    /**
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}>
     */
    public function primaryKeyColumns(): array
    {
        return array_values(array_filter(
            $this->columns,
            static fn (array $column): bool => $column['primaryKey']
        ));
    }

    public function isKeyless(): bool
    {
        return $this->primaryKeyColumns() === [];
    }

    /**
     * @return list<int>
     */
    public function tags(): array
    {
        return array_map(static fn (array $column): int => $column['tag'], $this->columns);
    }

    /**
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}|null
     */
    public function columnByTag(int $tag): ?array
    {
        foreach ($this->columns as $column) {
            if ($column['tag'] === $tag) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}|null
     */
    public function columnByName(string $name): ?array
    {
        foreach ($this->columns as $column) {
            if ($column['name'] === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param array<string, true> $names
     */
    public function withoutColumnNames(array $names): self
    {
        if ($names === []) {
            return $this;
        }

        return new self(array_values(array_filter(
            $this->columns,
            static fn (array $column): bool => !isset($names[$column['name']])
        )));
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<array{code:int, message:string}> $warnings
     * @return array<string, scalar|null>
     */
    public function projectRowTo(self $target, array $row, array &$warnings = []): array
    {
        $projected = [];
        foreach ($target->columns as $column) {
            $projected[$column['name']] = null;
        }

        foreach ($this->primaryKeyColumns() as $column) {
            $targetColumn = $target->primaryKeyColumnByTag($column['tag'])
                ?? $target->primaryKeyColumnByNameCaseInsensitive($column['name'], $column['type']);
            if ($targetColumn === null || !array_key_exists($column['name'], $row)) {
                continue;
            }
            $projected[$targetColumn['name']] = self::coerceValue($row[$column['name']], $column, $targetColumn, $warnings);
        }

        foreach ($this->nonPrimaryKeyColumns() as $column) {
            $targetColumn = $target->nonPrimaryKeyColumnByName($column['name']);
            if ($targetColumn === null || !array_key_exists($column['name'], $row)) {
                continue;
            }
            $projected[$targetColumn['name']] = self::coerceValue($row[$column['name']], $column, $targetColumn, $warnings);
        }

        return $projected;
    }

    /**
     * @param list<array{name:string, tag:int, type:string, primaryKey?:bool, constraints?:list<string>}> $columns
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}>
     */
    private function normalizeColumns(array $columns): array
    {
        $normalized = [];
        $tags = [];
        $names = [];
        foreach ($columns as $column) {
            $name = $column['name'] ?? null;
            $tag = $column['tag'] ?? null;
            $type = $column['type'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Column names must be non-empty strings.');
            }
            if (!is_int($tag) || $tag < 0) {
                throw new \InvalidArgumentException("Column {$name} must have a non-negative integer tag.");
            }
            if (!is_string($type) || $type === '') {
                throw new \InvalidArgumentException("Column {$name} must have a non-empty type.");
            }
            if (isset($tags[$tag])) {
                throw new \InvalidArgumentException("Duplicate column tag: {$tag}");
            }
            $lowerName = strtolower($name);
            if (isset($names[$lowerName])) {
                throw new \InvalidArgumentException("Duplicate column name: {$name}");
            }

            $constraints = [];
            foreach (($column['constraints'] ?? []) as $constraint) {
                if (!is_string($constraint) || $constraint === '') {
                    throw new \InvalidArgumentException("Column {$name} constraints must be non-empty strings.");
                }
                $constraints[] = $constraint;
            }

            $tags[$tag] = true;
            $names[$lowerName] = true;
            $normalized[] = [
                'name' => $name,
                'tag' => $tag,
                'type' => $type,
                'primaryKey' => (bool) ($column['primaryKey'] ?? false),
                'constraints' => $constraints,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}>
     */
    private function nonPrimaryKeyColumns(): array
    {
        return array_values(array_filter(
            $this->columns,
            static fn (array $column): bool => !$column['primaryKey']
        ));
    }

    /**
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}|null
     */
    private function primaryKeyColumnByTag(int $tag): ?array
    {
        foreach ($this->primaryKeyColumns() as $column) {
            if ($column['tag'] === $tag) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}|null
     */
    private function primaryKeyColumnByNameCaseInsensitive(string $name, string $type): ?array
    {
        foreach ($this->primaryKeyColumns() as $column) {
            if (strcasecmp($column['name'], $name) === 0 && self::sameSqlType($column['type'], $type)) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>}|null
     */
    private function nonPrimaryKeyColumnByName(string $name): ?array
    {
        foreach ($this->nonPrimaryKeyColumns() as $column) {
            if ($column['name'] === $name) {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>} $fromColumn
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>} $toColumn
     * @param list<array{code:int, message:string}> $warnings
     */
    private static function coerceValue(mixed $value, array $fromColumn, array $toColumn, array &$warnings): int|float|string|bool|null
    {
        if ($value === null || self::sameSqlType($fromColumn['type'], $toColumn['type'])) {
            return $value;
        }

        $targetKind = self::sqlTypeKind($toColumn['type']);
        if ($targetKind === 'string') {
            return (string) $value;
        }
        if ($targetKind === 'integer') {
            if (is_int($value)) {
                return $value;
            }
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }
            if (is_float($value) && floor($value) === $value) {
                return (int) $value;
            }
            if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
                return (int) $value;
            }
            $warnings[] = [
                'code' => self::WARNING_UNKNOWN,
                'message' => sprintf(self::DATATYPE_COERCION_FAILURE_WARNING, $fromColumn['name']),
            ];

            return null;
        }
        if ($targetKind === 'number') {
            if (is_int($value) || is_float($value)) {
                return $value;
            }
            if (is_string($value) && is_numeric($value)) {
                return (float) $value;
            }
            $warnings[] = [
                'code' => self::WARNING_UNKNOWN,
                'message' => sprintf(self::DATATYPE_COERCION_FAILURE_WARNING, $fromColumn['name']),
            ];

            return null;
        }

        return $value;
    }

    public static function sqlTypesEqual(string $left, string $right): bool
    {
        return self::sameSqlType($left, $right);
    }

    private static function sameSqlType(string $left, string $right): bool
    {
        return strtolower(trim($left)) === strtolower(trim($right));
    }

    private static function sqlTypeKind(string $type): string
    {
        $type = strtolower(trim($type));
        if (preg_match('/\b(tinyint|smallint|mediumint|integer|int|bigint)\b/', $type) === 1) {
            return 'integer';
        }
        if (preg_match('/\b(float|double|decimal|numeric|real)\b/', $type) === 1) {
            return 'number';
        }
        if (preg_match('/\b(char|varchar|text|string|json|enum|set)\b/', $type) === 1) {
            return 'string';
        }

        return 'other';
    }

    /**
     * @return array{code:int, message:string}
     */
    private static function primaryKeyChangeWarning(string $fromCommit, string $toCommit): array
    {
        return [
            'code' => self::WARNING_UNKNOWN,
            'message' => sprintf(self::PRIMARY_KEY_CHANGE_WARNING, $fromCommit, $toCommit),
        ];
    }
}
