<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext234Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext231Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $fence = self::histogramFence(
            self::currentQualifiedKeys($currentSource, $whereTerms),
            self::stat4Samples(self::indexByName($currentSource, $selectedName)),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next231-ready'
            && $fence['allHistogramRowsMatchCurrentSource'] === true
            && $fence['mismatchedSampleRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next234-ready' : 'requires-current-source-stat4-histogram-reprepare',
            'stat4HistogramFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next234Ready' => $ready,
                'next234MismatchedSampleRowids' => $fence['mismatchedSampleRowids'],
                'next234HistogramSignature' => $fence['histogramSignature'],
                'next234ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next234HistogramSignature' => $fence['histogramSignature'],
                'next234ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT234 HISTOGRAM FENCE '
                . $selectedName
                . ($ready ? ' CURRENT HISTOGRAM PROVED' : ' REQUIRES CURRENT SOURCE HISTOGRAM REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext231Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next234',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next234 reuses current-source STAT4 expression partial page membership and adds histogram cardinality validation',
            'non_overlap' => 'adds current STAT4 neq/nlt/ndlt histogram cardinality validation after accepted next231 page membership validation; avoids next231 page proof, next228 sample partial proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $whereTerms
     * @return array<string,string>
     */
    private static function currentQualifiedKeys(array $source, array $whereTerms): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next234 needs current source rows');
        }
        $keys = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next234 current rows must be arrays');
            }
            if (self::rowSatisfiesWhereTerms($row, $whereTerms)) {
                $keys[(string) self::intValue($row, 'rowid')] = self::expressionKey($row);
            }
        }
        ksort($keys, SORT_NUMERIC);

        return $keys;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $whereTerms
     */
    private static function rowSatisfiesWhereTerms(array $row, array $whereTerms): bool
    {
        foreach ($whereTerms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next234 where terms must be arrays');
            }
            $left = $term['left'] ?? null;
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = self::leftValue($left, $row);
            $ok = match ($operator) {
                '=' => self::compare($value, $term['right'] ?? null) === 0,
                '>=', '=>' => self::compare($value, $term['right'] ?? null) >= 0,
                '<=' => self::compare($value, $term['right'] ?? null) <= 0,
                '>' => self::compare($value, $term['right'] ?? null) > 0,
                '<' => self::compare($value, $term['right'] ?? null) < 0,
                'IS NOT NULL' => $value !== null,
                'LIKE' => self::likePrefix((string) $value, (string) ($term['right'] ?? '')),
                'BETWEEN' => self::compare($value, $term['lower'] ?? null) >= 0
                    && self::compare($value, $term['upper'] ?? null) <= 0,
                default => throw new \InvalidArgumentException('SQLite next234 unsupported where operator ' . $operator),
            };
            if (!$ok) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next234 where term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower(preg_replace('/\s+/', '', (string) ($left['expression'] ?? '')) ?? '');
        if ($expression === 'lower(option_name)') {
            return self::expressionKey($row);
        }

        throw new \InvalidArgumentException('SQLite next234 where expression is unsupported');
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
        $regex = '/\A' . strtr(preg_quote($pattern, '/'), ['%' => '.*', '_' => '.']) . '\z/i';

        return preg_match($regex, $value) === 1;
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next234 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next234 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next234 selected index missing from current source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{rowid:int,expressionKey:string,neq:int,nlt:int,ndlt:int}>
     */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next234 current expression index needs stat4Samples');
        }

        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next234 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next234 STAT4 sample needs expression key and rowid');
            }
            $out[] = [
                'rowid' => self::sampleRowid($values[1]),
                'expressionKey' => strtolower((string) $values[0]),
                'neq' => self::firstStat($sample, 'neq'),
                'nlt' => self::firstStat($sample, 'nlt'),
                'ndlt' => self::firstStat($sample, 'ndlt'),
            ];
        }

        return $out;
    }

    /**
     * @param array<string,string> $qualifiedByRowid
     * @param list<array{rowid:int,expressionKey:string,neq:int,nlt:int,ndlt:int}> $samples
     * @return array<string,mixed>
     */
    private static function histogramFence(array $qualifiedByRowid, array $samples): array
    {
        $keys = array_values(array_filter($qualifiedByRowid, static fn (string $key): bool => $key !== ''));
        sort($keys, SORT_STRING);
        $distinct = array_values(array_unique($keys));
        $proofs = [];
        $mismatched = [];

        foreach ($samples as $sample) {
            $key = $sample['expressionKey'];
            $expected = [
                'neq' => count(array_filter($keys, static fn (string $value): bool => $value === $key)),
                'nlt' => count(array_filter($keys, static fn (string $value): bool => $value < $key)),
                'ndlt' => count(array_filter($distinct, static fn (string $value): bool => $value < $key)),
            ];
            $actual = [
                'neq' => $sample['neq'],
                'nlt' => $sample['nlt'],
                'ndlt' => $sample['ndlt'],
            ];
            $matches = $expected === $actual;
            if (!$matches) {
                $mismatched[] = $sample['rowid'];
            }
            $proofs[] = [
                'sampleRowid' => $sample['rowid'],
                'sampleExpressionKey' => $key,
                'expected' => $expected,
                'actual' => $actual,
                'matchesCurrentHistogram' => $matches,
            ];
        }

        return [
            'qualifiedRowCount' => count($keys),
            'distinctExpressionKeyCount' => count($distinct),
            'qualifiedExpressionKeys' => $keys,
            'distinctExpressionKeys' => $distinct,
            'sampleHistogramProofs' => $proofs,
            'mismatchedSampleRowids' => array_values(array_unique($mismatched)),
            'allHistogramRowsMatchCurrentSource' => $mismatched === [],
            'histogramSignature' => self::signature([$keys, $distinct]),
            'proofSignature' => self::signature([$keys, $distinct, $proofs]),
        ];
    }

    /** @param array<string,mixed> $sample */
    private static function firstStat(array $sample, string $key): int
    {
        $raw = $sample[$key] ?? null;
        if (is_int($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            throw new \InvalidArgumentException('SQLite next234 STAT4 ' . $key . ' value must be present');
        }
        $parts = preg_split('/\s+/', trim($raw));
        if ($parts === false || $parts === [] || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next234 STAT4 ' . $key . ' first value must be an integer');
        }

        return (int) $parts[0];
    }

    private static function sampleRowid(mixed $rowid): int
    {
        if (!is_int($rowid) && !ctype_digit((string) $rowid)) {
            throw new \InvalidArgumentException('SQLite next234 STAT4 sample rowid must be an integer');
        }

        return (int) $rowid;
    }

    /** @param array<string,mixed> $row */
    private static function intValue(array $row, string $key): int
    {
        if (!array_key_exists($key, $row) || (!is_int($row[$key]) && !ctype_digit((string) $row[$key]))) {
            throw new \InvalidArgumentException('SQLite next234 expected integer ' . $key);
        }

        return (int) $row[$key];
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
            'opcode' => 'RecheckCurrentStat4ExpressionPartialHistogram',
            'mode' => 'next234-current-source-stat4-expression-partial-histogram',
            'qualifiedRowCount' => $fence['qualifiedRowCount'],
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
