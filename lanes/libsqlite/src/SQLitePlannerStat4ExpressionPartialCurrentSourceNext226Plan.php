<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext226Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext219Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );

        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $preparedIndex = self::indexByName($preparedSource, $selectedName);
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $bounds = self::expressionBounds($whereTerms, (string) ($currentIndex['expression'] ?? ''));
        $preparedWindow = self::stat4Window($preparedIndex, $bounds);
        $currentWindow = self::stat4Window($currentIndex, $bounds);
        $currentOutside = self::stat4OutsideWindow($currentIndex, $bounds);
        $windowReady = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next219-ready'
            && $bounds['lower'] !== null
            && $bounds['upper'] !== null
            && $currentWindow !== []
            && self::sortedRowids(self::matchedRows($base)) === self::sortedRowidsFromSamples($currentWindow);
        $stat4Fence = [
            'expression' => $currentIndex['expression'] ?? null,
            'lowerBound' => $bounds['lower'],
            'upperBound' => $bounds['upper'],
            'preparedWindowSamples' => $preparedWindow,
            'currentWindowSamples' => $currentWindow,
            'currentOutsideWindowSamples' => $currentOutside,
            'preparedWindowRowids' => self::rowidsFromSamples($preparedWindow),
            'currentWindowRowids' => self::rowidsFromSamples($currentWindow),
            'currentOutsideWindowRowids' => self::rowidsFromSamples($currentOutside),
            'matchedRowids' => self::rowids(self::matchedRows($base)),
            'windowSampleCount' => count($currentWindow),
            'outsideWindowSampleCount' => count($currentOutside),
            'windowSignature' => self::signature($currentWindow),
            'outsideWindowSignature' => self::signature($currentOutside),
            'ready' => $windowReady,
        ];

        return array_replace($base, [
            'status' => $windowReady ? 'stat4-expression-partial-current-source-next226-ready' : 'requires-current-source-stat4-window-reprepare',
            'stat4SampleWindowFence' => $stat4Fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next226Ready' => $windowReady,
                'next226Expression' => $stat4Fence['expression'],
                'next226LowerBound' => $bounds['lower'],
                'next226UpperBound' => $bounds['upper'],
                'next226WindowRowids' => $stat4Fence['currentWindowRowids'],
                'next226OutsideWindowRowids' => $stat4Fence['currentOutsideWindowRowids'],
                'next226WindowSignature' => $stat4Fence['windowSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next226WindowSignature' => $stat4Fence['windowSignature'],
                'next226OutsideWindowSignature' => $stat4Fence['outsideWindowSignature'],
                'next226WindowReady' => $windowReady,
            ]),
            'cursorProgram' => self::cursorProgram(is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [], $windowReady, $stat4Fence),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT226 SAMPLE WINDOW '
                . ($selectedName !== '' ? $selectedName : 'NO INDEX')
                . ($windowReady ? ' CURRENT STAT4 RANGE WINDOW PROVED' : ' REQUIRES STAT4 WINDOW REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext219Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next226',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next226 reuses current-source STAT4 expression partial row streams and adds a bounded sample-window proof for partial expression-index range scans',
            'non_overlap' => 'avoids accepted next219 peer-run boundary, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only proves the STAT4 sample rows inside a partial expression-index range window match the current cursor rowids while outside-window samples may churn',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next226 source needs index list');
        }
        foreach ($indexes as $index) {
            if (is_array($index) && (string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next226 selected expression index is missing from source');
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array{lower:?string,upper:?string}
     */
    private static function expressionBounds(array $terms, string $expression): array
    {
        $normal = self::normalExpression($expression);
        $lower = null;
        $upper = null;
        foreach ($terms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || self::normalExpression((string) ($left['expression'] ?? '')) !== $normal) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === 'BETWEEN') {
                $lower = self::stringOrNull($term['lower'] ?? null);
                $upper = self::stringOrNull($term['upper'] ?? null);
            } elseif ($operator === '>=' || $operator === '>') {
                $lower = self::stringOrNull($term['right'] ?? null);
            } elseif ($operator === '<=' || $operator === '<') {
                $upper = self::stringOrNull($term['right'] ?? null);
            }
        }

        return ['lower' => $lower, 'upper' => $upper];
    }

    /** @param array<string,mixed> $index @param array{lower:?string,upper:?string} $bounds @return list<array<string,mixed>> */
    private static function stat4Window(array $index, array $bounds): array
    {
        return array_values(array_filter(self::stat4Samples($index), static fn (array $sample): bool => self::sampleInWindow($sample, $bounds)));
    }

    /** @param array<string,mixed> $index @param array{lower:?string,upper:?string} $bounds @return list<array<string,mixed>> */
    private static function stat4OutsideWindow(array $index, array $bounds): array
    {
        return array_values(array_filter(self::stat4Samples($index), static fn (array $sample): bool => !self::sampleInWindow($sample, $bounds)));
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next226 expression index needs STAT4 samples');
        }
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next226 STAT4 samples must be arrays');
            }
        }

        return $samples;
    }

    /** @param array<string,mixed> $sample @param array{lower:?string,upper:?string} $bounds */
    private static function sampleInWindow(array $sample, array $bounds): bool
    {
        $key = self::sampleKey($sample);
        return ($bounds['lower'] === null || $key >= $bounds['lower'])
            && ($bounds['upper'] === null || $key <= $bounds['upper']);
    }

    /** @param array<string,mixed> $sample */
    private static function sampleKey(array $sample): string
    {
        $values = $sample['sample'] ?? null;
        if (!is_array($values) || !array_key_exists(0, $values)) {
            throw new \InvalidArgumentException('SQLite next226 STAT4 sample needs expression key');
        }

        return strtolower((string) $values[0]);
    }

    /** @param list<array<string,mixed>> $samples @return list<int> */
    private static function rowidsFromSamples(array $samples): array
    {
        $rowids = [];
        foreach ($samples as $sample) {
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next226 STAT4 sample needs rowid');
            }
            $rowids[] = (int) $values[1];
        }

        return $rowids;
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next226 needs matched row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next226 matched rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function rowids(array $rows): array
    {
        return array_map(static function (array $row): int {
            if (!array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next226 matched row needs rowid');
            }

            return (int) $row['rowid'];
        }, $rows);
    }

    /** @param list<array<string,mixed>> $rows @return list<int> */
    private static function sortedRowids(array $rows): array
    {
        $rowids = self::rowids($rows);
        sort($rowids, SORT_NUMERIC);

        return $rowids;
    }

    /** @param list<array<string,mixed>> $samples @return list<int> */
    private static function sortedRowidsFromSamples(array $samples): array
    {
        $rowids = self::rowidsFromSamples($samples);
        sort($rowids, SORT_NUMERIC);

        return $rowids;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $fence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCurrentSourceStat4SampleWindow',
            'mode' => 'next226-current-source-stat4-expression-partial-sample-window',
            'lowerBound' => $fence['lowerBound'],
            'upperBound' => $fence['upperBound'],
            'windowRowids' => $fence['currentWindowRowids'],
            'signature' => $fence['windowSignature'],
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
