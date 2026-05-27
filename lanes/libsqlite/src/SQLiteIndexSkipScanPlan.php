<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteIndexSkipScanPlan
{
    /**
     * @param list<array<string, mixed>> $rows
     * @return array{
     *     indexName:string,
     *     skippedColumn:string,
     *     rangeColumn:string,
     *     lowerInclusive:mixed,
     *     upperBound:mixed,
     *     upperInclusive:bool,
     *     loops:list<array{prefix:mixed, examined:int, matched:int, rowids:list<int>}>,
     *     rows:list<array<string, mixed>>,
     *     rowids:list<int>,
     *     omittedNullRangeRows:int,
     *     estimatedSeeks:int,
     *     usesSkipScan:bool
     * }
     */
    public static function betweenRows(
        array $rows,
        string $indexName,
        string $skippedColumn,
        string $rangeColumn,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive = true,
        ?int $limit = null,
        int $offset = 0,
        string $collation = 'BINARY',
    ): array {
        if ($indexName === '' || $skippedColumn === '' || $rangeColumn === '') {
            throw new \InvalidArgumentException('SQLite skip-scan index, skipped column, and range column names are required');
        }
        if ($skippedColumn === $rangeColumn) {
            throw new \InvalidArgumentException('SQLite skip-scan range column must differ from the skipped leading column');
        }
        if ($lowerInclusive === null && $upperBound === null) {
            throw new \InvalidArgumentException('SQLite skip-scan BETWEEN planning requires at least one range bound');
        }
        if ($limit !== null && $limit < 0) {
            throw new \InvalidArgumentException('SQLite skip-scan limit cannot be negative');
        }
        if ($offset < 0) {
            throw new \InvalidArgumentException('SQLite skip-scan offset cannot be negative');
        }

        $normalizedCollation = strtoupper($collation);
        if (!in_array($normalizedCollation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite skip-scan collation: {$collation}");
        }

        $ordered = array_values($rows);
        usort(
            $ordered,
            static function (array $left, array $right) use ($skippedColumn, $rangeColumn, $normalizedCollation): int {
                $prefixComparison = self::compare($left[$skippedColumn] ?? null, $right[$skippedColumn] ?? null, 'BINARY');
                if ($prefixComparison !== 0) {
                    return $prefixComparison;
                }

                $rangeComparison = self::compare($left[$rangeColumn] ?? null, $right[$rangeColumn] ?? null, $normalizedCollation);
                if ($rangeComparison !== 0) {
                    return $rangeComparison;
                }

                return ((int) ($left['rowid'] ?? 0)) <=> ((int) ($right['rowid'] ?? 0));
            },
        );

        $prefixes = [];
        foreach ($ordered as $row) {
            if (!array_key_exists($skippedColumn, $row)) {
                throw new \InvalidArgumentException("SQLite skip-scan row is missing skipped column {$skippedColumn}");
            }
            if (!array_key_exists($rangeColumn, $row)) {
                throw new \InvalidArgumentException("SQLite skip-scan row is missing range column {$rangeColumn}");
            }
            $prefixKey = self::key($row[$skippedColumn]);
            if (!array_key_exists($prefixKey, $prefixes)) {
                $prefixes[$prefixKey] = $row[$skippedColumn];
            }
        }

        $loops = [];
        $matches = [];
        $omittedNullRangeRows = 0;
        $seenMatched = 0;
        foreach ($prefixes as $prefix) {
            $examined = 0;
            $matched = 0;
            $rowids = [];
            foreach ($ordered as $row) {
                if (self::compare($row[$skippedColumn], $prefix, 'BINARY') !== 0) {
                    continue;
                }

                $examined++;
                $rangeValue = $row[$rangeColumn];
                if ($rangeValue === null) {
                    $omittedNullRangeRows++;
                    continue;
                }
                if (!self::inBetweenRange($rangeValue, $lowerInclusive, $upperBound, $upperInclusive, $normalizedCollation)) {
                    continue;
                }

                $matched++;
                $rowid = (int) ($row['rowid'] ?? 0);
                $rowids[] = $rowid;
                if ($seenMatched++ < $offset) {
                    continue;
                }
                if ($limit === null || count($matches) < $limit) {
                    $matches[] = $row;
                }
            }

            $loops[] = [
                'prefix' => $prefix,
                'examined' => $examined,
                'matched' => $matched,
                'rowids' => $rowids,
            ];
        }

        return [
            'indexName' => $indexName,
            'skippedColumn' => $skippedColumn,
            'rangeColumn' => $rangeColumn,
            'lowerInclusive' => $lowerInclusive,
            'upperBound' => $upperBound,
            'upperInclusive' => $upperInclusive,
            'loops' => $loops,
            'rows' => $matches,
            'rowids' => array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $matches),
            'omittedNullRangeRows' => $omittedNullRangeRows,
            'estimatedSeeks' => count($loops),
            'usesSkipScan' => count($loops) > 1,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string,mixed>> $queryTerms
     * @return array{
     *     indexName:string,
     *     partial:bool,
     *     partialPredicateImplied:bool,
     *     skippedPartialRows:int,
     *     skippedColumn:string,
     *     rangeColumn:string,
     *     lowerInclusive:mixed,
     *     upperBound:mixed,
     *     upperInclusive:bool,
     *     loops:list<array{prefix:mixed, examined:int, matched:int, rowids:list<int>}>,
     *     rows:list<array<string, mixed>>,
     *     rowids:list<int>,
     *     omittedNullRangeRows:int,
     *     estimatedSeeks:int,
     *     usesSkipScan:bool,
     *     status:string,
     *     reason:string|null
     * }
     */
    public static function betweenPartialRows(
        array $rows,
        string $indexName,
        string $skippedColumn,
        string $rangeColumn,
        mixed $lowerInclusive,
        mixed $upperBound,
        SQLiteIndexPredicate $partialPredicate,
        array $queryTerms,
        bool $upperInclusive = true,
        ?int $limit = null,
        int $offset = 0,
        string $collation = 'BINARY',
    ): array {
        if (!self::partialPredicateIsImplied($partialPredicate, $queryTerms, $collation)) {
            return [
                'indexName' => $indexName,
                'partial' => true,
                'partialPredicateImplied' => false,
                'skippedPartialRows' => 0,
                'skippedColumn' => $skippedColumn,
                'rangeColumn' => $rangeColumn,
                'lowerInclusive' => $lowerInclusive,
                'upperBound' => $upperBound,
                'upperInclusive' => $upperInclusive,
                'loops' => [],
                'rows' => [],
                'rowids' => [],
                'omittedNullRangeRows' => 0,
                'estimatedSeeks' => 0,
                'usesSkipScan' => false,
                'status' => 'unusable',
                'reason' => 'query constraints do not imply partial-index WHERE predicate',
            ];
        }

        $indexedRows = [];
        $skippedPartialRows = 0;
        foreach ($rows as $row) {
            if (self::rowSatisfiesPartialPredicate($row, $partialPredicate, $collation)) {
                $indexedRows[] = $row;
                continue;
            }
            $skippedPartialRows++;
        }

        $plan = self::betweenRows(
            $indexedRows,
            $indexName,
            $skippedColumn,
            $rangeColumn,
            $lowerInclusive,
            $upperBound,
            $upperInclusive,
            $limit,
            $offset,
            $collation,
        );

        return $plan + [
            'partial' => true,
            'partialPredicateImplied' => true,
            'skippedPartialRows' => $skippedPartialRows,
            'status' => 'usable',
            'reason' => null,
        ];
    }

    private static function inBetweenRange(
        mixed $value,
        mixed $lowerInclusive,
        mixed $upperBound,
        bool $upperInclusive,
        string $collation,
    ): bool {
        if ($lowerInclusive !== null && self::compare($value, $lowerInclusive, $collation) < 0) {
            return false;
        }
        if ($upperBound !== null) {
            $upperComparison = self::compare($value, $upperBound, $collation);
            if ($upperComparison > 0 || ($upperComparison === 0 && !$upperInclusive)) {
                return false;
            }
        }

        return true;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null || $right === null) {
            return $left === $right ? 0 : ($left === null ? -1 : 1);
        }
        if ((is_int($left) || is_float($left)) && (is_int($right) || is_float($right))) {
            return $left <=> $right;
        }

        $leftText = (string) $left;
        $rightText = (string) $right;
        if ($collation === 'NOCASE') {
            $leftText = self::asciiLower($leftText);
            $rightText = self::asciiLower($rightText);
        } elseif ($collation === 'RTRIM') {
            $leftText = rtrim($leftText, " \t\n\r\0\x0B");
            $rightText = rtrim($rightText, " \t\n\r\0\x0B");
        }

        return strcmp($leftText, $rightText) <=> 0;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateIsImplied(SQLiteIndexPredicate $predicate, array $terms, string $collation): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if (!$subPredicate instanceof SQLiteIndexPredicate || !self::partialPredicateIsImplied($subPredicate, $terms, $collation)) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if ($subPredicate instanceof SQLiteIndexPredicate && self::partialPredicateIsImplied($subPredicate, $terms, $collation)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($terms as $term) {
            $constraint = self::constraintFromPredicate($term);
            if ($constraint === null) {
                continue;
            }
            if ($constraint['operator'] === 'point'
                && $predicate->isImpliedByPointLookup($constraint['column'], $constraint['values'], $collation)
            ) {
                return true;
            }
            if ($constraint['operator'] === 'IN'
                && is_array($constraint['values'])
                && $predicate->isImpliedByInListLookup($constraint['column'], $constraint['values'], $collation)
            ) {
                return true;
            }
            if ($constraint['operator'] === 'BETWEEN'
                && is_array($constraint['values'])
                && $predicate->isImpliedByRangeLookup(
                    $constraint['column'],
                    $constraint['values']['lower'] ?? null,
                    $constraint['values']['upper'] ?? null,
                    true,
                    $collation,
                )
            ) {
                return true;
            }
            if (str_starts_with($constraint['operator'], 'range-')
                && self::rangeConstraintImpliesPartialPredicate($predicate, $constraint, $collation)
            ) {
                return true;
            }
            if ($constraint['operator'] === 'is-not-null'
                && $predicate->operator === SQLiteIndexPredicate::IS_NOT_NULL
                && strcasecmp($predicate->columnName, $constraint['column']) === 0
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function constraintFromPredicate(array $predicate): ?array
    {
        $operator = strtoupper((string) ($predicate['operator'] ?? ''));
        if ($operator === '=' || $operator === '==') {
            return self::binaryConstraint($predicate, 'point');
        }
        if (in_array($operator, ['<', '<=', '>', '>='], true)) {
            return self::binaryConstraint($predicate, 'range-' . $operator);
        }
        if ($operator === 'IN') {
            $column = self::columnOperand($predicate['left'] ?? null);
            $values = $predicate['values'] ?? null;
            if ($column === null || !is_array($values) || !array_is_list($values)) {
                return null;
            }

            return ['column' => $column, 'operator' => 'IN', 'values' => $values];
        }
        if ($operator === 'BETWEEN') {
            $column = self::columnOperand($predicate['left'] ?? null);
            if ($column === null || !array_key_exists('lower', $predicate) || !array_key_exists('upper', $predicate)) {
                return null;
            }

            return ['column' => $column, 'operator' => 'BETWEEN', 'values' => [
                'lower' => $predicate['lower'],
                'upper' => $predicate['upper'],
            ]];
        }
        if ($operator === 'IS NOT NULL') {
            $column = self::columnOperand($predicate['left'] ?? null);

            return $column === null ? null : ['column' => $column, 'operator' => 'is-not-null', 'values' => true];
        }

        return null;
    }

    /**
     * @return null|array{column:string,operator:string,values:mixed}
     */
    private static function binaryConstraint(array $predicate, string $operator): ?array
    {
        $left = self::columnOperand($predicate['left'] ?? null);
        $right = self::columnOperand($predicate['right'] ?? null);
        if ($left !== null && $right === null && array_key_exists('right', $predicate)) {
            return ['column' => $left, 'operator' => $operator, 'values' => $predicate['right']];
        }
        if ($right !== null && $left === null && array_key_exists('left', $predicate)) {
            return ['column' => $right, 'operator' => self::reverseRangeOperator($operator), 'values' => $predicate['left']];
        }

        return null;
    }

    private static function columnOperand(mixed $operand): ?string
    {
        if (!is_array($operand)) {
            return null;
        }
        $column = $operand['column'] ?? null;

        return is_string($column) && $column !== '' ? $column : null;
    }

    /**
     * @param array{column:string,operator:string,values:mixed} $constraint
     */
    private static function rangeConstraintImpliesPartialPredicate(
        SQLiteIndexPredicate $predicate,
        array $constraint,
        string $collation,
    ): bool {
        return match ($constraint['operator']) {
            'range->' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, false, $collation),
            'range->=' => $predicate->isImpliedByRangeLookup($constraint['column'], $constraint['values'], null, true, $collation),
            'range-<' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], false, $collation),
            'range-<=' => $predicate->isImpliedByRangeLookup($constraint['column'], null, $constraint['values'], true, $collation),
            default => false,
        };
    }

    private static function rowSatisfiesPartialPredicate(array $row, SQLiteIndexPredicate $predicate, string $collation): bool
    {
        if ($predicate->operator === SQLiteIndexPredicate::AND && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if (!$subPredicate instanceof SQLiteIndexPredicate || !self::rowSatisfiesPartialPredicate($row, $subPredicate, $collation)) {
                    return false;
                }
            }

            return true;
        }

        if ($predicate->operator === SQLiteIndexPredicate::OR && is_array($predicate->value)) {
            foreach ($predicate->value as $subPredicate) {
                if ($subPredicate instanceof SQLiteIndexPredicate && self::rowSatisfiesPartialPredicate($row, $subPredicate, $collation)) {
                    return true;
                }
            }

            return false;
        }

        $value = $row[$predicate->columnName] ?? null;

        return match ($predicate->operator) {
            SQLiteIndexPredicate::IS_NOT_NULL => $value !== null,
            SQLiteIndexPredicate::EQUALS => self::compare($value, $predicate->value, $collation) === 0,
            SQLiteIndexPredicate::NOT_EQUALS => $value !== null && self::compare($value, $predicate->value, $collation) !== 0,
            SQLiteIndexPredicate::LESS_THAN => $value !== null && self::compare($value, $predicate->value, $collation) < 0,
            SQLiteIndexPredicate::LESS_THAN_OR_EQUAL => $value !== null && self::compare($value, $predicate->value, $collation) <= 0,
            SQLiteIndexPredicate::GREATER_THAN => $value !== null && self::compare($value, $predicate->value, $collation) > 0,
            SQLiteIndexPredicate::GREATER_THAN_OR_EQUAL => $value !== null && self::compare($value, $predicate->value, $collation) >= 0,
            SQLiteIndexPredicate::BETWEEN => is_array($predicate->value)
                && array_key_exists('lower', $predicate->value)
                && array_key_exists('upper', $predicate->value)
                && $value !== null
                && self::compare($value, $predicate->value['lower'], $collation) >= 0
                && self::compare($value, $predicate->value['upper'], $collation) <= 0,
            SQLiteIndexPredicate::IN_LIST => is_array($predicate->value) && self::valueInList($value, $predicate->value, $collation),
            default => false,
        };
    }

    /**
     * @param list<mixed> $values
     */
    private static function valueInList(mixed $value, array $values, string $collation): bool
    {
        foreach ($values as $candidate) {
            if (self::compare($value, $candidate, $collation) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function reverseRangeOperator(string $operator): string
    {
        return match ($operator) {
            'range-<' => 'range->',
            'range-<=' => 'range->=',
            'range->' => 'range-<',
            'range->=' => 'range-<=',
            default => $operator,
        };
    }

    private static function asciiLower(string $value): string
    {
        $bytes = $value;
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $ord = ord($bytes[$i]);
            if ($ord >= 0x41 && $ord <= 0x5a) {
                $bytes[$i] = chr($ord + 0x20);
            }
        }

        return $bytes;
    }

    private static function key(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }
}
