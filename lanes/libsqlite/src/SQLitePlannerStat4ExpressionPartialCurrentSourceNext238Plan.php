<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext238Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext235Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $fence = self::coveringPayloadFence(
            self::rows($currentSource),
            self::partialPredicateTerms($currentIndex),
            self::coveringColumns($currentIndex),
            self::payloads($currentIndex),
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next235-ready'
            && $fence['allPayloadsMatchCurrentRows'] === true
            && $fence['payloadMismatchRowids'] === []
            && $fence['missingPayloadRowids'] === []
            && $fence['stalePayloadRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next238-ready' : 'requires-current-source-stat4-covering-payload-reprepare',
            'stat4CoveringPayloadFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next238Ready' => $ready,
                'next238PayloadSignature' => $fence['payloadSignature'],
                'next238ProofSignature' => $fence['proofSignature'],
                'next238CoveredRowCount' => $fence['coveredRowCount'],
                'next238PayloadMismatchRowids' => $fence['payloadMismatchRowids'],
                'next238MissingPayloadRowids' => $fence['missingPayloadRowids'],
                'next238StalePayloadRowids' => $fence['stalePayloadRowids'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next238PayloadSignature' => $fence['payloadSignature'],
                'next238ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT238 COVERING PAYLOAD FENCE '
                . $selectedName
                . ($ready ? ' CURRENT COVERING PAYLOADS VERIFIED' : ' REQUIRES CURRENT COVERING PAYLOAD REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext235Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next238',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next238 reuses current-source STAT4 expression partial vector fences and adds covering-payload validation over current partial rows',
            'non_overlap' => 'extends accepted next235 vector counters by checking current-row covering payload staleness for partial expression indexes; avoids next235 vector counters, next231 page membership, next228 sample partial proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next238 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next238 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next238 selected index missing from source');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next238 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next238 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function partialPredicateTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite next238 needs partialPredicateTerms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next238 partial predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /** @param array<string,mixed> $index @return list<string> */
    private static function coveringColumns(array $index): array
    {
        $columns = $index['coveringColumns'] ?? null;
        if (!is_array($columns) || !array_is_list($columns) || $columns === []) {
            throw new \InvalidArgumentException('SQLite next238 needs coveringColumns');
        }
        $out = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next238 covering columns must be names');
            }
            $out[] = $column;
        }

        return $out;
    }

    /** @param array<string,mixed> $index @return list<array<string,mixed>> */
    private static function payloads(array $index): array
    {
        $payloads = $index['stat4ExpressionPayloads'] ?? null;
        if (!is_array($payloads) || !array_is_list($payloads)) {
            throw new \InvalidArgumentException('SQLite next238 needs stat4ExpressionPayloads');
        }
        foreach ($payloads as $payload) {
            if (!is_array($payload) || !is_array($payload['coveredValues'] ?? null)) {
                throw new \InvalidArgumentException('SQLite next238 payloads need coveredValues');
            }
        }

        return $payloads;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $partialTerms
     * @param list<string> $coveringColumns
     * @param list<array<string,mixed>> $payloads
     * @return array<string,mixed>
     */
    private static function coveringPayloadFence(array $rows, array $partialTerms, array $coveringColumns, array $payloads): array
    {
        $partialRows = array_values(array_filter(
            $rows,
            static fn (array $row): bool => self::rowSatisfiesPartialPredicate($row, $partialTerms),
        ));
        usort($partialRows, static fn (array $left, array $right): int => self::comparePayloadKey(self::payloadKeyFromRow($left), self::payloadKeyFromRow($right)));
        $payloadsByRowid = [];
        foreach ($payloads as $payload) {
            $rowid = self::intValue($payload['rowid'] ?? null, 'payload rowid');
            if (isset($payloadsByRowid[$rowid])) {
                throw new \InvalidArgumentException('SQLite next238 duplicate payload rowid');
            }
            $payloadsByRowid[$rowid] = $payload;
        }

        $proofs = [];
        $matched = [];
        $mismatches = [];
        $missing = [];
        foreach ($partialRows as $row) {
            $rowid = self::intValue($row['rowid'] ?? null, 'rowid');
            $payload = $payloadsByRowid[$rowid] ?? null;
            if ($payload === null) {
                $missing[] = $rowid;
                $proofs[] = [
                    'rowid' => $rowid,
                    'payloadPresent' => false,
                    'payloadMatchesCurrentRow' => false,
                    'mismatchedColumns' => $coveringColumns,
                ];
                continue;
            }
            $matched[$rowid] = true;
            $mismatchedColumns = self::mismatchedColumns($row, $payload, $coveringColumns);
            if ($mismatchedColumns !== []) {
                $mismatches[] = $rowid;
            }
            $proofs[] = [
                'rowid' => $rowid,
                'payloadPresent' => true,
                'payloadExpressionKey' => (string) ($payload['expressionKey'] ?? ''),
                'currentExpressionKey' => strtolower((string) ($row['option_name'] ?? '')),
                'payloadMatchesCurrentRow' => $mismatchedColumns === [],
                'mismatchedColumns' => $mismatchedColumns,
                'coveredValues' => self::coveredValues($row, $coveringColumns),
            ];
        }

        $stale = [];
        foreach ($payloadsByRowid as $rowid => $payload) {
            if (!isset($matched[$rowid])) {
                $stale[] = $rowid;
            }
        }
        sort($stale, SORT_NUMERIC);

        return [
            'coveredRowCount' => count($partialRows),
            'payloadRowCount' => count($payloads),
            'coveringColumns' => $coveringColumns,
            'payloadProofs' => $proofs,
            'payloadMismatchRowids' => array_values(array_unique($mismatches)),
            'missingPayloadRowids' => array_values(array_unique($missing)),
            'stalePayloadRowids' => $stale,
            'allPayloadsMatchCurrentRows' => $mismatches === [] && $missing === [] && $stale === [],
            'payloadSignature' => self::signature($payloads),
            'proofSignature' => self::signature([$coveringColumns, $proofs, $mismatches, $missing, $stale]),
        ];
    }

    /** @param array<string,mixed> $row @param array<string,mixed> $payload @param list<string> $coveringColumns @return list<string> */
    private static function mismatchedColumns(array $row, array $payload, array $coveringColumns): array
    {
        $mismatches = [];
        $covered = $payload['coveredValues'] ?? null;
        if (!is_array($covered)) {
            throw new \InvalidArgumentException('SQLite next238 payload coveredValues must be an array');
        }
        if (strtolower((string) ($payload['expressionKey'] ?? '')) !== strtolower((string) ($row['option_name'] ?? ''))) {
            $mismatches[] = 'expressionKey';
        }
        foreach ($coveringColumns as $column) {
            if (!array_key_exists($column, $covered) || ($covered[$column] !== ($row[$column] ?? null))) {
                $mismatches[] = $column;
            }
        }

        return $mismatches;
    }

    /** @param array<string,mixed> $row @param list<string> $coveringColumns @return array<string,mixed> */
    private static function coveredValues(array $row, array $coveringColumns): array
    {
        $out = [];
        foreach ($coveringColumns as $column) {
            $out[$column] = $row[$column] ?? null;
        }

        return $out;
    }

    /** @param array<string,mixed> $row @return array{0:string,1:int,2:int} */
    private static function payloadKeyFromRow(array $row): array
    {
        return [
            strtolower((string) ($row['option_name'] ?? '')),
            self::intValue($row['blog_id'] ?? null, 'row blog_id'),
            self::intValue($row['rowid'] ?? null, 'rowid'),
        ];
    }

    /** @param array{0:string,1:int,2:int} $left @param array{0:string,1:int,2:int} $right */
    private static function comparePayloadKey(array $left, array $right): int
    {
        $cmp = strcmp($left[0], $right[0]);
        if ($cmp !== 0) {
            return $cmp;
        }
        $cmp = $left[1] <=> $right[1];
        if ($cmp !== 0) {
            return $cmp;
        }

        return $left[2] <=> $right[2];
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
            default => throw new \InvalidArgumentException('SQLite next238 unsupported partial predicate operator ' . $operator),
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
            throw new \InvalidArgumentException('SQLite next238 only supports simple LIKE prefix partial terms');
        }

        return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next238 partial predicate term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower((string) ($left['expression'] ?? ''));
        if ($expression === 'lower(option_name)') {
            return strtolower((string) ($row['option_name'] ?? ''));
        }

        throw new \InvalidArgumentException('SQLite next238 partial predicate expression is unsupported');
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next238 ' . $label . ' must be an integer');
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
            'opcode' => 'VerifyCurrentStat4CoveringPayloads',
            'mode' => 'next238-current-source-stat4-expression-partial-covering-payloads',
            'coveredRowCount' => $fence['coveredRowCount'],
            'payloadRowCount' => $fence['payloadRowCount'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
