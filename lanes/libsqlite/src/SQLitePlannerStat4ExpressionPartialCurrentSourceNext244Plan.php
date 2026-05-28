<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
            $limit,
            $offset,
        );
        $index = self::indexByName($currentSource, (string) ($base['selectedPlan']['name'] ?? ''));
        $fence = self::windowFence(
            self::rows($currentSource),
            $whereTerms,
            $index,
            self::rowids($base['matchedRowids'] ?? null),
            $limit,
            $offset,
        );
        $residualReady = is_array($base['stat4ResidualWhereFence'] ?? null)
            && ($base['stat4ResidualWhereFence']['allMatchedRowsSatisfyResidualWhere'] ?? false) === true
            && ($base['stat4ResidualWhereFence']['residualRejectedRowids'] ?? null) === [];
        $ready = $residualReady
            && $fence['windowMatchesCurrentSource'] === true
            && $fence['windowMismatchRowids'] === []
            && $fence['missingWindowRowids'] === []
            && $fence['extraWindowRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next244-ready' : 'requires-current-source-stat4-window-reprepare',
            'stat4CurrentWindowFence' => $fence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next244Ready' => $ready,
                'next244CurrentWindowRowids' => $fence['currentWindowRowids'],
                'next244YieldedWindowRowids' => $fence['yieldedWindowRowids'],
                'next244WindowMismatchRowids' => $fence['windowMismatchRowids'],
                'next244ProofSignature' => $fence['proofSignature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next244CurrentWindowSignature' => $fence['currentWindowSignature'],
                'next244ProofSignature' => $fence['proofSignature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $fence,
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT244 WINDOW FENCE '
                . (string) ($base['selectedPlan']['name'] ?? 'NO INDEX')
                . ($ready ? ' CURRENT LIMIT OFFSET WINDOW VERIFIED' : ' REQUIRES CURRENT WINDOW REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next244',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next244 reuses current-source STAT4 expression partial residual fences and adds LIMIT/OFFSET window validation against current partial expression order',
            'non_overlap' => 'adds current-source LIMIT/OFFSET window validation after accepted next241 residual WHERE checks; avoids next238 payload validation, next232 counters, next231 page membership, next228 sample partial proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters',
        ]);
    }

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next244 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next244 source indexes must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next244 selected current index missing');
    }

    /** @param array<string,mixed> $source @return list<array<string,mixed>> */
    private static function rows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next244 needs current source rows');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next244 current rows must be arrays');
            }
        }

        return $rows;
    }

    /** @param mixed $value @return list<int> */
    private static function rowids(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next244 needs yielded rowids');
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
    private static function windowFence(array $rows, array $whereTerms, array $index, array $yieldedRowids, int $limit, int $offset): array
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('SQLite next244 needs non-negative LIMIT/OFFSET');
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

        $currentWindow = array_slice($ordered, $offset, $limit);
        $currentRowids = array_map(static fn (array $row): int => $row['rowid'], $currentWindow);
        $missing = array_values(array_diff($currentRowids, $yieldedRowids));
        $extra = array_values(array_diff($yieldedRowids, $currentRowids));
        $mismatches = [];
        $count = max(count($currentRowids), count($yieldedRowids));
        for ($i = 0; $i < $count; $i++) {
            if (($currentRowids[$i] ?? null) !== ($yieldedRowids[$i] ?? null)) {
                if (isset($yieldedRowids[$i])) {
                    $mismatches[] = $yieldedRowids[$i];
                }
                if (isset($currentRowids[$i])) {
                    $mismatches[] = $currentRowids[$i];
                }
            }
        }
        $mismatches = array_values(array_unique($mismatches));
        $proof = [
            'limit' => $limit,
            'offset' => $offset,
            'descending' => $descending,
            'orderedCurrentRowids' => array_map(static fn (array $row): int => $row['rowid'], $ordered),
            'currentWindowRowids' => $currentRowids,
            'yieldedWindowRowids' => $yieldedRowids,
            'currentWindowRows' => $currentWindow,
            'windowMismatchRowids' => $mismatches,
            'missingWindowRowids' => $missing,
            'extraWindowRowids' => $extra,
        ];

        return $proof + [
            'windowMatchesCurrentSource' => $currentRowids === $yieldedRowids,
            'currentWindowSignature' => self::signature([$limit, $offset, $descending, $currentRowids]),
            'proofSignature' => self::signature($proof),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms): bool
    {
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next244 where terms must be arrays');
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if (!self::termSatisfied($operator, self::leftValue($term['left'] ?? null, $row), $term)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $term */
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
            default => throw new \InvalidArgumentException('SQLite next244 unsupported where operator ' . $operator),
        };
    }

    /** @param array<string,mixed>|mixed $left @param array<string,mixed> $row */
    private static function leftValue(mixed $left, array $row): mixed
    {
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite next244 where term needs left operand');
        }
        if (isset($left['column']) && is_string($left['column'])) {
            return $row[$left['column']] ?? null;
        }
        $expression = strtolower(preg_replace('/\s+/', '', (string) ($left['expression'] ?? '')) ?? '');
        if ($expression === 'lower(option_name)') {
            return self::expressionKey($row);
        }

        throw new \InvalidArgumentException('SQLite next244 unsupported where expression');
    }

    /** @param array<string,mixed> $row */
    private static function expressionKey(array $row): string
    {
        return strtolower((string) ($row['option_name'] ?? ''));
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
        $regex = '/\A' . strtr(preg_quote($pattern, '/'), ['%' => '.*', '_' => '.']) . '\z/i';

        return preg_match($regex, $value) === 1;
    }

    private static function intValue(mixed $value, string $label): int
    {
        if (!is_int($value)) {
            throw new \InvalidArgumentException('SQLite next244 needs integer ' . $label);
        }

        return $value;
    }

    /** @param array<string,mixed> $fence @param list<array<string,mixed>> $program @return list<array<string,mixed>> */
    private static function cursorProgram(array $program, bool $ready, array $fence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'VerifyCurrentStat4LimitOffsetWindow',
            'mode' => 'next244-current-source-stat4-expression-partial-window',
            'currentWindowRowids' => $fence['currentWindowRowids'],
            'yieldedWindowRowids' => $fence['yieldedWindowRowids'],
            'signature' => $fence['proofSignature'],
        ];

        return $program;
    }

    /** @param mixed $value */
    private static function signature($value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
