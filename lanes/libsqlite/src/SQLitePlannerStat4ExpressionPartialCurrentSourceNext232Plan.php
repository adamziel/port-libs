<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext232Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::counterFence(
            self::rowsByRowid($currentSource),
            self::partialPredicateTerms($currentIndex),
            self::stat4Samples($currentIndex),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next228-ready'
            && $fence['allCurrentStat4CountersMatchPartialRows'] === true
            && $fence['counterMismatchRowids'] === []
            && $fence['partialRowCount'] >= count($base['matchedRowids'] ?? []);

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next232-ready' : 'requires-current-source-stat4-counter-reprepare',
            'stat4CounterFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next232Ready' => $ready,
                'next232CounterSignature' => $fence['counterSignature'],
                'next232ProofSignature' => $fence['proofSignature'],
                'next232CounterMismatchRowids' => $fence['counterMismatchRowids'],
                'next232PartialRowCount' => $fence['partialRowCount'],
                'next232DistinctExpressionKeyCount' => $fence['distinctExpressionKeyCount'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next232CounterSignature' => $fence['counterSignature'],
                'next232ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT232 COUNTER FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 COUNTERS VERIFIED' : ' REQUIRES CURRENT STAT4 COUNTER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next232',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next232 reuses current-source STAT4 expression partial sample-row fences and adds neq/nlt/ndlt counter validation from current partial rows',
            'non_overlap' => 'avoids accepted next228 sample-row partial-predicate validation, next224 sample-order validation, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only verifies current sqlite_stat4 counter cardinalities for partial expression-index samples before cursor reuse',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next232 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next232 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next232 selected index missing from source');
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next232 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next232 current source rows must be arrays');
            }
            $out[self::rowid($row, 'current rowid')] = $row;
        }

        return $out;
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function partialPredicateTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next232 needs partialPredicateTerms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next232 partial predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{expressionKey:string,rowid:int,neq:int,nlt:int,ndlt:int}>
     */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next232 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next232 stat4 samples are malformed');
            }
            $out[] = [
                'expressionKey' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]], 'stat4 sample rowid'),
                'neq' => self::firstStatInt($sample['neq'] ?? null, 'neq'),
                'nlt' => self::firstStatInt($sample['nlt'] ?? null, 'nlt'),
                'ndlt' => self::firstStatInt($sample['ndlt'] ?? null, 'ndlt'),
            ];
        }

        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array{expressionKey:string,rowid:int,neq:int,nlt:int,ndlt:int}> $samples
     * @return array<string,mixed>
     */
    private static function counterFence(array $rowsByRowid, array $partialTerms, array $samples): array
    {
        $partialRows = array_values(array_filter(
            $rowsByRowid,
            static fn (array $row): bool => self::rowSatisfiesPartialPredicate($row, $partialTerms),
        ));
        $keys = array_map(static fn (array $row): string => self::expressionKeyForRow($row), $partialRows);
        sort($keys, SORT_STRING);
        $distinctKeys = array_values(array_unique($keys));
        $proofs = [];
        $mismatches = [];

        foreach ($samples as $sample) {
            $expected = [
                'neq' => count(array_filter($keys, static fn (string $key): bool => $key === $sample['expressionKey'])),
                'nlt' => count(array_filter($keys, static fn (string $key): bool => $key < $sample['expressionKey'])),
                'ndlt' => count(array_filter($distinctKeys, static fn (string $key): bool => $key < $sample['expressionKey'])),
            ];
            $matches = $sample['neq'] === $expected['neq']
                && $sample['nlt'] === $expected['nlt']
                && $sample['ndlt'] === $expected['ndlt'];
            if (!$matches) {
                $mismatches[] = $sample['rowid'];
            }
            $proofs[] = [
                'sampleRowid' => $sample['rowid'],
                'expressionKey' => $sample['expressionKey'],
                'stat4Neq' => $sample['neq'],
                'stat4Nlt' => $sample['nlt'],
                'stat4Ndlt' => $sample['ndlt'],
                'currentNeq' => $expected['neq'],
                'currentNlt' => $expected['nlt'],
                'currentNdlt' => $expected['ndlt'],
                'counterMatchesCurrentPartialRows' => $matches,
            ];
        }

        return [
            'partialRowCount' => count($partialRows),
            'distinctExpressionKeyCount' => count($distinctKeys),
            'partialExpressionKeys' => $keys,
            'distinctExpressionKeys' => $distinctKeys,
            'sampleCounterProofs' => $proofs,
            'counterMismatchRowids' => array_values(array_unique($mismatches)),
            'allCurrentStat4CountersMatchPartialRows' => $mismatches === [],
            'counterSignature' => self::signature($samples),
            'proofSignature' => self::signature([$partialTerms, $keys, $distinctKeys, $proofs, $mismatches]),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $partialTerms
     */
    private static function rowSatisfiesPartialPredicate(array $row, array $partialTerms): bool
    {
        foreach ($partialTerms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if (!self::termSatisfied($operator, self::leftValue($term['left'] ?? null, $row), $term)) {
                return false;
            }
        }

        return true;
    }

    private static function termSatisfied(string $operator, mixed $value, array $term): bool
    {
        return match ($operator) {
            '=' => self::compare($value, $term['right'] ?? null) === 0,
            '>=', '=>' => self::compare($value, $term['right'] ?? null) >= 0,
            '<=' => self::compare($value, $term['right'] ?? null) <= 0,
            '>' => self::compare($value, $term['right'] ?? null) > 0,
            '<' => self::compare($value, $term['right'] ?? null) < 0,
            'IS NOT NULL' => $value !== null,
            'LIKE' => self::likePrefix((string) $value, (string) ($term['right'] ?? '')),
            'BETWEEN' => self::compare($value, $term['lower'] ?? null) >= 0
                && self::compare($value, $term['upper'] ?? null) <= 0,
            default => throw new \InvalidArgumentException('SQLite next232 unsupported partial predicate operator ' . $operator),
        };
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return ((float) $left) <=> ((float) $right);
        }

        return strcmp(strtolower((string) $left), strtolower((string) $right));
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        if (!str_ends_with($pattern, '%') || str_contains(substr($pattern, 0, -1), '%') || str_contains($pattern, '_')) {
            throw new \InvalidArgumentException('SQLite next232 only supports simple LIKE prefix partial terms');
        }

        return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next232 partial predicate term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower((string) ($left['expression'] ?? ''));
        if ($expression === 'lower(option_name)') {
            return self::expressionKeyForRow($row);
        }

        throw new \InvalidArgumentException('SQLite next232 partial predicate expression is unsupported');
    }

    /** @param array<string,mixed> $row */
    private static function expressionKeyForRow(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
    }

    private static function firstStatInt(mixed $value, string $label): int
    {
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('SQLite next232 stat4 ' . $label . ' must be a string or integer');
        }
        $parts = preg_split('/\s+/', trim((string) $value));
        if ($parts === false || $parts === [] || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next232 stat4 ' . $label . ' is malformed');
        }

        return (int) $parts[0];
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row, string $label): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next232 ' . $label . ' must be an integer');
        }

        return (int) $row['rowid'];
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
            'opcode' => 'VerifyCurrentStat4PartialCounters',
            'mode' => 'next232-current-source-stat4-expression-partial-counters',
            'partialRowCount' => $fence['partialRowCount'],
            'distinctExpressionKeyCount' => $fence['distinctExpressionKeyCount'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
