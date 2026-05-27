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
