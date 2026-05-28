<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext253Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext250Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );

        $index = self::indexByName($currentSource, (string) ($base['selectedPlan']['name'] ?? ''));
        $fence = self::payloadFence(
            self::rowsByRowid($currentSource),
            self::payloadsByRowid($index),
            self::rowids($base['matchedRowids'] ?? null),
            $neededColumns,
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next250-ready'
            && $fence['allYieldedRowsHaveCurrentPayloads'] === true
            && $fence['payloadMismatchRowids'] === []
            && $fence['missingPayloadRowids'] === []
            && $fence['missingCurrentRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next253-ready' : 'requires-current-source-stat4-payload-reprepare',
            'stat4CurrentPayloadFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next253Ready' => $ready,
                'next253PayloadMatchedRowids' => $fence['payloadMatchedRowids'],
                'next253PayloadMismatchRowids' => $fence['payloadMismatchRowids'],
                'next253MissingPayloadRowids' => $fence['missingPayloadRowids'],
                'next253PayloadSignature' => $fence['payloadSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next253CurrentPayloadReady' => $ready,
                'next253CurrentPayloadSignature' => $fence['payloadSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT253 PAYLOAD FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT STAT4 PAYLOADS VERIFIED' : ' REQUIRES CURRENT STAT4 PAYLOAD REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext250Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next253',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next253 reuses current-source STAT4 expression partial row streams and verifies STAT4 expression payloads against the current row image before cursor reuse',
            'non_overlap' => 'adds current-source STAT4 expression payload row-image fencing after accepted next250 partial-predicate proof; avoids next250 predicate implication, next247 boundary peers, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, PRAGMA, compound SELECT, and UTF clusters',
        ]);
    }

    /**
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param array<int,array<string,mixed>> $payloadsByRowid
     * @param list<int> $yieldedRowids
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function payloadFence(array $rowsByRowid, array $payloadsByRowid, array $yieldedRowids, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next253 needed columns must be non-empty');
        }

        $matched = [];
        $mismatches = [];
        $missingPayloads = [];
        $missingRows = [];
        $proofs = [];
        foreach ($yieldedRowids as $rowid) {
            $row = $rowsByRowid[$rowid] ?? null;
            $payload = $payloadsByRowid[$rowid] ?? null;
            if ($row === null) {
                $missingRows[] = $rowid;
                continue;
            }
            if ($payload === null) {
                $missingPayloads[] = $rowid;
                continue;
            }

            $expected = self::coveredValues($row, $neededColumns);
            $actual = self::coveredValues(self::payloadCoveredValues($payload), $neededColumns);
            $expressionMatches = self::expressionKey($row) === self::payloadExpressionKey($payload);
            $coveredValuesMatch = $expected === $actual;
            $matches = $expressionMatches && $coveredValuesMatch;
            if ($matches) {
                $matched[] = $rowid;
            } else {
                $mismatches[] = $rowid;
            }
            $proofs[] = [
                'rowid' => $rowid,
                'currentExpressionKey' => self::expressionKey($row),
                'payloadExpressionKey' => self::payloadExpressionKey($payload),
                'expressionMatches' => $expressionMatches,
                'currentCoveredValues' => $expected,
                'payloadCoveredValues' => $actual,
                'coveredValuesMatch' => $coveredValuesMatch,
                'payloadMatchesCurrentRow' => $matches,
            ];
        }

        $proof = [
            'yieldedRowids' => $yieldedRowids,
            'neededColumns' => array_values($neededColumns),
            'payloadMatchedRowids' => $matched,
            'payloadMismatchRowids' => $mismatches,
            'missingPayloadRowids' => $missingPayloads,
            'missingCurrentRowids' => $missingRows,
            'rowProofs' => $proofs,
        ];

        return $proof + [
            'allYieldedRowsHaveCurrentPayloads' => $yieldedRowids !== [] && $mismatches === [] && $missingPayloads === [] && $missingRows === [],
            'payloadSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next253 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next253 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next253 selected index missing from source');
    }

    /** @param array<string,mixed> $source @return array<int,array<string,mixed>> */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next253 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next253 current source rows must be arrays');
            }
            $rowid = self::intValue($row['rowid'] ?? null, 'current rowid');
            if (isset($out[$rowid])) {
                throw new \InvalidArgumentException('SQLite next253 duplicate current rowid');
            }
            $out[$rowid] = $row;
        }

        return $out;
    }

    /** @param array<string,mixed> $index @return array<int,array<string,mixed>> */
    private static function payloadsByRowid(array $index): array
    {
        $payloads = $index['stat4ExpressionPayloads'] ?? null;
        if (!is_array($payloads) || !array_is_list($payloads)) {
            throw new \InvalidArgumentException('SQLite next253 needs STAT4 expression payloads');
        }
        $out = [];
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next253 STAT4 expression payloads must be arrays');
            }
            $rowid = self::intValue($payload['rowid'] ?? null, 'payload rowid');
            if (isset($out[$rowid])) {
                throw new \InvalidArgumentException('SQLite next253 duplicate STAT4 expression payload rowid');
            }
            $out[$rowid] = $payload;
        }

        return $out;
    }

    /** @param mixed $value @return list<int> */
    private static function rowids(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next253 needs yielded rowids');
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValue($rowid, 'yielded rowid'), $value));
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function coveredValues(array $row, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next253 needed columns must be names');
            }
            $values[$column] = $row[$column] ?? null;
        }

        return $values;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private static function payloadCoveredValues(array $payload): array
    {
        $values = $payload['coveredValues'] ?? null;
        if (!is_array($values)) {
            throw new \InvalidArgumentException('SQLite next253 STAT4 expression payload needs covered values');
        }

        return $values;
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
    }

    /** @param array<string,mixed> $payload */
    private static function payloadExpressionKey(array $payload): string
    {
        if (!array_key_exists('expressionKey', $payload)) {
            throw new \InvalidArgumentException('SQLite next253 STAT4 expression payload needs expression key');
        }

        return strtolower((string) $payload['expressionKey']);
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('SQLite next253 ' . $label . ' must be an integer');
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
            'opcode' => 'VerifyCurrentStat4ExpressionPayload',
            'mode' => 'next253-current-source-stat4-expression-payload',
            'payloadMatchedRowids' => $fence['payloadMatchedRowids'],
            'signature' => $fence['payloadSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
