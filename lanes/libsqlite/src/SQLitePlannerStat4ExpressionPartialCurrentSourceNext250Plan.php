<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext250Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext247Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $index = self::indexByName($currentSource, (string) ($base['selectedPlan']['name'] ?? ''));
        $fence = self::partialPredicateFence(
            self::rowsByRowid($currentSource),
            $index,
            self::rowids($base['matchedRowids'] ?? null),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next247-ready'
            && $fence['allYieldedRowsSatisfyCurrentPartialPredicate'] === true
            && $fence['predicateMismatchRowids'] === []
            && $fence['missingCurrentRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next250-ready' : 'requires-current-source-partial-predicate-reprepare',
            'stat4CurrentPartialPredicateFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next250Ready' => $ready,
                'next250PartialPredicateTermCount' => $fence['partialPredicateTermCount'],
                'next250PredicateMatchedRowids' => $fence['predicateMatchedRowids'],
                'next250PredicateMismatchRowids' => $fence['predicateMismatchRowids'],
                'next250ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next250CurrentPartialPredicateReady' => $ready,
                'next250CurrentPartialPredicateSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT250 PARTIAL PREDICATE FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT PARTIAL PREDICATE VERIFIED' : ' REQUIRES CURRENT PARTIAL PREDICATE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext247Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next250',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next250 reuses current-source STAT4 expression partial boundary-peer validation and adds current partial-index predicate proof for yielded rowids',
            'non_overlap' => 'adds current partial-index predicate rowid fencing after accepted next247 boundary peers; avoids next246 duplicate cardinality, next247 boundary peers, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, PRAGMA, and UTF clusters',
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param array<string,mixed> $index
     * @param list<int> $yieldedRowids
     * @return array<string,mixed>
     */
    private static function partialPredicateFence(array $rowsByRowid, array $index, array $yieldedRowids): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next250 needs current partial predicate terms');
        }

        $matched = [];
        $mismatches = [];
        $missing = [];
        $proofs = [];
        foreach ($yieldedRowids as $rowid) {
            $row = $rowsByRowid[$rowid] ?? null;
            if ($row === null) {
                $missing[] = $rowid;
                continue;
            }
            $termResults = [];
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next250 partial predicate terms must be arrays');
                }
                $termResults[] = self::termProof($row, $term);
            }
            $satisfies = !in_array(false, array_column($termResults, 'satisfied'), true);
            if ($satisfies) {
                $matched[] = $rowid;
            } else {
                $mismatches[] = $rowid;
            }
            $proofs[] = [
                'rowid' => $rowid,
                'expressionKey' => self::expressionKey($row),
                'satisfiesCurrentPartialPredicate' => $satisfies,
                'termResults' => $termResults,
            ];
        }

        $proof = [
            'partialPredicateTermCount' => count($terms),
            'yieldedRowids' => $yieldedRowids,
            'predicateMatchedRowids' => $matched,
            'predicateMismatchRowids' => $mismatches,
            'missingCurrentRowids' => $missing,
            'rowProofs' => $proofs,
        ];

        return $proof + [
            'allYieldedRowsSatisfyCurrentPartialPredicate' => $yieldedRowids !== [] && $mismatches === [] && $missing === [],
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $term @return array<string,mixed> */
    private static function termProof(array $row, array $term): array
    {
        $operator = strtoupper((string) ($term['operator'] ?? ''));
        $left = self::leftValue($term['left'] ?? null, $row);
        $right = $term['right'] ?? null;
        $satisfied = match ($operator) {
            '=' => self::compare($left, $right) === 0,
            '!=' , '<>' => self::compare($left, $right) !== 0,
            '>=' => self::compare($left, $right) >= 0,
            '>' => self::compare($left, $right) > 0,
            '<=' => self::compare($left, $right) <= 0,
            '<' => self::compare($left, $right) < 0,
            'IS NOT NULL' => $left !== null,
            'LIKE' => self::like((string) $left, (string) $right),
            'BETWEEN' => self::compare($left, $term['lower'] ?? null) >= 0 && self::compare($left, $term['upper'] ?? null) <= 0,
            default => throw new \InvalidArgumentException('SQLite next250 unsupported partial predicate operator ' . $operator),
        };

        return [
            'left' => self::termLabel($term['left'] ?? null),
            'operator' => $operator,
            'right' => $operator === 'BETWEEN' ? [$term['lower'] ?? null, $term['upper'] ?? null] : $right,
            'leftValue' => $left,
            'satisfied' => $satisfied,
        ];
    }

    /** @param mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next250 term left operand must be an array');
        }
        if (isset($left['column'])) {
            return $row[(string) $left['column']] ?? null;
        }
        if (isset($left['expression'])) {
            $expression = strtolower(str_replace(' ', '', (string) $left['expression']));
            if ($expression === 'lower(option_name)') {
                return strtolower((string) ($row['option_name'] ?? ''));
            }
        }

        throw new \InvalidArgumentException('SQLite next250 unsupported left operand');
    }

    private static function termLabel(mixed $left): string
    {
        if (!is_array($left)) {
            return 'unknown';
        }
        if (isset($left['column'])) {
            return (string) $left['column'];
        }
        if (isset($left['expression'])) {
            return (string) $left['expression'];
        }

        return 'unknown';
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next250 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next250 current source rows must be arrays');
            }
            $rowid = self::intValue($row['rowid'] ?? null, 'current rowid');
            if (isset($out[$rowid])) {
                throw new \InvalidArgumentException('SQLite next250 duplicate current rowid');
            }
            $out[$rowid] = $row;
        }

        return $out;
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next250 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next250 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next250 selected index missing from source');
    }

    /** @param mixed $value @return list<int> */
    private static function rowids(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next250 needs yielded rowids');
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValue($rowid, 'yielded rowid'), $value));
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp(strtolower((string) $left), strtolower((string) $right));
    }

    private static function like(string $value, string $pattern): bool
    {
        $quoted = preg_quote($pattern, '/');
        $regex = '/^' . str_replace(['%', '_'], ['.*', '.'], $quoted) . '$/iu';

        return preg_match($regex, $value) === 1;
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next250 ' . $label . ' must be an integer');
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
            'opcode' => 'VerifyCurrentPartialPredicate',
            'mode' => 'next250-current-source-stat4-expression-partial-predicate',
            'predicateMatchedRowids' => $fence['predicateMatchedRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
