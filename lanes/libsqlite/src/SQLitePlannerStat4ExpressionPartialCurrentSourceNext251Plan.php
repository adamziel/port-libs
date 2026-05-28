<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext251Plan
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
        $fence = self::coveringPayloadFence(
            self::rowsByRowid($currentSource),
            self::payloadsByRowid($index),
            self::rowids($base['matchedRowids'] ?? null),
            $neededColumns,
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next247-ready'
            && $fence['allCoveringPayloadsMatchCurrentSource'] === true
            && $fence['missingPayloadRowids'] === []
            && $fence['stalePayloadRowids'] === []
            && $fence['missingCoveredColumnProofs'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next251-ready' : 'requires-current-source-stat4-covering-payload-reprepare',
            'stat4CoveringPayloadFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next251Ready' => $ready,
                'next251CheckedRowids' => $fence['checkedRowids'],
                'next251NeededColumns' => $fence['neededColumns'],
                'next251StalePayloadRowids' => $fence['stalePayloadRowids'],
                'next251MissingPayloadRowids' => $fence['missingPayloadRowids'],
                'next251ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next251CoveringPayloadReady' => $ready,
                'next251CoveringPayloadSignature' => $fence['coveringPayloadSignature'],
                'next251ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT251 COVERING PAYLOAD FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT COVERING PAYLOADS VERIFIED' : ' REQUIRES CURRENT COVERING PAYLOAD REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext247Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next251',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next251 reuses current-source STAT4 expression partial boundary-peer validation and adds covering-payload freshness checks for yielded rows',
            'non_overlap' => 'adds current-source covering payload freshness validation for yielded STAT4 partial expression-index rows; avoids accepted next247 boundary peers, next246/247 STAT4 current-source planning, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next251 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next251 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next251 selected current index missing');
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowid(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next251 needs current source rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next251 current rows must be arrays');
            }
            $out[self::intValue($row['rowid'] ?? null, 'current rowid')] = $row;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $index
     * @return array<int,array<string,mixed>>
     */
    private static function payloadsByRowid(array $index): array
    {
        $payloads = $index['stat4ExpressionPayloads'] ?? null;
        if (!is_array($payloads) || !array_is_list($payloads)) {
            throw new \InvalidArgumentException('SQLite next251 selected index needs stat4ExpressionPayloads');
        }
        $out = [];
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next251 payload entries must be arrays');
            }
            $rowid = self::intValue($payload['rowid'] ?? null, 'payload rowid');
            $covered = $payload['coveredValues'] ?? null;
            if (!is_array($covered)) {
                throw new \InvalidArgumentException('SQLite next251 payload entries need coveredValues');
            }
            $out[$rowid] = $covered;
        }

        return $out;
    }

    /** @param mixed $value @return list<int> */
    private static function rowids(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next251 needs yielded rowids');
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValue($rowid, 'yielded rowid'), $value));
    }

    /**
     * @param array<int,array<string,mixed>> $rowsByRowid
     * @param array<int,array<string,mixed>> $payloadsByRowid
     * @param list<int> $yieldedRowids
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function coveringPayloadFence(array $rowsByRowid, array $payloadsByRowid, array $yieldedRowids, array $neededColumns): array
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next251 needs at least one covering column');
        }
        $neededColumns = array_values(array_unique(array_map(
            static fn (string $column): string => trim($column),
            $neededColumns,
        )));
        if (in_array('', $neededColumns, true)) {
            throw new \InvalidArgumentException('SQLite next251 covering column names must be non-empty');
        }

        $proofs = [];
        $missingPayloads = [];
        $stalePayloads = [];
        $missingColumns = [];
        foreach ($yieldedRowids as $rowid) {
            $row = $rowsByRowid[$rowid] ?? null;
            if ($row === null) {
                throw new \InvalidArgumentException('SQLite next251 yielded row missing from current source');
            }
            $payload = $payloadsByRowid[$rowid] ?? null;
            if ($payload === null) {
                $missingPayloads[] = $rowid;
                $payload = [];
            }
            $columnProofs = [];
            $rowStale = false;
            foreach ($neededColumns as $column) {
                $hasPayload = array_key_exists($column, $payload);
                $hasCurrent = array_key_exists($column, $row);
                if (!$hasPayload || !$hasCurrent) {
                    $missingColumns[] = [
                        'rowid' => $rowid,
                        'column' => $column,
                        'payloadColumnPresent' => $hasPayload,
                        'currentColumnPresent' => $hasCurrent,
                    ];
                    $rowStale = true;
                }
                $matches = $hasPayload && $hasCurrent && self::sameValue($payload[$column], $row[$column]);
                if (!$matches) {
                    $rowStale = true;
                }
                $columnProofs[] = [
                    'column' => $column,
                    'payloadValue' => $hasPayload ? $payload[$column] : null,
                    'currentValue' => $hasCurrent ? $row[$column] : null,
                    'matches' => $matches,
                ];
            }
            if ($rowStale) {
                $stalePayloads[] = $rowid;
            }
            $proofs[] = [
                'rowid' => $rowid,
                'columnProofs' => $columnProofs,
                'payloadFresh' => !$rowStale,
            ];
        }
        $stalePayloads = array_values(array_unique($stalePayloads));
        $missingPayloads = array_values(array_unique($missingPayloads));

        $proof = [
            'checkedRowids' => $yieldedRowids,
            'neededColumns' => $neededColumns,
            'payloadProofs' => $proofs,
            'missingPayloadRowids' => $missingPayloads,
            'stalePayloadRowids' => $stalePayloads,
            'missingCoveredColumnProofs' => $missingColumns,
        ];

        return $proof + [
            'allCoveringPayloadsMatchCurrentSource' => $missingPayloads === [] && $stalePayloads === [] && $missingColumns === [],
            'coveringPayloadSignature' => self::signature([$yieldedRowids, $neededColumns, $payloadsByRowid]),
            'proofSignature' => self::signature($proof),
        ];
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
            'mode' => 'next251-current-source-stat4-expression-partial-covering-payload',
            'rowids' => $fence['checkedRowids'],
            'neededColumns' => $fence['neededColumns'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next251 ' . $label . ' must be an integer');
        }

        return (int) $value;
    }

    private static function sameValue(mixed $left, mixed $right): bool
    {
        if (is_int($left) || is_float($left) || is_int($right) || is_float($right)) {
            return (string) $left === (string) $right;
        }

        return $left === $right;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
