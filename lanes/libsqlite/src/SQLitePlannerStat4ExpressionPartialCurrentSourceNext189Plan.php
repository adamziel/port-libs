<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext189Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext185Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $expression = self::normalizeExpression((string) ($currentIndex['expression'] ?? ''));
        if ($expression === '') {
            throw new \InvalidArgumentException('SQLite next189 needs selected expression index expression');
        }

        $rowsById = self::rowsById($currentSource);
        $sampleChecks = self::sampleChecks(self::sampleRows($currentIndex), $rowsById, self::predicateTerms($currentIndex), $expression);
        $sampleRejected = array_values(array_map(
            static fn (array $check): int => (int) $check['rowid'],
            array_filter($sampleChecks, static fn (array $check): bool => ($check['ready'] ?? false) !== true),
        ));
        $expectedKeys = self::stringList($base['matchedExpressionKeys'] ?? null, 'matchedExpressionKeys');
        $rowids = self::intList($base['matchedRowids'] ?? null, 'matchedRowids');
        $checks = [];
        $rejected = [];
        foreach ($rowids as $offsetKey => $rowid) {
            $row = $rowsById[$rowid] ?? null;
            if ($row === null) {
                $checks[] = [
                    'rowid' => $rowid,
                    'source' => 'missing-current-row',
                    'ready' => false,
                    'reasons' => ['missing-current-row'],
                ];
                $rejected[] = $rowid;
                continue;
            }

            $actualKey = self::evaluateExpression($expression, $row);
            $expectedKey = $expectedKeys[$offsetKey] ?? null;
            $reasons = [];
            if ($actualKey !== $expectedKey) {
                $reasons[] = 'expression-key-drift';
            }
            $predicate = self::predicateCheck(self::predicateTerms($currentIndex), $row);
            $reasons = array_values(array_merge($reasons, $predicate['reasons']));
            $ready = $reasons === [];
            if (!$ready) {
                $rejected[] = $rowid;
            }
            $checks[] = [
                'rowid' => $rowid,
                'source' => 'current',
                'expectedExpressionKey' => $expectedKey,
                'actualExpressionKey' => $actualKey,
                'partialPredicateReady' => $predicate['ready'],
                'ready' => $ready,
                'reasons' => $reasons,
                'payloadSignature' => self::signature($row),
            ];
        }

        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next185-ready'
            && $checks !== []
            && $rejected === []
            && $sampleRejected === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next189-ready' : 'requires-current-source-reprepare',
            'currentPayloadPartialFence' => [
                'ready' => $ready,
                'expression' => $expression,
                'rowids' => $rowids,
                'rejectedRowids' => $rejected,
                'sampleRejectedRowids' => $sampleRejected,
                'checks' => $checks,
                'sampleChecks' => $sampleChecks,
                'signature' => self::signature([$expression, $checks, $sampleChecks]),
            ],
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next189Ready' => $ready,
                'next189RejectedRowids' => array_values(array_unique(array_merge($rejected, $sampleRejected))),
                'next189PredicateCheckCount' => count($checks) + count($sampleChecks),
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next189PayloadPartialSignature' => self::signature([$expression, $checks, $sampleChecks]),
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $rowids,
                $rejected
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT189 PAYLOAD PARTIAL FENCE '
                . $selectedName
                . ($ready ? ' CURRENT PAYLOAD VERIFIED' : ' REQUIRES CURRENT SOURCE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext185Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next189',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next189 reuses current-source STAT4 expression partial provenance and adds payload-level expression/partial-predicate rechecks',
            'non_overlap' => 'avoids accepted next185 sample provenance, next186 IN windows, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, and trigger clusters; this slice only proves current payloads still satisfy the partial expression-index predicate before admitting the STAT4 window',
        ]);
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{key:mixed,rowid:int}>
     */
    private static function sampleRows(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next189 needs STAT4 sample list');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next189 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next189 STAT4 samples need expression key and rowid');
            }
            if (!is_int($values[1]) && !ctype_digit((string) $values[1])) {
                throw new \InvalidArgumentException('SQLite next189 STAT4 sample rowid must be an integer');
            }
            $out[] = ['key' => $values[0], 'rowid' => (int) $values[1]];
        }

        return $out;
    }

    /**
     * @param list<array{key:mixed,rowid:int}> $samples
     * @param array<int,array<string,mixed>> $rowsById
     * @param list<array<string,mixed>> $predicateTerms
     * @return list<array<string,mixed>>
     */
    private static function sampleChecks(array $samples, array $rowsById, array $predicateTerms, string $expression): array
    {
        $checks = [];
        foreach ($samples as $sample) {
            $rowid = $sample['rowid'];
            $row = $rowsById[$rowid] ?? null;
            if ($row === null) {
                $checks[] = [
                    'rowid' => $rowid,
                    'source' => 'missing-current-row',
                    'expectedExpressionKey' => $sample['key'],
                    'actualExpressionKey' => null,
                    'partialPredicateReady' => false,
                    'ready' => false,
                    'reasons' => ['missing-current-sample-row'],
                ];
                continue;
            }

            $actualKey = self::evaluateExpression($expression, $row);
            $reasons = [];
            if ($actualKey !== $sample['key']) {
                $reasons[] = 'stat4-sample-key-drift';
            }
            $predicate = self::predicateCheck($predicateTerms, $row);
            $reasons = array_values(array_merge($reasons, $predicate['reasons']));
            $checks[] = [
                'rowid' => $rowid,
                'source' => 'current',
                'expectedExpressionKey' => $sample['key'],
                'actualExpressionKey' => $actualKey,
                'partialPredicateReady' => $predicate['ready'],
                'ready' => $reasons === [],
                'reasons' => $reasons,
            ];
        }

        return $checks;
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next189 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next189 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next189 selected index missing from current source');
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next189 needs current rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next189 current rows need rowid');
            }
            if (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid'])) {
                throw new \InvalidArgumentException('SQLite next189 current rowid must be an integer');
            }
            $out[(int) $row['rowid']] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array<string,mixed>>
     */
    private static function predicateTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite next189 needs partial predicate terms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next189 partial predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @param array<string,mixed> $row
     * @return array{ready:bool,reasons:list<string>}
     */
    private static function predicateCheck(array $terms, array $row): array
    {
        $reasons = [];
        foreach ($terms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = self::leftValue($term, $row);
            $ok = match ($operator) {
                '=' => $left === self::literal($term['right'] ?? null),
                'IS NOT NULL' => $left !== null,
                '>=' => self::compare($left, self::literal($term['right'] ?? null)) >= 0,
                '>' => self::compare($left, self::literal($term['right'] ?? null)) > 0,
                '<=' => self::compare($left, self::literal($term['right'] ?? null)) <= 0,
                '<' => self::compare($left, self::literal($term['right'] ?? null)) < 0,
                'BETWEEN' => self::compare($left, self::literal($term['lower'] ?? null)) >= 0
                    && self::compare($left, self::literal($term['upper'] ?? null)) <= 0,
                default => throw new \InvalidArgumentException('SQLite next189 unsupported partial predicate operator ' . $operator),
            };
            if (!$ok) {
                $reasons[] = 'partial-predicate-' . strtolower(str_replace(' ', '-', $operator));
            }
        }

        return ['ready' => $reasons === [], 'reasons' => $reasons];
    }

    /** @param array<string,mixed> $term */
    private static function leftValue(array $term, array $row): mixed
    {
        $left = $term['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next189 predicate left side must be an array');
        }
        if (isset($left['column'])) {
            return $row[(string) $left['column']] ?? null;
        }
        if (isset($left['expression'])) {
            return self::evaluateExpression(self::normalizeExpression((string) $left['expression']), $row);
        }

        throw new \InvalidArgumentException('SQLite next189 predicate left side needs column or expression');
    }

    /** @param array<string,mixed> $row */
    private static function evaluateExpression(string $expression, array $row): mixed
    {
        if ($expression === 'lower(option_name)') {
            $value = $row['option_name'] ?? null;
            return $value === null ? null : strtolower((string) $value);
        }
        if ($expression === 'upper(option_name)') {
            $value = $row['option_name'] ?? null;
            return $value === null ? null : strtoupper((string) $value);
        }

        throw new \InvalidArgumentException('SQLite next189 unsupported expression ' . $expression);
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if ($left === null || $right === null) {
            return -1;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private static function intList(mixed $value, string $name): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next189 needs integer list ' . $name);
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_int($item) && !ctype_digit((string) $item)) {
                throw new \InvalidArgumentException('SQLite next189 list contains non-integer ' . $name);
            }
            $out[] = (int) $item;
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value, string $name): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next189 needs string list ' . $name);
        }

        return array_map(static fn (mixed $item): string => (string) $item, $value);
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param list<int> $rowids
     * @param list<int> $rejected
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $rowids, array $rejected): array
    {
        $program[] = [
            'opcode' => 'CurrentPayloadPartialFence',
            'mode' => 'next189-current-source-stat4-expression-partial-payload',
            'ready' => $ready,
            'rowids' => $rowids,
            'rejectedRowids' => $rejected,
        ];

        return $program;
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
