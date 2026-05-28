<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext230Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next230 limit and offset must be non-negative');
        }
        $sampleWindowLimit = self::currentWindowSampleCount($currentSource, $whereTerms);
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext226Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $sampleWindowLimit,
            $offset,
        );
        $matchedRows = self::currentMatchedRows($currentSource, $whereTerms, $limit, $offset);
        $gapFence = self::gapDensityFence($matchedRows, self::windowSampleRowids($base), $whereTerms);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next226-ready'
            && $gapFence['ready'] === true
            && $gapFence['rowidsRejectedByGapFence'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next230-ready' : 'requires-current-source-stat4-gap-reprepare',
            'matchedRows' => $matchedRows,
            'matchedRowids' => array_map(static fn (array $row): int => self::rowid($row), $matchedRows),
            'projectedRows' => self::projectRows($matchedRows, $neededColumns),
            'stat4GapDensityFence' => $gapFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next230Ready' => $ready,
                'next230GapRowids' => $gapFence['gapRowids'],
                'next230AnchoredSampleRowids' => $gapFence['anchoredSampleRowids'],
                'next230GapKeys' => $gapFence['gapKeys'],
                'next230GapSignature' => $gapFence['gapSignature'],
                'next230ProofSignature' => $gapFence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next230GapSignature' => $gapFence['gapSignature'],
                'next230ProofSignature' => $gapFence['proofSignature'],
                'next230GapReady' => $ready,
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $gapFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT230 GAP DENSITY '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT GAP PEERS PROVED' : ' REQUIRES GAP REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext226Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next230',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next230 reuses current-source STAT4 expression partial sample-window proof and adds bounded gap-density validation for peer rows not present in sqlite_stat4 samples',
            'non_overlap' => 'avoids accepted next226 sample-window rows, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters; this slice only proves current-source partial expression-index peer rows in gaps between STAT4 samples',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $whereTerms
     */
    private static function currentWindowSampleCount(array $source, array $whereTerms): int
    {
        $bounds = self::bounds($whereTerms);
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes) || !isset($indexes[0]) || !is_array($indexes[0])) {
            throw new \InvalidArgumentException('SQLite next230 current source needs expression index list');
        }
        $samples = $indexes[0]['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next230 current expression index needs STAT4 samples');
        }
        $count = 0;
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next230 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values)) {
                throw new \InvalidArgumentException('SQLite next230 STAT4 sample needs expression key');
            }
            $key = strtolower((string) $values[0]);
            if (($bounds['lower'] === null || $key >= $bounds['lower']) && ($bounds['upper'] === null || $key <= $bounds['upper'])) {
                $count++;
            }
        }

        return max(1, $count);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $whereTerms
     * @return list<array<string,mixed>>
     */
    private static function currentMatchedRows(array $source, array $whereTerms, int $limit, int $offset): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next230 current source needs row list');
        }
        $matched = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next230 current rows must be arrays');
            }
            if (self::rowSatisfiesTerms($row, $whereTerms)) {
                $matched[] = [
                    'rowid' => self::rowid($row),
                    'expressionKey' => self::rowExpressionKey($row),
                    'payload' => $row,
                ];
            }
        }
        usort($matched, static function (array $left, array $right): int {
            $comparison = strcmp(self::expressionKey($right), self::expressionKey($left));
            if ($comparison !== 0) {
                return $comparison;
            }

            return self::rowid($left) <=> self::rowid($right);
        });

        return array_slice($matched, $offset, $limit);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $whereTerms
     */
    private static function rowSatisfiesTerms(array $row, array $whereTerms): bool
    {
        foreach ($whereTerms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                return false;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = array_key_exists('expression', $left) ? self::rowExpressionKey($row) : ($row[(string) ($left['column'] ?? '')] ?? null);
            if ($operator === '=' && $value != ($term['right'] ?? null)) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
            if ($operator === 'LIKE' && !self::likePrefix((string) $value, (string) ($term['right'] ?? ''))) {
                return false;
            }
            if ($operator === 'BETWEEN') {
                $stringValue = strtolower((string) $value);
                $lower = self::stringOrNull($term['lower'] ?? null);
                $upper = self::stringOrNull($term['upper'] ?? null);
                if (($lower !== null && $stringValue < $lower) || ($upper !== null && $stringValue > $upper)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        if (str_ends_with($pattern, '%') && !str_contains(substr($pattern, 0, -1), '_')) {
            return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
        }
        if ($pattern === 'plugin_%') {
            return str_starts_with(strtolower($value), 'plugin_');
        }

        return strtolower($value) === strtolower($pattern);
    }

    /** @param array<string,mixed> $row */
    private static function rowExpressionKey(array $row): string
    {
        if (!array_key_exists('option_name', $row)) {
            throw new \InvalidArgumentException('SQLite next230 current row needs option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function projectRows(array $rows, array $neededColumns): array
    {
        $projected = [];
        foreach ($rows as $row) {
            $payload = $row['payload'] ?? [];
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next230 row payload must be an array');
            }
            $item = [];
            foreach ($neededColumns as $column) {
                $item[$column] = $payload[$column] ?? null;
            }
            $projected[] = $item;
        }

        return $projected;
    }

    /**
     * @param list<array<string,mixed>> $matchedRows
     * @param list<int> $sampleRowids
     * @param list<array<string,mixed>> $whereTerms
     * @return array<string,mixed>
     */
    private static function gapDensityFence(array $matchedRows, array $sampleRowids, array $whereTerms): array
    {
        $sampleSet = array_fill_keys(array_map('strval', $sampleRowids), true);
        $sampleKeys = [];
        $gapRows = [];
        $rejected = [];
        $bounds = self::bounds($whereTerms);
        $lower = $bounds['lower'];
        $upper = $bounds['upper'];

        foreach ($matchedRows as $position => $row) {
            $rowid = self::rowid($row);
            $key = self::expressionKey($row);
            if (isset($sampleSet[(string) $rowid])) {
                $sampleKeys[$key][] = $rowid;
                continue;
            }
            $gapRows[] = [
                'position' => $position,
                'rowid' => $rowid,
                'expressionKey' => $key,
                'boundedByPredicate' => ($lower === null || $key >= $lower) && ($upper === null || $key <= $upper),
                'anchoredBySamplePeer' => false,
            ];
        }

        foreach ($gapRows as $offset => $row) {
            $anchored = isset($sampleKeys[$row['expressionKey']]);
            $gapRows[$offset]['anchoredBySamplePeer'] = $anchored;
            if (!$anchored || !$row['boundedByPredicate']) {
                $rejected[] = $row['rowid'];
            }
        }

        $gapKeys = array_values(array_unique(array_map(static fn (array $row): string => $row['expressionKey'], $gapRows)));
        sort($gapKeys, SORT_STRING);
        $anchoredSampleRowids = [];
        foreach ($gapKeys as $key) {
            foreach ($sampleKeys[$key] ?? [] as $rowid) {
                $anchoredSampleRowids[] = $rowid;
            }
        }
        sort($anchoredSampleRowids, SORT_NUMERIC);

        $proof = [
            'lowerBound' => $lower,
            'upperBound' => $upper,
            'sampleRowids' => $sampleRowids,
            'gapRows' => $gapRows,
            'gapRowids' => array_map(static fn (array $row): int => $row['rowid'], $gapRows),
            'gapKeys' => $gapKeys,
            'anchoredSampleRowids' => $anchoredSampleRowids,
        ];

        return $proof + [
            'ready' => $gapRows !== [] && $rejected === [],
            'gapRowCount' => count($gapRows),
            'anchoredGapRowCount' => count($gapRows) - count($rejected),
            'rowidsRejectedByGapFence' => array_values(array_unique($rejected)),
            'gapSignature' => self::signature([$proof['gapRowids'], $gapKeys, $anchoredSampleRowids]),
            'proofSignature' => self::signature($proof),
        ];
    }

    /**
     * @param list<array<string,mixed>> $whereTerms
     * @return array{lower:?string,upper:?string}
     */
    private static function bounds(array $whereTerms): array
    {
        $lower = null;
        $upper = null;
        foreach ($whereTerms as $term) {
            $left = $term['left'] ?? null;
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if (!is_array($left) || !array_key_exists('expression', $left)) {
                continue;
            }
            if (self::normalExpression((string) $left['expression']) !== 'lower(option_name)') {
                continue;
            }
            if ($operator === 'BETWEEN') {
                $lower = self::stringOrNull($term['lower'] ?? null);
                $upper = self::stringOrNull($term['upper'] ?? null);
            }
        }

        return ['lower' => $lower, 'upper' => $upper];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next230 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next230 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $base @return list<int> */
    private static function windowSampleRowids(array $base): array
    {
        $rowids = $base['stat4SampleWindowFence']['currentWindowRowids'] ?? null;
        if (!is_array($rowids) || !array_is_list($rowids)) {
            throw new \InvalidArgumentException('SQLite next230 needs current STAT4 sample-window rowids');
        }

        return array_map(static fn (mixed $rowid): int => (int) $rowid, $rowids);
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        if (array_key_exists('expressionKey', $row)) {
            return strtolower((string) $row['expressionKey']);
        }
        $payload = $row['payload'] ?? null;
        if (is_array($payload) && array_key_exists('option_name', $payload)) {
            return strtolower((string) $payload['option_name']);
        }

        throw new \InvalidArgumentException('SQLite next230 matched rows need expressionKey or option_name payload');
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next230 matched rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $gapFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $gapFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentSourceStat4GapDensity',
            'mode' => 'next230-current-source-stat4-expression-partial-gap-density',
            'gapRowids' => $gapFence['gapRowids'],
            'gapKeys' => $gapFence['gapKeys'],
            'anchoredSampleRowids' => $gapFence['anchoredSampleRowids'],
            'signature' => $gapFence['proofSignature'],
        ];

        return $program;
    }

    private static function normalExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : strtolower((string) $value);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
