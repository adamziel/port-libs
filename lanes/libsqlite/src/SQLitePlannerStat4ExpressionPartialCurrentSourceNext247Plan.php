<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext247Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $index = self::indexByName($currentSource, (string) ($base['selectedPlan']['name'] ?? ''));
        $fence = self::boundaryPeerFence(
            self::rows($currentSource),
            $whereTerms,
            $index,
            self::rowids($base['matchedRowids'] ?? null),
            $limit,
            $offset,
        );
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next244-ready'
            && $fence['boundaryPeersMatchCurrentSource'] === true
            && $fence['peerMismatchRowids'] === []
            && $fence['missingPeerRowids'] === []
            && $fence['extraPeerRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next247-ready' : 'requires-current-source-stat4-boundary-peer-reprepare',
            'stat4BoundaryPeerFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next247Ready' => $ready,
                'next247BoundaryKeys' => $fence['boundaryExpressionKeys'],
                'next247CurrentPeerRowids' => $fence['currentBoundaryPeerRowids'],
                'next247YieldedPeerRowids' => $fence['yieldedBoundaryPeerRowids'],
                'next247PeerMismatchRowids' => $fence['peerMismatchRowids'],
                'next247ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next247BoundaryPeerReady' => $ready,
                'next247BoundaryPeerSignature' => $fence['boundaryPeerSignature'],
                'next247ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT247 BOUNDARY PEER FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT BOUNDARY PEERS VERIFIED' : ' REQUIRES CURRENT BOUNDARY PEER REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next247',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next247 reuses current-source STAT4 expression partial LIMIT/OFFSET fences and adds duplicate expression-key boundary peer validation',
            'non_overlap' => 'adds boundary peer validation for duplicate expression keys around current-source LIMIT/OFFSET windows; avoids accepted next244 window validation, next235 vector counters, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next247 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next247 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next247 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next247 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next247 current rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param mixed $value @return list<int> */
    private static function rowids(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next247 needs yielded rowids');
        }

        return array_values(array_map(static fn (mixed $rowid): int => self::intValue($rowid, 'yielded rowid'), $value));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $whereTerms
     * @param array<string,mixed> $index
     * @param list<int> $yieldedRowids
     * @return array<string,mixed>
     */
    private static function boundaryPeerFence(array $rows, array $whereTerms, array $index, array $yieldedRowids, int $limit, int $offset): array
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next247 needs non-negative LIMIT/OFFSET');
        }
        $ordered = [];
        foreach ($rows as $row) {
            if (self::rowSatisfiesTerms($row, $whereTerms)) {
                $ordered[] = [
                    'rowid' => self::intValue($row['rowid'] ?? null, 'current rowid'),
                    'expressionKey' => self::expressionKey($row),
                    'optionName' => (string) ($row['option_name'] ?? ''),
                    'blogId' => (int) ($row['blog_id'] ?? 0),
                ];
            }
        }
        $descending = (bool) ($index['descending'] ?? false);
        usort($ordered, static function (array $left, array $right) use ($descending): int {
            $key = strcmp($left['expressionKey'], $right['expressionKey']);
            if ($key !== 0) {
                return $descending ? -$key : $key;
            }
            $blog = $left['blogId'] <=> $right['blogId'];
            if ($blog !== 0) {
                return $blog;
            }

            return $left['rowid'] <=> $right['rowid'];
        });

        $window = array_slice($ordered, $offset, $limit);
        $boundaryKeys = self::boundaryKeys($window);
        $currentPeers = self::peerRowsForKeys($ordered, $boundaryKeys);
        $yieldedPeers = self::peerRowsForKeys(
            array_values(array_filter(
                $ordered,
                static fn (array $row): bool => in_array($row['rowid'], $yieldedRowids, true),
            )),
            $boundaryKeys,
        );
        $currentPeerRowids = array_map(static fn (array $row): int => $row['rowid'], $currentPeers);
        $yieldedPeerRowids = array_map(static fn (array $row): int => $row['rowid'], $yieldedPeers);
        $missing = array_values(array_diff($currentPeerRowids, $yieldedPeerRowids));
        $extra = array_values(array_diff($yieldedPeerRowids, $currentPeerRowids));
        $mismatches = [];
        $count = max(count($currentPeerRowids), count($yieldedPeerRowids));
        for ($i = 0; $i < $count; $i++) {
            if (($currentPeerRowids[$i] ?? null) !== ($yieldedPeerRowids[$i] ?? null)) {
                if (isset($currentPeerRowids[$i])) {
                    $mismatches[] = $currentPeerRowids[$i];
                }
                if (isset($yieldedPeerRowids[$i])) {
                    $mismatches[] = $yieldedPeerRowids[$i];
                }
            }
        }
        $mismatches = array_values(array_unique($mismatches));
        $proof = [
            'limit' => $limit,
            'offset' => $offset,
            'descending' => $descending,
            'orderedCurrentRowids' => array_map(static fn (array $row): int => $row['rowid'], $ordered),
            'windowRowids' => array_map(static fn (array $row): int => $row['rowid'], $window),
            'boundaryExpressionKeys' => $boundaryKeys,
            'currentBoundaryPeerRowids' => $currentPeerRowids,
            'yieldedBoundaryPeerRowids' => $yieldedPeerRowids,
            'currentBoundaryPeerRows' => $currentPeers,
            'missingPeerRowids' => $missing,
            'extraPeerRowids' => $extra,
            'peerMismatchRowids' => $mismatches,
        ];

        return $proof + [
            'boundaryPeersMatchCurrentSource' => $currentPeerRowids === $yieldedPeerRowids,
            'boundaryPeerSignature' => self::signature([$limit, $offset, $descending, $boundaryKeys, $currentPeerRowids]),
            'proofSignature' => self::signature($proof),
        ];
    }

    /** @param list<array<string,mixed>> $window @return list<string> */
    private static function boundaryKeys(array $window): array
    {
        if ($window === []) {
            return [];
        }
        $keys = [(string) $window[array_key_first($window)]['expressionKey']];
        $keys[] = (string) $window[array_key_last($window)]['expressionKey'];

        return array_values(array_unique($keys));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $keys
     * @return list<array<string,mixed>>
     */
    private static function peerRowsForKeys(array $rows, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => in_array((string) $row['expressionKey'], $keys, true),
        ));
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms): bool
    {
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next247 where terms must be arrays');
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if (!self::termSatisfied($operator, self::leftValue($term['left'] ?? null, $row), $term)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $term */
    private static function termSatisfied(string $operator, mixed $left, array $term): bool
    {
        return match ($operator) {
            '=' => self::compare($left, $term['right'] ?? null) === 0,
            'IS NOT NULL' => $left !== null,
            'LIKE' => self::like((string) $left, (string) ($term['right'] ?? '')),
            'BETWEEN' => self::compare($left, $term['lower'] ?? null) >= 0 && self::compare($left, $term['upper'] ?? null) <= 0,
            default => throw new \InvalidArgumentException('SQLite next247 unsupported operator ' . $operator),
        };
    }

    /** @param mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next247 term left operand must be an array');
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

        throw new \InvalidArgumentException('SQLite next247 unsupported left operand');
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

        throw new \InvalidArgumentException('SQLite next247 ' . $label . ' must be an integer');
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
            'opcode' => 'VerifyCurrentStat4BoundaryPeers',
            'mode' => 'next247-current-source-stat4-expression-partial-boundary-peers',
            'boundaryExpressionKeys' => $fence['boundaryExpressionKeys'],
            'currentBoundaryPeerRowids' => $fence['currentBoundaryPeerRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
