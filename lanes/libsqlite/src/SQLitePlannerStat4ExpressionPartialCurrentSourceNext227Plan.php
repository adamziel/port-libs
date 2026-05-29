<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext227Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $cardinalityFence = self::peerCardinalityFence($currentIndex, self::sampleProofs($base));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next224-ready'
            && $cardinalityFence['allSelectedSamplePeerCountsMatchCurrentPayloads'] === true
            && $cardinalityFence['expressionKeysWithStalePeerCounts'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next227-ready' : 'requires-current-source-stat4-peer-cardinality-reprepare',
            'stat4PeerCardinalityFence' => $cardinalityFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next227Ready' => $ready,
                'next227PeerCardinalitySignature' => $cardinalityFence['peerCardinalitySignature'],
                'next227PeerCardinalityProofSignature' => $cardinalityFence['proofSignature'],
                'next227StalePeerCountKeys' => $cardinalityFence['expressionKeysWithStalePeerCounts'],
                'next227SelectedPeerCounts' => $cardinalityFence['selectedPeerCounts'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next227PeerCardinalitySignature' => $cardinalityFence['peerCardinalitySignature'],
                'next227PeerCardinalityProofSignature' => $cardinalityFence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $cardinalityFence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT227 PEER CARDINALITY FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 NEQ MATCHES PAYLOAD PEERS' : ' REQUIRES CURRENT STAT4 PEER CARDINALITY REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext224Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next227',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next227 reuses current-source STAT4 expression partial payloads and validates sqlite_stat4 neq peer cardinality before cursor admission',
            'non_overlap' => 'adds current sqlite_stat4 neq peer-cardinality validation for selected expression keys after accepted next224 sample-order validation; avoids grouped LIKE/OR, rowid alias, payload coverage, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next227 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next227 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next227 selected index missing from source');
    }

    /** @param array<string,mixed> $base @return list<array<string,mixed>> */
    private static function sampleProofs(array $base): array
    {
        $fence = $base['stat4SampleOrderFence'] ?? null;
        $proofs = is_array($fence) ? ($fence['matchedSampleProofs'] ?? null) : null;
        if (!is_array($proofs) || !array_is_list($proofs)) {
            throw new \InvalidArgumentException('SQLite next227 needs next224 sample proofs');
        }
        foreach ($proofs as $proof) {
            if (!is_array($proof)) {
                throw new \InvalidArgumentException('SQLite next227 sample proofs must be arrays');
            }
        }

        return $proofs;
    }

    /**
     * @param array<string,mixed> $index
     * @param list<array<string,mixed>> $sampleProofs
     * @return array<string,mixed>
     */
    private static function peerCardinalityFence(array $index, array $sampleProofs): array
    {
        $payloadCounts = self::payloadPeerCounts($index['stat4ExpressionPayloads'] ?? null);
        $selected = [];
        foreach ($sampleProofs as $proof) {
            $key = self::proofKey($proof);
            if (isset($selected[$key])) {
                continue;
            }
            $stat4Neq = self::proofInt($proof, 'sampleNeq');
            $payloadCount = $payloadCounts[$key] ?? 0;
            $selected[$key] = [
                'expressionKey' => $key,
                'stat4Neq' => $stat4Neq,
                'currentPayloadPeerCount' => $payloadCount,
                'matchesCurrentPayloadPeers' => $stat4Neq === $payloadCount,
                'sampleOrdinal' => self::proofNullableInt($proof, 'sampleOrdinal'),
                'sampleNlt' => self::proofNullableInt($proof, 'sampleNlt'),
                'sampleNdlt' => self::proofNullableInt($proof, 'sampleNdlt'),
            ];
        }
        $stale = array_values(array_map(
            static fn (array $row): string => $row['expressionKey'],
            array_filter($selected, static fn (array $row): bool => $row['matchesCurrentPayloadPeers'] !== true),
        ));

        return [
            'payloadPeerCounts' => $payloadCounts,
            'selectedPeerCounts' => array_values($selected),
            'expressionKeysWithStalePeerCounts' => $stale,
            'allSelectedSamplePeerCountsMatchCurrentPayloads' => $stale === [] && $selected !== [],
            'peerCardinalitySignature' => self::signature(array_map(
                static fn (array $row): array => [
                    'expressionKey' => $row['expressionKey'],
                    'stat4Neq' => $row['stat4Neq'],
                    'currentPayloadPeerCount' => $row['currentPayloadPeerCount'],
                ],
                array_values($selected),
            )),
            'proofSignature' => self::signature([$payloadCounts, array_values($selected), $stale]),
        ];
    }

    /** @return array<string,int> */
    private static function payloadPeerCounts(mixed $payloads): array
    {
        if (!is_array($payloads) || !array_is_list($payloads) || $payloads === []) {
            throw new \InvalidArgumentException('SQLite next227 needs stat4ExpressionPayloads');
        }
        $counts = [];
        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next227 payload entries must be arrays');
            }
            $key = self::payloadExpressionKey($payload);
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    /** @param array<string,mixed> $payload */
    private static function payloadExpressionKey(array $payload): string
    {
        if (array_key_exists('expressionKey', $payload)) {
            return strtolower((string) $payload['expressionKey']);
        }
        $covered = $payload['coveredValues'] ?? null;
        if (is_array($covered) && array_key_exists('option_name', $covered)) {
            return strtolower((string) $covered['option_name']);
        }
        if (array_key_exists('option_name', $payload)) {
            return strtolower((string) $payload['option_name']);
        }

        throw new \InvalidArgumentException('SQLite next227 payload entries need expressionKey or option_name');
    }

    /** @param array<string,mixed> $proof */
    private static function proofKey(array $proof): string
    {
        if (!array_key_exists('expressionKey', $proof)) {
            throw new \InvalidArgumentException('SQLite next227 sample proof needs expressionKey');
        }

        return strtolower((string) $proof['expressionKey']);
    }

    /** @param array<string,mixed> $proof */
    private static function proofInt(array $proof, string $key): int
    {
        if (!array_key_exists($key, $proof) || (!is_int($proof[$key]) && !ctype_digit((string) $proof[$key]))) {
            throw new \InvalidArgumentException('SQLite next227 sample proof ' . $key . ' must be an integer');
        }

        return (int) $proof[$key];
    }

    /** @param array<string,mixed> $proof */
    private static function proofNullableInt(array $proof, string $key): ?int
    {
        if (($proof[$key] ?? null) === null) {
            return null;
        }

        return self::proofInt($proof, $key);
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
            'opcode' => 'RecheckCurrentStat4PeerCardinality',
            'mode' => 'next227-current-source-stat4-expression-partial-peer-cardinality',
            'selectedPeerCounts' => $fence['selectedPeerCounts'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
