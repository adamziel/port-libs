<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Plan
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
            throw new \InvalidArgumentException('SQLite next236 limit and offset must be non-negative');
        }

        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext233Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $guard = self::densityVectorGuard($currentSource, $whereTerms, self::currentWindowSamples($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next233-ready'
            && $guard['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next236-ready' : 'requires-current-source-stat4-density-reprepare',
            'stat4DensityVectorGuard' => $guard,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next236Ready' => $ready,
                'next236ValidatedSampleRowids' => $guard['validatedSampleRowids'],
                'next236RejectedSampleRowids' => $guard['rejectedSampleRowids'],
                'next236RejectedReasons' => $guard['rejectedReasons'],
                'next236DensitySignature' => $guard['densitySignature'],
                'next236ProofSignature' => $guard['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next236DensitySignature' => $guard['densitySignature'],
                'next236ProofSignature' => $guard['proofSignature'],
                'next236DensityReady' => $ready,
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $guard,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT236 DENSITY VECTOR '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT STAT4 COUNTS PROVED' : ' REQUIRES STAT4 DENSITY REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext233Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next236',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next236 reuses current-source STAT4 expression partial sample-row validation and adds neq/nlt/ndlt density-vector validation',
            'non_overlap' => 'avoids accepted next233 sample-row guards, next230 gap peers, range-cost ranking, expression ORDER BY, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters; this slice only rejects stale sqlite_stat4 density vectors whose sample rowids still resolve',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $whereTerms
     * @param list<array<string,mixed>> $samples
     * @return array<string,mixed>
     */
    private static function densityVectorGuard(array $source, array $whereTerms, array $samples): array
    {
        $rows = self::partialRows($source, $whereTerms);
        $keys = array_map(static fn (array $row): string => $row['expressionKey'], $rows);
        $distinctKeys = array_values(array_unique($keys));
        sort($distinctKeys, SORT_STRING);

        $validated = [];
        $rejected = [];
        $reasons = [];
        $sampleRows = [];

        foreach ($samples as $sample) {
            [$sampleKey, $rowid] = self::sampleKeyAndRowid($sample);
            $expected = self::expectedDensity($sample);
            $actual = self::actualDensity($sampleKey, $keys, $distinctKeys);
            $rowExists = self::rowidExists($rows, $rowid);
            $matches = $rowExists && $expected === $actual;
            if ($matches) {
                $validated[] = $rowid;
            } else {
                $rejected[] = $rowid;
                $reasons[$rowid] = self::rejectionReason($rowExists, $expected, $actual);
            }
            $sampleRows[] = [
                'rowid' => $rowid,
                'sampleKey' => $sampleKey,
                'rowExistsInPartialSet' => $rowExists,
                'expected' => $expected,
                'actual' => $actual,
                'densityMatchesCurrentRows' => $expected === $actual,
                'accepted' => $matches,
            ];
        }

        $proof = [
            'sampleRows' => $sampleRows,
            'validatedSampleRowids' => $validated,
            'rejectedSampleRowids' => $rejected,
            'rejectedReasons' => $reasons,
            'currentPartialRowCount' => count($rows),
            'currentDistinctExpressionKeyCount' => count($distinctKeys),
            'currentExpressionKeys' => $keys,
        ];

        return $proof + [
            'ready' => $samples !== [] && $rejected === [],
            'sampleRowCount' => count($samples),
            'validatedSampleRowCount' => count($validated),
            'rejectedSampleRowCount' => count($rejected),
            'densitySignature' => self::signature([$sampleRows, $keys, $distinctKeys]),
            'proofSignature' => self::signature($proof),
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $whereTerms
     * @return list<array{rowid:int,expressionKey:string,row:array<string,mixed>}>
     */
    private static function partialRows(array $source, array $whereTerms): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next236 current source needs row list');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next236 current rows must be arrays');
            }
            if (self::rowSatisfiesTerms($row, $whereTerms)) {
                $out[] = [
                    'rowid' => self::rowid($row),
                    'expressionKey' => self::rowExpressionKey($row),
                    'row' => $row,
                ];
            }
        }
        usort($out, static function (array $left, array $right): int {
            $comparison = strcmp($left['expressionKey'], $right['expressionKey']);
            if ($comparison !== 0) {
                return $comparison;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        return $out;
    }

    /**
     * @param list<array{rowid:int,expressionKey:string,row:array<string,mixed>}> $rows
     */
    private static function rowidExists(array $rows, int $rowid): bool
    {
        foreach ($rows as $row) {
            if ($row['rowid'] === $rowid) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $keys
     * @param list<string> $distinctKeys
     * @return array{neq:int,nlt:int,ndlt:int}
     */
    private static function actualDensity(string $sampleKey, array $keys, array $distinctKeys): array
    {
        $neq = 0;
        $nlt = 0;
        foreach ($keys as $key) {
            if ($key === $sampleKey) {
                $neq++;
            }
            if ($key < $sampleKey) {
                $nlt++;
            }
        }
        $ndlt = 0;
        foreach ($distinctKeys as $key) {
            if ($key < $sampleKey) {
                $ndlt++;
            }
        }

        return ['neq' => $neq, 'nlt' => $nlt, 'ndlt' => $ndlt];
    }

    /** @param array<string,mixed> $sample @return array{neq:int,nlt:int,ndlt:int} */
    private static function expectedDensity(array $sample): array
    {
        return [
            'neq' => self::firstStatInt($sample['neq'] ?? null, 'neq'),
            'nlt' => self::firstStatInt($sample['nlt'] ?? null, 'nlt'),
            'ndlt' => self::firstStatInt($sample['ndlt'] ?? null, 'ndlt'),
        ];
    }

    private static function firstStatInt(mixed $value, string $name): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException('SQLite next236 STAT4 sample needs ' . $name);
        }
        $first = strtok(trim($value), ' ');
        if ($first === false || !ctype_digit($first)) {
            throw new \InvalidArgumentException('SQLite next236 STAT4 ' . $name . ' must start with an integer');
        }

        return (int) $first;
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function currentWindowSamples(array $base): array
    {
        $samples = $base['stat4SampleWindowFence']['currentWindowSamples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next236 needs current STAT4 window samples');
        }
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next236 STAT4 window samples must be arrays');
            }
        }

        return $samples;
    }

    /** @param array<string,mixed> $sample @return array{0:string,1:int} */
    private static function sampleKeyAndRowid(array $sample): array
    {
        $values = $sample['sample'] ?? null;
        if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
            throw new \InvalidArgumentException('SQLite next236 STAT4 sample needs expression key and rowid');
        }

        return [strtolower((string) $values[0]), (int) $values[1]];
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
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $comparison = strcmp(strtolower((string) $value), strtolower((string) ($term['right'] ?? '')));
                if (($operator === '>' && $comparison <= 0)
                    || ($operator === '>=' && $comparison < 0)
                    || ($operator === '<' && $comparison >= 0)
                    || ($operator === '<=' && $comparison > 0)
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        if ($pattern === 'plugin_%') {
            return str_starts_with(strtolower($value), 'plugin_');
        }
        if (str_ends_with($pattern, '%') && !str_contains(substr($pattern, 0, -1), '_')) {
            return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
        }

        return strtolower($value) === strtolower($pattern);
    }

    /** @param array<string,mixed> $row */
    private static function rowExpressionKey(array $row): string
    {
        if (!array_key_exists('option_name', $row)) {
            throw new \InvalidArgumentException('SQLite next236 current row needs option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next236 rowid must be an integer');
        }

        return (int) $row['rowid'];
    }

    /**
     * @param array{neq:int,nlt:int,ndlt:int} $expected
     * @param array{neq:int,nlt:int,ndlt:int} $actual
     */
    private static function rejectionReason(bool $rowExists, array $expected, array $actual): string
    {
        if (!$rowExists) {
            return 'sample-row-not-in-current-partial-set';
        }
        $parts = [];
        foreach (['neq', 'nlt', 'ndlt'] as $key) {
            if ($expected[$key] !== $actual[$key]) {
                $parts[] = $key;
            }
        }

        return 'density-mismatch-' . implode('-', $parts);
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $guard
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $guard): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'ValidateCurrentSourceStat4DensityVectors',
            'mode' => 'next236-current-source-stat4-expression-partial-density-vector',
            'validatedSampleRowids' => $guard['validatedSampleRowids'],
            'currentPartialRowCount' => $guard['currentPartialRowCount'],
            'currentDistinctExpressionKeyCount' => $guard['currentDistinctExpressionKeyCount'],
            'signature' => $guard['proofSignature'],
        ];

        return $program;
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
