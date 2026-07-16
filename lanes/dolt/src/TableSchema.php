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
    public const DEFAULT_TARGET_ROW_SIZE = 2048;

    /** @var list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}> */
    private array $columns;

    /** @var list<array{name:non-empty-string, columns:list<non-empty-string>, unique:bool}> */
    private array $indexes;

    /** @var list<array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null}> */
    private array $foreignKeys;

    /** @var list<array{name:non-empty-string, expression:non-empty-string, enforced:bool}> */
    private array $checks;

    private string $collation;

    private int $targetRowSize;

    /**
     * @param list<array{name:string, tag:int, type:string, primaryKey?:bool, constraints?:list<string>, default?:string|null, generated?:string|null, generatedStored?:bool, onUpdate?:string|null, autoIncrement?:bool}> $columns
     * @param array{
     *   indexes?:list<array{name:string, columns:list<string>, unique?:bool}>,
     *   foreignKeys?:list<array{name:string, columns:list<string>, referencedTable:string, referencedColumns:list<string>, onDelete?:string|null, onUpdate?:string|null}>,
     *   checks?:list<array{name:string, expression:string, enforced?:bool}>,
     *   collation?:string,
     *   targetRowSize?:int
     * } $options
     */
    public function __construct(array $columns, array $options = [])
    {
        $this->columns = $this->normalizeColumns($columns);
        $this->indexes = $this->normalizeIndexes($options['indexes'] ?? []);
        $this->foreignKeys = $this->normalizeForeignKeys($options['foreignKeys'] ?? []);
        $this->checks = $this->normalizeChecks($options['checks'] ?? []);
        $this->collation = $this->normalizeCollation($options['collation'] ?? 'utf8mb4_0900_bin');
        $this->targetRowSize = $this->normalizeTargetRowSize($options['targetRowSize'] ?? self::DEFAULT_TARGET_ROW_SIZE);
    }

    /**
     * @param list<array{name:string, tag:int, type:string, primaryKey?:bool, constraints?:list<string>, default?:string|null, generated?:string|null, generatedStored?:bool, onUpdate?:string|null, autoIncrement?:bool}> $columns
     * @param array{
     *   indexes?:list<array{name:string, columns:list<string>, unique?:bool}>,
     *   foreignKeys?:list<array{name:string, columns:list<string>, referencedTable:string, referencedColumns:list<string>, onDelete?:string|null, onUpdate?:string|null}>,
     *   checks?:list<array{name:string, expression:string, enforced?:bool}>,
     *   collation?:string,
     *   targetRowSize?:int
     * } $options
     */
    public static function fromColumns(array $columns, array $options = []): self
    {
        return new self($columns, $options);
    }

    /**
     * Dolt's DiffSchColumns pairs columns by stable column tag and then marks
     * equal-tag columns as modified when name, type, PK membership, or
     * constraints, default, generated expression, or on-update expression
     * changed.
     *
     * @return list<array{diff_type:string, tag:int, from:array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}|null, to:array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}|null}>
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

    /**
     * @return list<array{diff_type:string, from:array{name:non-empty-string, expression:non-empty-string, enforced:bool}|null, to:array{name:non-empty-string, expression:non-empty-string, enforced:bool}|null}>
     */
    public static function diffChecks(self $from, self $to): array
    {
        $fromByName = self::checkConstraintsByLowerName($from->checks);
        $toByName = self::checkConstraintsByLowerName($to->checks);
        $names = array_keys($fromByName + $toByName);
        sort($names, SORT_STRING);

        $diffs = [];
        foreach ($names as $name) {
            $fromCheck = $fromByName[$name] ?? null;
            $toCheck = $toByName[$name] ?? null;
            if ($fromCheck === null) {
                $diffType = self::DIFF_ADDED;
            } elseif ($toCheck === null) {
                $diffType = self::DIFF_REMOVED;
            } elseif ($fromCheck !== $toCheck) {
                $diffType = self::DIFF_MODIFIED;
            } else {
                $diffType = self::DIFF_NONE;
            }
            if ($diffType === self::DIFF_NONE) {
                continue;
            }

            $diffs[] = [
                'diff_type' => $diffType,
                'from' => $fromCheck,
                'to' => $toCheck,
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
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array{name:non-empty-string, columns:list<non-empty-string>, unique:bool}>
     */
    public function indexes(): array
    {
        return $this->indexes;
    }

    /**
     * @return list<array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null}>
     */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * @return list<array{name:non-empty-string, expression:non-empty-string, enforced:bool}>
     */
    public function checks(): array
    {
        return $this->checks;
    }

    public function collation(): string
    {
        return $this->collation;
    }

    public function characterSet(): string
    {
        return $this->characterSetForCollation($this->collation);
    }

    public function targetRowSize(): int
    {
        return $this->targetRowSize;
    }

    public function hasSameSchemaMetadata(self $other): bool
    {
        return $this->indexes === $other->indexes
            && $this->foreignKeys === $other->foreignKeys
            && $this->checks === $other->checks
            && $this->collation === $other->collation
            && $this->targetRowSize === $other->targetRowSize;
    }

    /**
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}>
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
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}|null
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
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}|null
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
        )), [
            'indexes' => $this->indexes,
            'foreignKeys' => $this->foreignKeys,
            'checks' => $this->checks,
            'collation' => $this->collation,
            'targetRowSize' => $this->targetRowSize,
        ]);
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
     * @param list<array{name:string, tag:int, type:string, primaryKey?:bool, constraints?:list<string>, default?:string|null, generated?:string|null, generatedStored?:bool, onUpdate?:string|null, autoIncrement?:bool}> $columns
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}>
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
            $generatedStored = $column['generatedStored'] ?? false;
            if (!is_bool($generatedStored)) {
                throw new \InvalidArgumentException("Column {$name} generatedStored must be a boolean.");
            }
            $generated = $this->nullableString($column['generated'] ?? null, "Column {$name} generated");
            if ($generated === null && $generatedStored) {
                throw new \InvalidArgumentException("Column {$name} generatedStored requires a generated expression.");
            }
            $autoIncrement = $column['autoIncrement'] ?? false;
            if (!is_bool($autoIncrement)) {
                throw new \InvalidArgumentException("Column {$name} autoIncrement must be a boolean.");
            }

            $tags[$tag] = true;
            $names[$lowerName] = true;
            $normalized[] = [
                'name' => $name,
                'tag' => $tag,
                'type' => $type,
                'primaryKey' => (bool) ($column['primaryKey'] ?? false),
                'constraints' => $constraints,
                'default' => $this->nullableString($column['default'] ?? null, "Column {$name} default"),
                'generated' => $generated,
                'generatedStored' => $generatedStored,
                'onUpdate' => $this->nullableString($column['onUpdate'] ?? null, "Column {$name} onUpdate"),
                'autoIncrement' => $autoIncrement,
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{name:non-empty-string, columns:list<non-empty-string>, unique:bool}>
     */
    private function normalizeIndexes(mixed $indexes): array
    {
        if (!is_array($indexes)) {
            throw new \InvalidArgumentException('Schema indexes must be a list.');
        }

        $normalized = [];
        $names = [];
        foreach (array_values($indexes) as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('Schema indexes must contain arrays.');
            }
            $name = $index['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Schema index names must be non-empty strings.');
            }
            $lowerName = strtolower($name);
            if (isset($names[$lowerName])) {
                throw new \InvalidArgumentException("Duplicate schema index name: {$name}");
            }

            $normalized[] = [
                'name' => $name,
                'columns' => $this->normalizeNameList($index['columns'] ?? null, "Schema index {$name} columns"),
                'unique' => (bool) ($index['unique'] ?? false),
            ];
            $names[$lowerName] = true;
        }

        return $normalized;
    }

    /**
     * @return list<array{name:non-empty-string, columns:list<non-empty-string>, referencedTable:non-empty-string, referencedColumns:list<non-empty-string>, onDelete:string|null, onUpdate:string|null}>
     */
    private function normalizeForeignKeys(mixed $foreignKeys): array
    {
        if (!is_array($foreignKeys)) {
            throw new \InvalidArgumentException('Schema foreign keys must be a list.');
        }

        $normalized = [];
        $names = [];
        foreach (array_values($foreignKeys) as $foreignKey) {
            if (!is_array($foreignKey)) {
                throw new \InvalidArgumentException('Schema foreign keys must contain arrays.');
            }
            $name = $foreignKey['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Schema foreign key names must be non-empty strings.');
            }
            $lowerName = strtolower($name);
            if (isset($names[$lowerName])) {
                throw new \InvalidArgumentException("Duplicate schema foreign key name: {$name}");
            }
            $referencedTable = $foreignKey['referencedTable'] ?? null;
            if (!is_string($referencedTable) || $referencedTable === '') {
                throw new \InvalidArgumentException("Schema foreign key {$name} referencedTable must be a non-empty string.");
            }

            $normalized[] = [
                'name' => $name,
                'columns' => $this->normalizeNameList($foreignKey['columns'] ?? null, "Schema foreign key {$name} columns"),
                'referencedTable' => $referencedTable,
                'referencedColumns' => $this->normalizeNameList($foreignKey['referencedColumns'] ?? null, "Schema foreign key {$name} referencedColumns"),
                'onDelete' => $this->nullableString($foreignKey['onDelete'] ?? null, "Schema foreign key {$name} onDelete"),
                'onUpdate' => $this->nullableString($foreignKey['onUpdate'] ?? null, "Schema foreign key {$name} onUpdate"),
            ];
            $names[$lowerName] = true;
        }

        return $normalized;
    }

    /**
     * @return list<array{name:non-empty-string, expression:non-empty-string, enforced:bool}>
     */
    private function normalizeChecks(mixed $checks): array
    {
        if (!is_array($checks)) {
            throw new \InvalidArgumentException('Schema check constraints must be a list.');
        }

        $normalized = [];
        $names = [];
        foreach (array_values($checks) as $check) {
            if (!is_array($check)) {
                throw new \InvalidArgumentException('Schema check constraints must contain arrays.');
            }
            $name = $check['name'] ?? null;
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Schema check constraint names must be non-empty strings.');
            }
            $lowerName = strtolower($name);
            if (isset($names[$lowerName])) {
                throw new \InvalidArgumentException("Duplicate schema check constraint name: {$name}");
            }
            $expression = $check['expression'] ?? null;
            if (!is_string($expression) || $expression === '') {
                throw new \InvalidArgumentException("Schema check constraint {$name} expression must be a non-empty string.");
            }

            $normalized[] = [
                'name' => $name,
                'expression' => $expression,
                'enforced' => (bool) ($check['enforced'] ?? true),
            ];
            $names[$lowerName] = true;
        }

        return $normalized;
    }

    /**
     * @return list<non-empty-string>
     */
    private function normalizeNameList(mixed $names, string $label): array
    {
        if (!is_array($names) || $names === []) {
            throw new \InvalidArgumentException("{$label} must be a non-empty list.");
        }

        $normalized = [];
        foreach (array_values($names) as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException("{$label} must contain non-empty strings.");
            }
            $normalized[] = $name;
        }

        return $normalized;
    }

    private function nullableString(mixed $value, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$label} must be a string or null.");
        }

        return $value;
    }

    private function normalizeCollation(mixed $collation): string
    {
        if (!is_string($collation) || $collation === '') {
            throw new \InvalidArgumentException('Schema collation must be a non-empty string.');
        }

        return $collation;
    }

    private function characterSetForCollation(string $collation): string
    {
        $underscore = strpos($collation, '_');
        if ($underscore === false) {
            return 'utf8mb4';
        }

        return substr($collation, 0, $underscore);
    }

    private function normalizeTargetRowSize(mixed $targetRowSize): int
    {
        if (!is_int($targetRowSize) || $targetRowSize < 0 || $targetRowSize > 65535) {
            throw new \InvalidArgumentException('Schema targetRowSize must be an integer between 0 and 65535.');
        }

        return $targetRowSize === 0 ? self::DEFAULT_TARGET_ROW_SIZE : $targetRowSize;
    }

    /**
     * @param list<array{name:non-empty-string, expression:non-empty-string, enforced:bool}> $checks
     * @return array<string, array{name:non-empty-string, expression:non-empty-string, enforced:bool}>
     */
    private static function checkConstraintsByLowerName(array $checks): array
    {
        $byName = [];
        foreach ($checks as $check) {
            $byName[strtolower($check['name'])] = $check;
        }

        return $byName;
    }

    /**
     * @return list<array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}>
     */
    private function nonPrimaryKeyColumns(): array
    {
        return array_values(array_filter(
            $this->columns,
            static fn (array $column): bool => !$column['primaryKey']
        ));
    }

    /**
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}|null
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
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}|null
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
     * @return array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool}|null
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
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool} $fromColumn
     * @param array{name:non-empty-string, tag:int, type:non-empty-string, primaryKey:bool, constraints:list<non-empty-string>, default:string|null, generated:string|null, generatedStored:bool, onUpdate:string|null, autoIncrement:bool} $toColumn
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
