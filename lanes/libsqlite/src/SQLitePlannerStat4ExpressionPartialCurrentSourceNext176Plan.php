<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext176Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $neededColumns,
        );
        $selected = self::arrayValue($base, 'selectedPlan');
        $range = self::arrayValue($selected, 'rangeConstraint');
        $boundary = self::rangeBoundary($range);
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next164-ready'
            && ($selected['partialPredicateImpliedByRange'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true
            && ($selected['matchedRowCount'] ?? 0) > 0
            && ($boundary['usesExactBoundaryOpcodes'] ?? false) === true
            && self::rowBoundaryAudit(self::listValue($base['matchedRows'] ?? []), $range, (string) ($selected['collation'] ?? 'BINARY'))['leakedRowids'] === [];

        $audit = self::rowBoundaryAudit(self::listValue($base['matchedRows'] ?? []), $range, (string) ($selected['collation'] ?? 'BINARY'));

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next176-ready' : 'requires-next-stage',
            'rangeBoundary' => $boundary,
            'boundaryRowAudit' => $audit,
            'cursorProgram' => self::cursorProgram($selected, $boundary, $audit, $ready),
            'selectedPlan' => array_replace($selected, [
                'next176Ready' => $ready,
                'next176SeekOpcode' => $boundary['lowerSeekOpcode'],
                'next176UpperFenceOpcode' => $boundary['upperFenceOpcode'],
                'next176BoundarySignature' => self::signature([$boundary, $audit['acceptedRowids']]),
            ]),
            'stat4Fence' => array_replace(self::arrayValue($base, 'stat4Fence'), [
                'next176BoundarySignature' => self::signature([$boundary, $audit['acceptedRowids']]),
                'next176AcceptedRowids' => $audit['acceptedRowids'],
            ]),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT176 EXACT RANGE BOUNDARIES '
                . (string) ($selected['name'] ?? 'NO INDEX'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next176',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next176 reuses native STAT4 expression partial current-source planning and adds exact lower/upper range-bound cursor opcodes',
            'non_overlap' => 'avoids accepted next173 duplicate STAT4 sample fanout, next172 selectivity refresh, next168 LIKE prefix conversion, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters; this slice only fixes exact exclusive/inclusive expression range cursor boundaries',
        ]);
    }

    /**
     * @param array<string,mixed> $range
     * @return array<string,mixed>
     */
    private static function rangeBoundary(array $range): array
    {
        $lowerInclusive = (bool) ($range['lowerInclusive'] ?? false);
        $upperInclusive = (bool) ($range['upperInclusive'] ?? false);

        return [
            'lowerKey' => $range['lower'] ?? null,
            'upperKey' => $range['upper'] ?? null,
            'lowerInclusive' => $lowerInclusive,
            'upperInclusive' => $upperInclusive,
            'lowerSeekOpcode' => $lowerInclusive ? 'SeekGE' : 'SeekGT',
            'upperFenceOpcode' => $upperInclusive ? 'IdxLE' : 'IdxLT',
            'usesExactBoundaryOpcodes' => true,
        ];
    }

    /**
     * @param list<array<string,mixed>> $matchedRows
     * @param array<string,mixed> $range
     * @return array{acceptedRowids:list<int>,acceptedKeys:list<mixed>,leakedRowids:list<int>,lowerEdgeRowids:list<int>,upperEdgeRowids:list<int>,signature:string}
     */
    private static function rowBoundaryAudit(array $matchedRows, array $range, string $collation): array
    {
        $accepted = [];
        $leaked = [];
        $lowerEdge = [];
        $upperEdge = [];
        foreach ($matchedRows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next176 matched rows must be arrays');
            }
            $rowid = self::intValue($row['rowid'] ?? null);
            $key = $row['expressionKey'] ?? null;
            $lower = self::compare($key, $range['lower'] ?? null, $collation);
            $upper = self::compare($key, $range['upper'] ?? null, $collation);
            $inside = (($range['lowerInclusive'] ?? false) ? $lower >= 0 : $lower > 0)
                && (($range['upperInclusive'] ?? false) ? $upper <= 0 : $upper < 0);
            if ($lower === 0) {
                $lowerEdge[] = $rowid;
            }
            if ($upper === 0) {
                $upperEdge[] = $rowid;
            }
            if ($inside) {
                $accepted[] = ['rowid' => $rowid, 'key' => $key];
            } else {
                $leaked[] = $rowid;
            }
        }

        return [
            'acceptedRowids' => array_column($accepted, 'rowid'),
            'acceptedKeys' => array_column($accepted, 'key'),
            'leakedRowids' => $leaked,
            'lowerEdgeRowids' => $lowerEdge,
            'upperEdgeRowids' => $upperEdge,
            'signature' => self::signature($accepted),
        ];
    }

    /**
     * @param array<string,mixed> $selected
     * @param array<string,mixed> $boundary
     * @param array<string,mixed> $audit
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $selected, array $boundary, array $audit, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'exact STAT4 expression partial range boundaries not usable']];
        }

        return [
            ['opcode' => 'OpenRead', 'rootPage' => $selected['rootPage'] ?? null, 'index' => $selected['name'] ?? null],
            ['opcode' => 'FenceExactRangeBoundary', 'signature' => $audit['signature']],
            ['opcode' => $boundary['lowerSeekOpcode'], 'key' => $boundary['lowerKey']],
            ['opcode' => $boundary['upperFenceOpcode'], 'key' => $boundary['upperKey']],
            ['opcode' => (($selected['covering'] ?? false) ? 'ColumnFromIndex' : 'DeferredSeek')],
            ['opcode' => 'ResidualPartialCheck'],
            ['opcode' => 'ResultRow', 'rowids' => $audit['acceptedRowids']],
            ['opcode' => 'Next'],
        ];
    }

    /** @return array<string,mixed> */
    private static function arrayValue(array $array, string $key): array
    {
        $value = $array[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite next176 needs array ' . $key);
        }

        return $value;
    }

    /** @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    private static function intValue(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite next176 rowid must be a non-negative integer');
        }

        return $value;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        $a = (string) $left;
        $b = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $a = strtolower($a);
            $b = strtolower($b);
        }

        return $a <=> $b;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', serialize($value));
    }
}
