<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext249Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) ($base['selectedPlan']['name'] ?? '');
        $index = self::indexByName($currentSource, $selectedName);
        $fence = self::duplicatePeerFence(self::rows($currentSource), $whereTerms, $index);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next245-ready'
            && $fence['ready'] === true;

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next249-ready' : 'requires-current-source-stat4-duplicate-peer-reprepare',
            'stat4DuplicatePeerFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next249Ready' => $ready,
                'next249PeerSignature' => $fence['proofSignature'],
                'next249RejectedPeerKeys' => $fence['rejectedPeerKeys'],
                'next249CurrentPeerCounts' => $fence['currentPeerCounts'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next249DuplicatePeerReady' => $ready,
                'next249DuplicatePeerSignature' => $fence['proofSignature'],
                'next249RejectedPeerKeys' => $fence['rejectedPeerKeys'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT249 DUPLICATE PEER FENCE '
                . $selectedName
                . ($ready ? ' CURRENT STAT4 DUPLICATE PEERS VERIFIED' : ' REQUIRES CURRENT STAT4 DUPLICATE PEER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext245Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next249',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next249 reuses current-source STAT4 expression partial rowsets and adds duplicate peer-count validation',
            'non_overlap' => 'adds STAT4 duplicate peer-count validation after accepted next245 sample-rowid anchor validation; avoids anchor validation duplicates, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, UTF, and suite-runner clusters',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,mixed> $index
     * @return array<string,mixed>
     */
    private static function duplicatePeerFence(array $rows, array $whereTerms, array $index): array
    {
        $peers = [];
        foreach ($rows as $row) {
            if (!self::rowSatisfiesTerms($row, $whereTerms)) {
                continue;
            }
            $key = self::peerKey(self::rowExpressionKey($row), (int) ($row['blog_id'] ?? 0));
            $peers[$key] ??= [
                'expressionKey' => self::rowExpressionKey($row),
                'blogId' => (int) ($row['blog_id'] ?? 0),
                'rowids' => [],
            ];
            $peers[$key]['rowids'][] = self::rowid($row);
        }
        ksort($peers);
        foreach ($peers as &$peer) {
            sort($peer['rowids']);
        }
        unset($peer);

        $proofs = [];
        $rejected = [];
        foreach (self::stat4Samples($index) as $sample) {
            $key = self::peerKey($sample['key'], $sample['blogId']);
            $peer = $peers[$key] ?? null;
            $currentCount = is_array($peer) ? count($peer['rowids']) : 0;
            $matches = $currentCount === $sample['neq'];
            $proofs[] = [
                'sampleKey' => $sample['key'],
                'sampleBlogId' => $sample['blogId'],
                'sampleRowid' => $sample['rowid'],
                'sampleNeq' => $sample['neq'],
                'currentPeerCount' => $currentCount,
                'currentPeerRowids' => is_array($peer) ? $peer['rowids'] : [],
                'matchesCurrentPeers' => $matches,
            ];
            if (!$matches) {
                $rejected[] = $key;
            }
        }

        $peerCounts = [];
        foreach ($peers as $key => $peer) {
            $peerCounts[$key] = count($peer['rowids']);
        }
        $ready = $proofs !== [] && $rejected === [];
        $proof = [
            'currentPeerCounts' => $peerCounts,
            'currentPeerRows' => array_values($peers),
            'sampleCount' => count($proofs),
            'duplicatePeerProofs' => $proofs,
            'rejectedPeerKeys' => array_values(array_unique($rejected)),
            'indexName' => (string) ($index['name'] ?? ''),
        ];

        return $proof + [
            'ready' => $ready,
            'rejectedReason' => $ready ? null : 'stale-stat4-duplicate-peer-count',
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next249 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next249 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next249 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next249 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next249 current source rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param array<string,mixed> $index @return list<array{key:string,rowid:int,blogId:int,neq:int}> */
    private static function stat4Samples(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples) || $samples === []) {
            throw new \InvalidArgumentException('SQLite next249 needs stat4Samples');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 3) {
                throw new \InvalidArgumentException('SQLite next249 stat4 samples need key, rowid, and blog id');
            }
            $neq = self::firstStatInteger($sample['neq'] ?? null);
            $out[] = [
                'key' => strtolower((string) $sample['sample'][0]),
                'rowid' => self::rowid(['rowid' => $sample['sample'][1]]),
                'blogId' => (int) $sample['sample'][2],
                'neq' => $neq,
            ];
        }

        return $out;
    }

    private static function firstStatInteger(mixed $value): int
    {
        $parts = preg_split('/\s+/', trim((string) $value));
        if ($parts === false || $parts === [] || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next249 stat4 neq needs a leading integer');
        }

        return (int) $parts[0];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms): bool
    {
        foreach ($terms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                return false;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = array_key_exists('expression', $left)
                ? self::rowExpressionKey($row)
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            if ($operator === '=' && $value != ($term['right'] ?? null)) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
            if ($operator === 'LIKE' && !self::likePrefix((string) $value, (string) ($term['right'] ?? ''))) {
                return false;
            }
            if ($operator === 'BETWEEN') {
                $stringValue = strtolower((string) $value);
                $lower = self::stringOrNull($term['lower'] ?? null);
                $upper = self::stringOrNull($term['upper'] ?? null);
                if (($lower !== null && $stringValue < $lower) || ($upper !== null && $stringValue > $upper)) {
                    return false;
                }
            }
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $comparison = strcmp(strtolower((string) $value), strtolower((string) ($term['right'] ?? '')));
                if (($operator === '>' && $comparison <= 0)
                    || ($operator === '>=' && $comparison < 0)
                    || ($operator === '<' && $comparison >= 0)
                    || ($operator === '<=' && $comparison > 0)
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function likePrefix(string $value, string $pattern): bool
    {
        if ($pattern === 'plugin_%') {
            return str_starts_with(strtolower($value), 'plugin_');
        }
        if (str_ends_with($pattern, '%') && !str_contains(substr($pattern, 0, -1), '_')) {
            return str_starts_with(strtolower($value), strtolower(substr($pattern, 0, -1)));
        }

        return strtolower($value) === strtolower($pattern);
    }

    /** @param array<string,mixed> $row */
    private static function rowExpressionKey(array $row): string
    {
        if (!array_key_exists('option_name', $row)) {
            throw new \InvalidArgumentException('SQLite next249 current row needs option_name');
        }

        return strtolower((string) $row['option_name']);
    }

    /** @param array<string,mixed> $row */
    private static function rowid(array $row): int
    {
        if (!array_key_exists('rowid', $row) || (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid']))) {
            throw new \InvalidArgumentException('SQLite next249 rowid must be an integer');
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
            'opcode' => 'ValidateCurrentSourceStat4DuplicatePeers',
            'mode' => 'next249-current-source-stat4-expression-partial-duplicate-peers',
            'sampleCount' => $fence['sampleCount'],
            'currentPeerCounts' => $fence['currentPeerCounts'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function peerKey(string $expressionKey, int $blogId): string
    {
        return strtolower($expressionKey) . '#' . $blogId;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return $value === null ? null : strtolower((string) $value);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
