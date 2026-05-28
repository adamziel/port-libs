<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext188Plan
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
        $samples = self::sampleRows($currentIndex);
        $rows = self::matchedRows($base);
        $peerFence = self::peerFence($rows, $samples);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next185-ready'
            && $peerFence['deterministicRowidTiebreak'] === true
            && $peerFence['allPeersBracketedByStat4'] === true
            && $peerFence['ambiguousPeerKeys'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next188-ready' : 'requires-current-source-peer-reprepare',
            'peerFence' => $peerFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next188Ready' => $ready,
                'next188DuplicateExpressionKeys' => $peerFence['duplicateExpressionKeys'],
                'next188PeerRowids' => $peerFence['peerRowids'],
                'next188AmbiguousPeerKeys' => $peerFence['ambiguousPeerKeys'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next188PeerSignature' => self::signature($peerFence),
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $peerFence
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT188 PEER ROWID FENCE '
                . $selectedName
                . ($ready ? ' DUPLICATE KEYS ORDERED' : ' REQUIRES CURRENT SOURCE PEER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext185Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next188',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next188 reuses current-source STAT4 expression partial sample provenance and adds duplicate expression-key peer rowid fencing',
            'non_overlap' => 'avoids accepted next185 sample provenance, next182 LIMIT windows, next180 descending scans, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits duplicate expression-key peers when current-source rowid tiebreak order is deterministic and STAT4-bracketed',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next188 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next188 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next188 selected index missing from source');
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array{key:string,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed}>
     */
    private static function sampleRows(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next188 needs STAT4 sample list');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next188 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next188 STAT4 samples need expression key and rowid');
            }
            if (!is_int($values[1]) && !ctype_digit((string) $values[1])) {
                throw new \InvalidArgumentException('SQLite next188 STAT4 sample rowid must be an integer');
            }
            $out[] = [
                'key' => (string) $values[0],
                'rowid' => (int) $values[1],
                'neq' => $sample['neq'] ?? null,
                'nlt' => $sample['nlt'] ?? null,
                'ndlt' => $sample['ndlt'] ?? null,
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcmp($a['key'], $b['key']) ?: ($a['rowid'] <=> $b['rowid']));

        return $out;
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next188 needs matched row list');
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array{key:string,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed}> $samples
     * @return array<string,mixed>
     */
    private static function peerFence(array $rows, array $samples): array
    {
        $groups = [];
        foreach ($rows as $position => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next188 matched rows must be arrays');
            }
            if (!array_key_exists('expressionKey', $row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next188 matched rows need expressionKey and rowid');
            }
            if (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid'])) {
                throw new \InvalidArgumentException('SQLite next188 matched rowid must be an integer');
            }
            $key = (string) $row['expressionKey'];
            $groups[$key][] = [
                'position' => $position,
                'rowid' => (int) $row['rowid'],
                'sample' => self::sampleFor($samples, $key, (int) $row['rowid']),
                'bracket' => self::bracketFor($samples, $key),
            ];
        }

        $duplicateKeys = [];
        $peerRowids = [];
        $peerDetails = [];
        $ambiguous = [];
        foreach ($groups as $key => $entries) {
            $rowids = array_column($entries, 'rowid');
            if (count($entries) > 1) {
                $duplicateKeys[] = $key;
                $peerRowids[$key] = $rowids;
                $sorted = $rowids;
                sort($sorted);
                if ($rowids !== $sorted || count(array_unique($rowids)) !== count($rowids)) {
                    $ambiguous[] = $key;
                }
            }
            foreach ($entries as $entry) {
                $bracket = $entry['bracket'];
                $peerDetails[] = [
                    'key' => $key,
                    'rowid' => $entry['rowid'],
                    'position' => $entry['position'],
                    'sampleAnchor' => $entry['sample'] !== null,
                    'anchorRowid' => $entry['sample']['rowid'] ?? null,
                    'lowerSampleKey' => $bracket['lower']['key'] ?? null,
                    'upperSampleKey' => $bracket['upper']['key'] ?? null,
                    'bracketedByStat4' => $bracket['bracketed'],
                ];
                if (!$bracket['bracketed']) {
                    $ambiguous[] = $key;
                }
            }
        }
        $ambiguous = array_values(array_unique($ambiguous));
        sort($duplicateKeys);

        return [
            'duplicateExpressionKeys' => $duplicateKeys,
            'peerRowids' => $peerRowids,
            'peerDetails' => $peerDetails,
            'deterministicRowidTiebreak' => $ambiguous === [],
            'allPeersBracketedByStat4' => self::allBracketed($peerDetails),
            'ambiguousPeerKeys' => $ambiguous,
            'peerSignature' => self::signature([$duplicateKeys, $peerRowids, $peerDetails]),
        ];
    }

    /**
     * @param list<array{key:string,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed}> $samples
     * @return array{key:string,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed}|null
     */
    private static function sampleFor(array $samples, string $key, int $rowid): ?array
    {
        foreach ($samples as $sample) {
            if ($sample['key'] === $key && $sample['rowid'] === $rowid) {
                return $sample;
            }
        }

        return null;
    }

    /**
     * @param list<array{key:string,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed}> $samples
     * @return array{lower:?array{key:string,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed},upper:?array{key:string,rowid:int,neq:mixed,nlt:mixed,ndlt:mixed},bracketed:bool}
     */
    private static function bracketFor(array $samples, string $key): array
    {
        $lower = null;
        $upper = null;
        foreach ($samples as $sample) {
            if (strcmp($sample['key'], $key) <= 0) {
                $lower = $sample;
            }
            if ($upper === null && strcmp($sample['key'], $key) >= 0) {
                $upper = $sample;
            }
        }

        return [
            'lower' => $lower,
            'upper' => $upper,
            'bracketed' => $lower !== null && $upper !== null,
        ];
    }

    /** @param list<array<string,mixed>> $peerDetails */
    private static function allBracketed(array $peerDetails): bool
    {
        foreach ($peerDetails as $detail) {
            if (($detail['bracketedByStat4'] ?? null) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $peerFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $peerFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'Stat4PeerRowidFence',
            'mode' => 'next188-current-source-stat4-expression-partial-peers',
            'duplicateExpressionKeys' => $peerFence['duplicateExpressionKeys'],
            'peerRowids' => $peerFence['peerRowids'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
