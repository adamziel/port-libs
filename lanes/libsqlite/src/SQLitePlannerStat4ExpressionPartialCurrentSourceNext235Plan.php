<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext235Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext232Plan::materialize(
            self::sourceForBaseCounterFence($preparedSource),
            self::sourceForBaseCounterFence($currentSource),
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::vectorCounterFence(
            self::rows($currentSource),
            self::partialPredicateTerms($currentIndex),
            self::stat4Samples($currentIndex),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next232-ready'
            && $fence['allVectorCountersMatchCurrentPartialRows'] === true
            && $fence['vectorCounterMismatchRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next235-ready' : 'requires-current-source-stat4-vector-counter-reprepare',
            'stat4VectorCounterFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next235Ready' => $ready,
                'next235VectorSignature' => $fence['vectorSignature'],
                'next235ProofSignature' => $fence['proofSignature'],
                'next235VectorMismatchRowids' => $fence['vectorCounterMismatchRowids'],
                'next235VectorKeyCount' => $fence['distinctVectorKeyCount'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next235VectorSignature' => $fence['vectorSignature'],
                'next235ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT235 VECTOR COUNTER FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 VECTOR COUNTERS VERIFIED' : ' REQUIRES CURRENT STAT4 VECTOR COUNTER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext232Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next235',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next235 reuses current-source STAT4 partial expression counter fences and adds multi-prefix STAT4 vector validation for expression-plus-column samples',
            'non_overlap' => 'avoids accepted next232 first-prefix counter cardinalities, next231 page membership, next228 sample partial proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only verifies multi-prefix STAT4 vector counters for partial expression indexes before current-source cursor reuse',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function sourceForBaseCounterFence(array $source): array
    {
        $copy = $source;
        if (!isset($copy['indexes']) || !is_array($copy['indexes']) || !array_is_list($copy['indexes'])) {
            throw new \InvalidArgumentException('SQLite next235 needs source indexes');
        }
        foreach ($copy['indexes'] as &$index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next235 index entries must be arrays');
            }
            if (!isset($index['stat4Samples']) || !is_array($index['stat4Samples']) || !array_is_list($index['stat4Samples'])) {
                continue;
            }
            foreach ($index['stat4Samples'] as &$sample) {
                if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 3) {
                    continue;
                }
                $sample['sample'] = [$sample['sample'][0], $sample['sample'][2]];
            }
            unset($sample);
        }
        unset($index);

        return $copy;
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next235 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next235 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next235 selected index missing from source');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next235 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next235 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function partialPredicateTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next235 needs partialPredicateTerms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next235 partial predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{expressionKey:string,blogId:int,rowid:int,neq:list<int>,nlt:list<int>,ndlt:list<int>}>
     */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next235 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 3) {
                throw new \InvalidArgumentException('SQLite next235 stat4 samples need expression, blog_id, and rowid');
            }
            $out[] = [
                'expressionKey' => strtolower((string) $sample['sample'][0]),
                'blogId' => self::intValue($sample['sample'][1], 'sample blog_id'),
                'rowid' => self::intValue($sample['sample'][2], 'sample rowid'),
                'neq' => self::statVector($sample['neq'] ?? null, 'neq'),
                'nlt' => self::statVector($sample['nlt'] ?? null, 'nlt'),
                'ndlt' => self::statVector($sample['ndlt'] ?? null, 'ndlt'),
            ];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array{expressionKey:string,blogId:int,rowid:int,neq:list<int>,nlt:list<int>,ndlt:list<int>}> $samples
     * @return array<string,mixed>
     */
    private static function vectorCounterFence(array $rows, array $partialTerms, array $samples): array
    {
        $partialRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => self::rowSatisfiesPartialPredicate($row, $partialTerms),
        ));
        $keys = array_map(static fn (array $row): array => self::vectorKey($row), $partialRows);
        usort($keys, static fn (array $left, array $right): int => self::compareVector($left, $right));
        $distinctVectors = self::distinctVectors($keys);
        $distinctExpressions = array_values(array_unique(array_map(static fn (array $key): string => $key[0], $keys)));
        sort($distinctExpressions, SORT_STRING);

        $proofs = [];
        $mismatches = [];
        foreach ($samples as $sample) {
            $sampleVector = [$sample['expressionKey'], $sample['blogId']];
            $expected = [
                'neq' => [
                    count(array_filter($keys, static fn (array $key): bool => $key[0] === $sample['expressionKey'])),
                    count(array_filter($keys, static fn (array $key): bool => $key === $sampleVector)),
                ],
                'nlt' => [
                    count(array_filter($keys, static fn (array $key): bool => strcmp($key[0], $sample['expressionKey']) < 0)),
                    count(array_filter($keys, static fn (array $key): bool => self::compareVector($key, $sampleVector) < 0)),
                ],
                'ndlt' => [
                    count(array_filter($distinctExpressions, static fn (string $key): bool => strcmp($key, $sample['expressionKey']) < 0)),
                    count(array_filter($distinctVectors, static fn (array $key): bool => self::compareVector($key, $sampleVector) < 0)),
                ],
            ];
            $matches = $sample['neq'] === $expected['neq']
                && $sample['nlt'] === $expected['nlt']
                && $sample['ndlt'] === $expected['ndlt'];
            if (!$matches) {
                $mismatches[] = $sample['rowid'];
            }
            $proofs[] = [
                'sampleRowid' => $sample['rowid'],
                'sampleVector' => $sampleVector,
                'stat4NeqVector' => $sample['neq'],
                'stat4NltVector' => $sample['nlt'],
                'stat4NdltVector' => $sample['ndlt'],
                'currentNeqVector' => $expected['neq'],
                'currentNltVector' => $expected['nlt'],
                'currentNdltVector' => $expected['ndlt'],
                'vectorCountersMatchCurrentPartialRows' => $matches,
            ];
        }

        return [
            'partialRowCount' => count($partialRows),
            'expressionKeyCount' => count($distinctExpressions),
            'distinctVectorKeyCount' => count($distinctVectors),
            'partialVectorKeys' => $keys,
            'distinctVectorKeys' => $distinctVectors,
            'sampleVectorProofs' => $proofs,
            'vectorCounterMismatchRowids' => array_values(array_unique($mismatches)),
            'allVectorCountersMatchCurrentPartialRows' => $mismatches === [],
            'vectorSignature' => self::signature($samples),
            'proofSignature' => self::signature([$partialTerms, $keys, $distinctVectors, $proofs, $mismatches]),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{0:string,1:int}
     */
    private static function vectorKey(array $row): array
    {
        return [strtolower((string) ($row['option_name'] ?? '')), self::intValue($row['blog_id'] ?? null, 'row blog_id')];
    }

    /**
     * @param list<array{0:string,1:int}> $keys
     * @return list<array{0:string,1:int}>
     */
    private static function distinctVectors(array $keys): array
    {
        $seen = [];
        $out = [];
        foreach ($keys as $key) {
            $signature = $key[0] . "\0" . $key[1];
            if (isset($seen[$signature])) {
                continue;
            }
            $seen[$signature] = true;
            $out[] = $key;
        }

        return $out;
    }

    /** @param array{0:string,1:int} $left @param array{0:string,1:int} $right */
    private static function compareVector(array $left, array $right): int
    {
        $cmp = strcmp($left[0], $right[0]);
        if ($cmp !== 0) {
            return $cmp;
        }

        return $left[1] <=> $right[1];
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
            default => throw new \InvalidArgumentException('SQLite next235 unsupported partial predicate operator ' . $operator),
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
            throw new \InvalidArgumentException('SQLite next235 only supports simple LIKE prefix partial terms');
        }

        return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next235 partial predicate term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower((string) ($left['expression'] ?? ''));
        if ($expression === 'lower(option_name)') {
            return strtolower((string) ($row['option_name'] ?? ''));
        }

        throw new \InvalidArgumentException('SQLite next235 partial predicate expression is unsupported');
    }

    /** @return list<int> */
    private static function statVector(mixed $value, string $label): array
    {
        if (!is_string($value) && !is_int($value)) {
            throw new \InvalidArgumentException('SQLite next235 stat4 ' . $label . ' must be a string or integer');
        }
        $parts = preg_split('/\s+/', trim((string) $value));
        if ($parts === false || count($parts) < 2) {
            throw new \InvalidArgumentException('SQLite next235 stat4 ' . $label . ' needs at least two counters');
        }
        $out = [];
        foreach (array_slice($parts, 0, 2) as $part) {
            if (!ctype_digit($part)) {
                throw new \InvalidArgumentException('SQLite next235 stat4 ' . $label . ' is malformed');
            }
            $out[] = (int) $part;
        }

        return $out;
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next235 ' . $label . ' must be an integer');
        }

        return (int) $value;
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
            'opcode' => 'VerifyCurrentStat4VectorCounters',
            'mode' => 'next235-current-source-stat4-expression-partial-vector-counters',
            'partialRowCount' => $fence['partialRowCount'],
            'distinctVectorKeyCount' => $fence['distinctVectorKeyCount'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
