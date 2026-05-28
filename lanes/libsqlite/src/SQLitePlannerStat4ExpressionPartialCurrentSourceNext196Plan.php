<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext196Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext192Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $expression = self::expression($currentIndex);
        $descending = (bool) ($currentIndex['descending'] ?? false);
        $rows = self::matchedRows($base);
        $peerFence = self::peerOrderFence($rows, $expression, $descending, self::sourcePositions($currentSource));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next192-ready'
            && $peerFence['peerOrderStable'] === true
            && $peerFence['outOfOrderPeerRowids'] === []
            && $peerFence['nullExpressionRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next196-ready' : 'requires-current-source-peer-order-reprepare',
            'peerOrderFence' => $peerFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next196Ready' => $ready,
                'next196Expression' => $expression,
                'next196Descending' => $descending,
                'next196OutOfOrderPeerRowids' => $peerFence['outOfOrderPeerRowids'],
                'next196PeerOrderSignature' => $peerFence['signature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next196PeerOrderSignature' => $peerFence['signature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $peerFence
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT196 PEER ORDER FENCE '
                . $selectedName
                . ($ready ? ' CURRENT PEERS VERIFIED' : ' REQUIRES CURRENT SOURCE PEER ORDER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext192Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next196',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next196 reuses current-source STAT4 expression partial covering fences and adds a duplicate expression-key peer rowid order fence',
            'non_overlap' => 'avoids accepted next192 covering-column admission, next191 payload expression-key rechecks, next189 payload partial fences, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and encoding clusters; this slice only rejects current-source STAT4 partial expression windows whose duplicate expression-key peers are not in stable rowid order',
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
            throw new \InvalidArgumentException('SQLite next196 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next196 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next196 selected index missing from source');
    }

    /** @param array<string,mixed> $index */
    private static function expression(array $index): string
    {
        $expression = $index['expression'] ?? null;
        if (!is_string($expression) || trim($expression) === '') {
            throw new \InvalidArgumentException('SQLite next196 selected index needs expression text');
        }
        $normalized = strtolower(preg_replace('/\s+/', '', $expression) ?? '');
        if ($normalized !== 'lower(option_name)') {
            throw new \InvalidArgumentException('SQLite next196 supports lower(option_name) expression indexes');
        }

        return 'lower(option_name)';
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $base): array
    {
        $rows = $base['matchedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next196 needs matched row list');
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,int>
     */
    private static function sourcePositions(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next196 needs source rows');
        }
        $positions = [];
        foreach ($rows as $position => $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next196 source rows need rowid');
            }
            if (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid'])) {
                throw new \InvalidArgumentException('SQLite next196 source rowid must be an integer');
            }
            $positions[(int) $row['rowid']] = $position;
        }

        return $positions;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<int,int> $sourcePositions
     * @return array<string,mixed>
     */
    private static function peerOrderFence(array $rows, string $expression, bool $descending, array $sourcePositions): array
    {
        $details = [];
        $peers = [];
        $nulls = [];
        foreach ($rows as $position => $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next196 matched rows need rowid');
            }
            if (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid'])) {
                throw new \InvalidArgumentException('SQLite next196 matched rowid must be an integer');
            }
            $payload = $row['payload'] ?? null;
            if (!is_array($payload) || !array_key_exists('option_name', $payload)) {
                throw new \InvalidArgumentException('SQLite next196 matched rows need option_name payload');
            }

            $rowid = (int) $row['rowid'];
            $key = self::evaluateLowerOptionName($payload['option_name']);
            if ($key === null) {
                $nulls[] = $rowid;
                $key = '__SQLITE_NEXT196_NULL__';
            }
            $peers[$key][] = $rowid;
            $details[] = [
                'position' => $position,
                'rowid' => $rowid,
                'sourcePosition' => $sourcePositions[$rowid] ?? null,
                'expression' => $expression,
                'payloadOptionName' => $payload['option_name'],
                'expressionKey' => $key === '__SQLITE_NEXT196_NULL__' ? null : $key,
            ];
        }

        $peerDetails = [];
        $outOfOrder = [];
        foreach ($peers as $key => $rowids) {
            $expected = $rowids;
            sort($expected, SORT_NUMERIC);
            $sourceOrder = $rowids;
            usort($sourceOrder, static fn (int $left, int $right): int => ($sourcePositions[$left] ?? PHP_INT_MAX) <=> ($sourcePositions[$right] ?? PHP_INT_MAX));
            $stable = $rowids === $expected && $rowids === $sourceOrder;
            if (!$stable) {
                $outOfOrder = array_values(array_unique(array_merge($outOfOrder, $rowids)));
            }
            $peerDetails[] = [
                'expressionKey' => $key === '__SQLITE_NEXT196_NULL__' ? null : $key,
                'rowids' => $rowids,
                'expectedRowids' => $expected,
                'sourceOrderRowids' => $sourceOrder,
                'stable' => $stable,
                'duplicatePeer' => count($rowids) > 1,
            ];
        }

        return [
            'expression' => $expression,
            'descending' => $descending,
            'checkedRowids' => array_column($details, 'rowid'),
            'details' => $details,
            'peers' => $peerDetails,
            'duplicatePeerKeys' => array_values(array_map(
                static fn (array $peer): mixed => $peer['expressionKey'],
                array_filter($peerDetails, static fn (array $peer): bool => $peer['duplicatePeer'] === true),
            )),
            'outOfOrderPeerRowids' => $outOfOrder,
            'nullExpressionRowids' => $nulls,
            'peerOrderStable' => $outOfOrder === [] && $nulls === [],
            'signature' => self::signature([$details, $peerDetails, $descending]),
        ];
    }

    private static function evaluateLowerOptionName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtolower((string) $value);
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
            'opcode' => 'Stat4ExpressionPeerOrderFence',
            'mode' => 'next196-current-source-stat4-expression-partial-peer-order',
            'rowids' => $peerFence['checkedRowids'],
            'duplicatePeerKeys' => $peerFence['duplicatePeerKeys'],
        ];

        return $program;
    }

    /** @param mixed $value */
    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
