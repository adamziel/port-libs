<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRealExpressionAffinityCorpusPlan
{
    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,string> $affinities
     * @return list<array<string,mixed>>
     */
    public static function applyInsertAffinities(array $rows, array $affinities): array
    {
        $out = [];
        foreach ($rows as $row) {
            $next = [];
            foreach ($row as $column => $value) {
                $affinity = self::normalizeAffinity($affinities[$column] ?? 'NONE');
                $next[$column] = match ($affinity) {
                    'INTEGER' => self::applyIntegerColumnAffinity($value),
                    'REAL' => self::applyRealColumnAffinity($value),
                    'NUMERIC' => SQLiteAffinityComparison::applyAffinity($value, 'NUMERIC'),
                    'TEXT' => SQLiteAffinityComparison::applyAffinity($value, 'TEXT'),
                    default => $value,
                };
            }
            $out[] = $next;
        }

        return $out;
    }

    /**
     * @return array{result:bool|null,comparison:int|null,left:mixed,right:mixed,leftStorageClass:string,rightStorageClass:string}
     */
    public static function compareExpression(
        mixed $left,
        mixed $right,
        string $operator,
        string $leftAffinity = 'NONE',
        string $rightAffinity = 'NONE',
        string $collation = 'BINARY'
    ): array {
        $pair = self::coercedPair($left, $right, $leftAffinity, $rightAffinity);
        $comparison = self::compareCoerced($pair['left'], $pair['right'], $collation);
        $result = null;
        if ($comparison !== null) {
            $result = match ($operator) {
                '=', '==', 'IS' => $comparison === 0,
                '!=', '<>', 'IS NOT' => $comparison !== 0,
                '<' => $comparison < 0,
                '<=' => $comparison <= 0,
                '>' => $comparison > 0,
                '>=' => $comparison >= 0,
                default => throw new \InvalidArgumentException("SQLite expression comparison operator {$operator} is not supported"),
            };
        }

        return [
            'result' => $result,
            'comparison' => $comparison,
            'left' => $pair['left'],
            'right' => $pair['right'],
            'leftStorageClass' => $pair['leftStorageClass'],
            'rightStorageClass' => $pair['rightStorageClass'],
        ];
    }

    public static function cast(mixed $value, string $target): mixed
    {
        return match (strtoupper($target)) {
            'TEXT' => self::castText($value),
            'BLOB' => self::castBlob($value),
            'INTEGER', 'INT' => self::castInteger($value),
            'REAL', 'FLOAT', 'DOUBLE' => self::castReal($value),
            'NUMERIC', 'NUM' => self::castNumeric($value),
            default => throw new \InvalidArgumentException("SQLite CAST target {$target} is not supported"),
        };
    }

    public static function unaryNumeric(mixed $value, int $minusCount = 0): mixed
    {
        $numeric = self::castNumeric($value);
        if ($numeric === null) {
            return null;
        }
        if ($minusCount % 2 === 0) {
            return $numeric;
        }

        return is_float($numeric) ? -$numeric : -((int) $numeric);
    }

    public static function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if ($value instanceof SQLiteBlobValue) {
            return "X'" . strtoupper(bin2hex($value->bytes)) . "'";
        }
        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NULL';
            }

            return self::formatReal($value);
        }

        return (string) (int) $value;
    }

    /**
     * @param list<array{id:int,apr:int|float|string}> $aprRows
     * @return list<array{id:int,apr_divided:float,apr_type:string,view:string,automatic_index:bool}>
     */
    public static function affinity3AprViewRows(array $aprRows, string $viewName, bool $automaticIndex): array
    {
        $validViews = ['v1', 'v1rj', 'v2', 'v2rj', 'v2rjrj'];
        if (!in_array($viewName, $validViews, true)) {
            throw new \InvalidArgumentException("SQLite affinity3 view {$viewName} is not supported");
        }

        $rows = self::applyInsertAffinities($aprRows, ['id' => 'INTEGER', 'apr' => 'REAL']);
        $out = [];
        foreach ($rows as $row) {
            $apr = $row['apr'];
            if (!is_int($apr) && !is_float($apr)) {
                continue;
            }

            $out[] = [
                'id' => (int) $row['id'],
                'apr_divided' => $apr / 100.0,
                'apr_type' => self::storageClass($apr),
                'view' => $viewName,
                'automatic_index' => $automaticIndex,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['id'] <=> $b['id']);

        return $out;
    }

    /**
     * @param list<array{id:int|string,name:string}> $dataRows
     * @param list<array{id:int|string,name:string,affinity:string}> $mapRows
     * @return list<array{id:string,name:string,mapped_name:string,source:string,automatic_index:bool}>
     */
    public static function affinity3UsingIdJoinRows(array $dataRows, array $mapRows, string $sourceName, bool $automaticIndex): array
    {
        if (!in_array($sourceName, ['idmap', 'mzed'], true)) {
            throw new \InvalidArgumentException("SQLite affinity3 source {$sourceName} is not supported");
        }

        $data = self::applyInsertAffinities($dataRows, ['id' => 'TEXT', 'name' => 'TEXT']);
        $maps = [];
        foreach ($mapRows as $row) {
            $affinity = strtoupper((string) $row['affinity']);
            $coerced = self::applyInsertAffinities([
                ['id' => $row['id'], 'name' => $row['name']],
            ], ['id' => $sourceName === 'mzed' ? 'BLOB' : $affinity, 'name' => 'TEXT']);
            $maps[] = $coerced[0];
        }

        $out = [];
        foreach ($data as $dataRow) {
            foreach ($maps as $mapRow) {
                $comparison = self::compareExpression($dataRow['id'], $mapRow['id'], '=', 'NONE', 'NONE');
                if ($comparison['result'] !== true) {
                    continue;
                }

                $out[] = [
                    'id' => (string) $dataRow['id'],
                    'name' => (string) $dataRow['name'],
                    'mapped_name' => (string) $mapRow['name'],
                    'source' => $sourceName,
                    'automatic_index' => $automaticIndex,
                ];
            }
        }

        return $out;
    }

    /**
     * @param list<array{name:string,value:mixed,cast?:string|null}> $arms
     * @return list<array{source:string,ordinal:int,name:string,value:mixed,quote:string,storage_class:string,dummy?:string}>
     */
    public static function flexnumCompoundRows(array $arms, string $source): array
    {
        if (!in_array($source, [
            'values',
            'union-all',
            'derived-values',
            'derived-union-all',
            'cross-join-values',
            'cross-join-union-all',
            'view-values',
            'view-union-all',
        ], true)) {
            throw new \InvalidArgumentException("SQLite FLEXNUM compound source {$source} is not supported");
        }

        $rows = [];
        foreach ($arms as $ordinal => $arm) {
            if (!isset($arm['name']) || !is_string($arm['name']) || $arm['name'] === '') {
                throw new \InvalidArgumentException('SQLite FLEXNUM compound arm needs a non-empty name');
            }

            $value = $arm['value'] ?? null;
            $cast = $arm['cast'] ?? null;
            if ($cast !== null) {
                if (!is_string($cast)) {
                    throw new \InvalidArgumentException('SQLite FLEXNUM compound arm cast must be a string or NULL');
                }
                $value = self::cast($value, $cast);
            }

            $row = [
                'source' => $source,
                'ordinal' => $ordinal,
                'name' => $arm['name'],
                'value' => $value,
                'quote' => self::quote($value),
                'storage_class' => self::storageClass($value),
            ];
            if (str_starts_with($source, 'cross-join-')) {
                $row['dummy'] = 'X';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public static function storageClass(mixed $value): string
    {
        return SQLiteAffinityComparison::storageClass($value);
    }

    /**
     * @param list<array{rowid:int,value:int|float|string,stored?:int|float|string|null,ulp_drift?:int|float}> $entries
     * @param list<int> $deletedRowids
     * @return array{integrity:list<string>,missing_rowids:list<int>,imprecise_rowids:list<int>,remaining:list<array{rowid:int,value:float,stored:float,drift:float,ulp:float,classification:string}>,deleted_rowids:list<int>}
     */
    public static function realExpressionIndexDriftPlan(array $entries, array $deletedRowids = [], string $indexName = 'expridx'): array
    {
        $deleted = array_fill_keys(array_map('intval', $deletedRowids), true);
        $integrity = [];
        $missing = [];
        $imprecise = [];
        $remaining = [];

        foreach ($entries as $entry) {
            $rowid = (int) ($entry['rowid'] ?? 0);
            if ($rowid <= 0) {
                throw new \InvalidArgumentException('SQLite real expression index drift rowid must be positive');
            }

            $value = self::castReal($entry['value'] ?? null);
            $stored = array_key_exists('stored', $entry) ? self::castReal($entry['stored']) : $value;
            if ($value === null || $stored === null || !is_finite($value) || !is_finite($stored)) {
                throw new \InvalidArgumentException('SQLite real expression index drift values must be finite REAL-compatible values');
            }

            if (isset($deleted[$rowid])) {
                continue;
            }

            $ulp = self::realUlp($value);
            $drift = abs($stored - $value);
            $ulpDrift = array_key_exists('ulp_drift', $entry) ? abs((float) $entry['ulp_drift']) : ($ulp === 0.0 ? 0.0 : $drift / $ulp);
            $classification = match (true) {
                $ulpDrift === 0.0 => 'ok',
                $ulpDrift <= 2.25 => 'imprecise',
                default => 'missing',
            };

            if ($classification === 'imprecise') {
                $imprecise[] = $rowid;
                $integrity[] = "index {$indexName} stores an imprecise floating-point value for row {$rowid}";
            } elseif ($classification === 'missing') {
                $missing[] = $rowid;
                $integrity[] = "row {$rowid} missing from index {$indexName}";
            }

            $remaining[] = [
                'rowid' => $rowid,
                'value' => $value,
                'stored' => $stored,
                'drift' => $drift,
                'ulp' => $ulp,
                'classification' => $classification,
            ];
        }

        return [
            'integrity' => $integrity === [] ? ['ok'] : $integrity,
            'missing_rowids' => $missing,
            'imprecise_rowids' => $imprecise,
            'remaining' => $remaining,
            'deleted_rowids' => array_keys($deleted),
        ];
    }

    /**
     * @param list<array<string,mixed>> $tableRows
     * @param list<array<string,mixed>> $indexEntries
     * @param list<string> $keyColumns
     * @param list<int|string> $deletedPrimaryKeys
     * @return array{missing_primary_keys:list<int|string>,matched_primary_keys:list<int|string>,stale_index_keys:list<array<string,mixed>>,remaining_table_rows:list<array<string,mixed>>,integrity:list<string>}
     */
    public static function expressionIndexMismatchPlan(
        array $tableRows,
        array $indexEntries,
        array $keyColumns,
        string $primaryKeyColumn = 'rowid',
        array $deletedPrimaryKeys = [],
        string $indexName = 'expridx'
    ): array {
        if ($keyColumns === []) {
            throw new \InvalidArgumentException('SQLite expression index mismatch plan needs at least one key column');
        }

        $deleted = array_fill_keys(array_map(static fn (int|string $key): string => (string) $key, $deletedPrimaryKeys), true);
        $indexKeys = [];
        foreach ($indexEntries as $entry) {
            foreach ($keyColumns as $column) {
                if (!array_key_exists($column, $entry)) {
                    throw new \InvalidArgumentException("SQLite expression index entry is missing key column {$column}");
                }
            }
            if (!array_key_exists($primaryKeyColumn, $entry)) {
                throw new \InvalidArgumentException("SQLite expression index entry is missing primary key column {$primaryKeyColumn}");
            }
            if (isset($deleted[(string) $entry[$primaryKeyColumn]])) {
                continue;
            }

            $key = self::expressionIndexKey($entry, $keyColumns, $primaryKeyColumn);
            $indexKeys[$key] = true;
        }

        $missing = [];
        $matched = [];
        $remainingRows = [];
        $expectedKeys = [];
        foreach ($tableRows as $row) {
            if (!array_key_exists($primaryKeyColumn, $row)) {
                throw new \InvalidArgumentException("SQLite expression table row is missing primary key column {$primaryKeyColumn}");
            }

            $primaryKey = (string) $row[$primaryKeyColumn];
            if (isset($deleted[$primaryKey])) {
                continue;
            }

            foreach ($keyColumns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite expression table row is missing key column {$column}");
                }
            }

            $key = self::expressionIndexKey($row, $keyColumns, $primaryKeyColumn);
            $expectedKeys[$key] = true;
            $remainingRows[] = $row;
            if (isset($indexKeys[$key])) {
                $matched[] = $row[$primaryKeyColumn];
            } else {
                $missing[] = $row[$primaryKeyColumn];
            }
        }

        $stale = [];
        foreach ($indexEntries as $entry) {
            if (isset($deleted[(string) $entry[$primaryKeyColumn]])) {
                continue;
            }
            $key = self::expressionIndexKey($entry, $keyColumns, $primaryKeyColumn);
            if (!isset($expectedKeys[$key])) {
                $stale[] = $entry;
            }
        }

        $integrity = [];
        foreach ($missing as $primaryKey) {
            $integrity[] = "row {$primaryKey} missing from index {$indexName}";
        }
        foreach ($stale as $entry) {
            $integrity[] = "stale key for row {$entry[$primaryKeyColumn]} remains in index {$indexName}";
        }

        return [
            'missing_primary_keys' => $missing,
            'matched_primary_keys' => $matched,
            'stale_index_keys' => $stale,
            'remaining_table_rows' => $remainingRows,
            'integrity' => $integrity === [] ? ['ok'] : $integrity,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $keyColumns
     */
    private static function expressionIndexKey(array $row, array $keyColumns, string $primaryKeyColumn): string
    {
        $parts = [];
        foreach ($keyColumns as $column) {
            $parts[] = self::storageClass($row[$column]) . ':' . self::quote($row[$column]);
        }
        $parts[] = self::storageClass($row[$primaryKeyColumn]) . ':' . self::quote($row[$primaryKeyColumn]);

        return implode('|', $parts);
    }

    /**
     * @return array{left:mixed,right:mixed,leftStorageClass:string,rightStorageClass:string}
     */
    private static function coercedPair(mixed $left, mixed $right, string $leftAffinity, string $rightAffinity): array
    {
        $leftAffinity = self::normalizeAffinity($leftAffinity);
        $rightAffinity = self::normalizeAffinity($rightAffinity);

        if (self::isNumericAffinity($leftAffinity) && in_array($rightAffinity, ['TEXT', 'BLOB', 'NONE'], true)) {
            $right = SQLiteAffinityComparison::applyAffinity($right, 'NUMERIC');
        } elseif (self::isNumericAffinity($rightAffinity) && in_array($leftAffinity, ['TEXT', 'BLOB', 'NONE'], true)) {
            $left = SQLiteAffinityComparison::applyAffinity($left, 'NUMERIC');
        } elseif ($leftAffinity === 'TEXT' && $rightAffinity === 'NONE') {
            $right = self::applyTextColumnAffinity($right);
        } elseif ($rightAffinity === 'TEXT' && $leftAffinity === 'NONE') {
            $left = self::applyTextColumnAffinity($left);
        }

        return [
            'left' => $left,
            'right' => $right,
            'leftStorageClass' => SQLiteAffinityComparison::storageClass($left),
            'rightStorageClass' => SQLiteAffinityComparison::storageClass($right),
        ];
    }

    private static function compareCoerced(mixed $left, mixed $right, string $collation): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }

        return SQLiteAffinityComparison::compare($left, $right, 'BLOB', 'BLOB', $collation);
    }

    private static function normalizeAffinity(string $affinity): string
    {
        $normalized = strtoupper($affinity);

        return match ($normalized) {
            'INT', 'INTEGER' => 'INTEGER',
            'REAL', 'FLOAT', 'DOUBLE' => 'REAL',
            'NUM', 'NUMERIC', 'BOOLEAN', 'DATE', 'DATETIME' => 'NUMERIC',
            'CHAR', 'CLOB', 'VARCHAR', 'TEXT' => 'TEXT',
            'BLOB' => 'BLOB',
            'NONE', '' => 'NONE',
            default => throw new \InvalidArgumentException("SQLite expression affinity {$affinity} is not supported"),
        };
    }

    private static function isNumericAffinity(string $affinity): bool
    {
        return $affinity === 'INTEGER' || $affinity === 'REAL' || $affinity === 'NUMERIC';
    }

    private static function applyIntegerColumnAffinity(mixed $value): mixed
    {
        $numeric = SQLiteAffinityComparison::applyAffinity($value, 'NUMERIC');
        if (($value instanceof SQLiteBlobValue || is_string($value)) && $numeric === $value) {
            return $value;
        }

        return $numeric;
    }

    private static function applyRealColumnAffinity(mixed $value): mixed
    {
        $numeric = SQLiteAffinityComparison::applyAffinity($value, 'NUMERIC');
        if (($value instanceof SQLiteBlobValue || is_string($value)) && $numeric === $value) {
            return $value;
        }

        return self::castReal($numeric);
    }

    private static function applyTextColumnAffinity(mixed $value): mixed
    {
        if ($value === null || is_string($value) || $value instanceof SQLiteBlobValue) {
            return $value;
        }

        return self::castText($value);
    }

    private static function castText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof SQLiteBlobValue) {
            return $value->bytes;
        }
        if (is_float($value)) {
            return self::formatReal($value);
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }

    private static function castBlob(mixed $value): ?SQLiteBlobValue
    {
        $text = self::castText($value);

        return $text === null ? null : new SQLiteBlobValue($text);
    }

    private static function castInteger(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value) || is_int($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            if ($value >= 9223372036854775807.0) {
                return PHP_INT_MAX;
            }
            if ($value <= -9223372036854775808.0) {
                return PHP_INT_MIN;
            }

            return (int) $value;
        }

        $text = $value instanceof SQLiteBlobValue ? $value->bytes : (string) $value;
        if (preg_match('/^\s*([+-]?[0-9]+)/', $text, $matches) !== 1) {
            return 0;
        }

        return self::clampedInteger($matches[1]);
    }

    private static function castReal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value) || is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $text = $value instanceof SQLiteBlobValue ? $value->bytes : (string) $value;
        if (preg_match('/^\s*([+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?)/', $text, $matches) !== 1) {
            return 0.0;
        }

        return (float) $matches[1];
    }

    private static function castNumeric(mixed $value): null|int|float
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = $value instanceof SQLiteBlobValue ? $value->bytes : (string) $value;
        if (preg_match('/^\s*([+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?)/', $text, $matches) !== 1) {
            return 0;
        }

        $literal = $matches[1];
        if (preg_match('/^[+-]?[0-9]+$/', $literal) === 1) {
            return self::clampedInteger($literal);
        }

        $real = (float) $literal;
        if (
            is_finite($real)
            && floor($real) === $real
            && abs($real) <= 2251799813685247.0
        ) {
            return (int) $real;
        }

        return $real;
    }

    private static function clampedInteger(string $literal): int
    {
        $negative = str_starts_with($literal, '-');
        $digits = ltrim($literal, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        if (strlen($digits) > strlen($limit) || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return $negative ? PHP_INT_MIN : PHP_INT_MAX;
        }

        return (int) (($negative ? '-' : '') . $digits);
    }

    private static function formatReal(float $value): string
    {
        if (floor($value) === $value && abs($value) < 1.0e16) {
            return sprintf('%.1F', $value);
        }

        $text = sprintf('%.15G', $value);

        return str_contains($text, 'E') ? str_replace('E', 'e', $text) : $text;
    }

    private static function realUlp(float $value): float
    {
        $scale = max(1.0, abs($value));

        return $scale * 2.220446049250313e-16;
    }
}
