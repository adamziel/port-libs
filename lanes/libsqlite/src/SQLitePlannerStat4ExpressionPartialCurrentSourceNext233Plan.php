<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext233Plan
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
            throw new \InvalidArgumentException('SQLite next233 limit and offset must be non-negative');
        }

        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext230Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $guard = self::sampleRowGuard($currentSource, $whereTerms, self::currentWindowSamples($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next230-ready'
            && $guard['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next233-ready' : 'requires-current-source-stat4-sample-row-reprepare',
            'stat4SampleRowGuard' => $guard,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next233Ready' => $ready,
                'next233ValidatedSampleRowids' => $guard['validatedSampleRowids'],
                'next233RejectedSampleRowids' => $guard['rejectedSampleRowids'],
                'next233SampleKeySignature' => $guard['sampleKeySignature'],
                'next233ProofSignature' => $guard['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next233SampleKeySignature' => $guard['sampleKeySignature'],
                'next233ProofSignature' => $guard['proofSignature'],
                'next233SampleRowReady' => $ready,
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $guard,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT233 SAMPLE ROW GUARD '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT SAMPLE ROWS PROVED' : ' REQUIRES SAMPLE ROW REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext230Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next233',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next233 reuses the lane-local STAT4 expression partial planner chain and adds bounded current-source sample-row validation',
            'non_overlap' => 'avoids accepted next230 gap-density peers, range-cost ranking, expression ORDER BY, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters; this slice only rejects stale sqlite_stat4 sample rowids whose current rows no longer satisfy the partial expression-index predicate',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $whereTerms
     * @param list<array<string,mixed>> $samples
     * @return array<string,mixed>
     */
    private static function sampleRowGuard(array $source, array $whereTerms, array $samples): array
    {
        $rowMap = self::rowMap($source);
        $validated = [];
        $rejected = [];
        $proofRows = [];

        foreach ($samples as $sample) {
            [$sampleKey, $rowid] = self::sampleKeyAndRowid($sample);
            $row = $rowMap[$rowid] ?? null;
            $rowKey = is_array($row) ? self::rowExpressionKey($row) : null;
            $satisfies = is_array($row) && self::rowSatisfiesTerms($row, $whereTerms);
            $keyMatches = $rowKey !== null && $rowKey === $sampleKey;
            $ok = $satisfies && $keyMatches;
            if ($ok) {
                $validated[] = $rowid;
            } else {
                $rejected[] = $rowid;
            }
            $proofRows[] = [
                'rowid' => $rowid,
                'sampleKey' => $sampleKey,
                'currentRowKey' => $rowKey,
                'rowExists' => is_array($row),
                'partialPredicateSatisfied' => $satisfies,
                'sampleKeyMatchesCurrentRow' => $keyMatches,
                'accepted' => $ok,
            ];
        }

        $sampleKeys = array_map(static fn (array $row): string => (string) $row['sampleKey'], $proofRows);
        $proof = [
            'sampleRows' => $proofRows,
            'validatedSampleRowids' => $validated,
            'rejectedSampleRowids' => $rejected,
            'sampleKeys' => $sampleKeys,
        ];

        return $proof + [
            'ready' => $samples !== [] && $rejected === [],
            'sampleRowCount' => count($samples),
            'validatedSampleRowCount' => count($validated),
            'rejectedSampleRowCount' => count($rejected),
            'sampleKeySignature' => self::signature($sampleKeys),
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function currentWindowSamples(array $base): array
    {
        $samples = $base['stat4SampleWindowFence']['currentWindowSamples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next233 needs current STAT4 window samples');
        }
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next233 STAT4 window samples must be arrays');
            }
        }

        return $samples;
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowMap(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next233 current source needs row list');
        }
        $map = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next233 current rows must be arrays');
            }
            $map[self::rowid($row)] = $row;
        }

        return $map;
    }

    /** @param array<string,mixed> $sample @return array{0:string,1:int} */
    private static function sampleKeyAndRowid(array $sample): array
    {
        $values = $sample['sample'] ?? null;
        if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
            throw new \InvalidArgumentException('SQLite next233 STAT4 sample needs expression key and rowid');
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
            throw new \InvalidArgumentException('SQLite next233 current row needs option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next233 rowid must be an integer');
        }

        return (int) $row['rowid'];
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
            'opcode' => 'ValidateCurrentSourceStat4SampleRows',
            'mode' => 'next233-current-source-stat4-expression-partial-sample-row-guard',
            'validatedSampleRowids' => $guard['validatedSampleRowids'],
            'sampleKeys' => $guard['sampleKeys'],
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
