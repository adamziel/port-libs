<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext183Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        [$inOffset, $inTerm] = self::inTerm($whereTerms);
        $expression = self::normalizeExpression((string) ($inTerm['left']['expression'] ?? ''));
        $values = self::inValues($inTerm['values'] ?? null);
        $probes = [];
        $matched = [];
        $seenRowids = [];
        $ready = true;
        $selectedSource = null;
        $stale = false;

        foreach ($values as $ordinal => $value) {
            $probeTerms = $whereTerms;
            $probeTerms[$inOffset] = [
                'left' => ['expression' => $expression],
                'operator' => '=',
                'right' => ['literal' => $value],
            ];
            $probe = SQLitePlannerStat4ExpressionPartialCurrentSourceNext171Plan::materialize(
                $preparedSource,
                $currentSource,
                $probeTerms,
                $neededColumns,
            );
            $probeReady = ($probe['status'] ?? null) === 'stat4-expression-partial-current-source-next171-ready';
            $ready = $ready && $probeReady;
            $selectedSource ??= (string) ($probe['selectedSource'] ?? 'prepared');
            $stale = $stale || (bool) ($probe['stalePreparedStatement'] ?? false);

            $probeRows = [];
            foreach (self::rowList($probe['matchedRows'] ?? []) as $row) {
                $rowid = self::rowid($row);
                if (isset($seenRowids[$rowid])) {
                    continue;
                }
                $seenRowids[$rowid] = true;
                $row['inOrdinal'] = $ordinal;
                $row['inValue'] = $value;
                $matched[] = $row;
                $probeRows[] = $rowid;
            }

            $probes[] = [
                'inOrdinal' => $ordinal,
                'inValue' => $value,
                'ready' => $probeReady,
                'selectedSource' => $probe['selectedSource'] ?? null,
                'unsampledEqualityKey' => $probe['unsampledEqualityKey'] ?? null,
                'stat4Bracket' => $probe['stat4Bracket'] ?? [],
                'matchedRowids' => $probeRows,
                'detail' => $probe['detail'] ?? null,
            ];
        }

        usort($matched, static function (array $left, array $right): int {
            return ((int) ($left['inOrdinal'] ?? 0) <=> (int) ($right['inOrdinal'] ?? 0))
                ?: ((int) ($left['rowid'] ?? 0) <=> (int) ($right['rowid'] ?? 0));
        });

        $rowids = array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), $matched);
        $keys = array_map(static fn (array $row): string => (string) ($row['expressionKey'] ?? ''), $matched);
        $ready = $ready && $matched !== [] && count($values) === count(array_unique($values, SORT_REGULAR));

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next183-ready' : 'requires-next-stage',
            'selectedSource' => $selectedSource ?? 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'expression' => $expression,
            'inValues' => $values,
            'inProbeCount' => count($values),
            'probes' => $probes,
            'matchedRows' => $ready ? $matched : [],
            'matchedRowids' => $ready ? $rowids : [],
            'matchedExpressionKeys' => $ready ? $keys : [],
            'deduplicatedRowids' => array_values(array_keys($seenRowids)),
            'inOrderFence' => [
                'ready' => $ready,
                'expression' => $expression,
                'inValues' => $values,
                'rowids' => $ready ? $rowids : [],
                'keys' => $ready ? $keys : [],
                'probeSignatures' => array_map(static fn (array $probe): string => self::signature($probe['stat4Bracket'] ?? []), $probes),
                'rowStreamSignature' => self::signature($ready ? $rowids : []),
            ],
            'cursorProgram' => self::cursorProgram($ready, $expression, $values, $rowids),
            'detail' => ($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT183 IN MULTI-PROBE',
            'dependencies' => [
                'SQLitePlannerStat4ExpressionPartialCurrentSourceNext171Plan',
                'sqlite-sqlplanner-stat4-expression-partial-current-source-next183',
            ],
            'dependency_closure' => 'no new support component needed; next183 reuses lane-local STAT4 expression partial equality brackets and adds bounded IN-list multi-probe cursor diagnostics',
            'non_overlap' => 'avoids accepted next154 equality/IN/BETWEEN row streams, next168 LIKE-prefix, next171 single unsampled equality bracket, next180 descending scans, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters; this slice only handles current-source IN-list multi-probe admission over a partial expression index',
        ];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array{0:int,1:array<string,mixed>}
     */
    private static function inTerm(array $terms): array
    {
        foreach ($terms as $offset => $term) {
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'IN') {
                continue;
            }
            if (!is_array($term['left'] ?? null) || !isset($term['left']['expression'])) {
                throw new \InvalidArgumentException('SQLite next183 IN term must target an expression');
            }

            return [$offset, $term];
        }

        throw new \InvalidArgumentException('SQLite next183 needs an expression IN term');
    }

    /** @return list<mixed> */
    private static function inValues(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            throw new \InvalidArgumentException('SQLite next183 IN values must be a non-empty list');
        }
        $out = [];
        foreach ($values as $value) {
            $out[] = is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
        }

        return $out;
    }

    /**
     * @param mixed $rows
     * @return list<array<string,mixed>>
     */
    private static function rowList(mixed $rows): array
    {
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next183 probe rows must be a list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next183 probe rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        $rowid = $row['rowid'] ?? null;
        if (!is_int($rowid) || $rowid < 0) {
            throw new \InvalidArgumentException('SQLite next183 rowid must be a non-negative integer');
        }

        return $rowid;
    }

    /**
     * @param list<mixed> $values
     * @param list<int> $rowids
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(bool $ready, string $expression, array $values, array $rowids): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'STAT4 expression partial IN multi-probe not usable']];
        }

        return [
            ['opcode' => 'OpenRead', 'mode' => 'next183-stat4-expression-partial-in', 'expression' => $expression],
            ['opcode' => 'RewindInList', 'values' => $values],
            ['opcode' => 'SeekProbeBracket', 'probeCount' => count($values)],
            ['opcode' => 'IdxEq', 'values' => $values],
            ['opcode' => 'DeduplicateRowid', 'rowids' => $rowids],
            ['opcode' => 'ResultRow', 'rowids' => $rowids],
            ['opcode' => 'NextInProbe'],
        ];
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
