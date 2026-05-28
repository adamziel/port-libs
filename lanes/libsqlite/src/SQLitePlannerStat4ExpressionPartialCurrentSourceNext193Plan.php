<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext193Plan
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
        $constraints = self::rowidConstraints($whereTerms);
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext189Plan::materialize(
            $preparedSource,
            $currentSource,
            self::nonRowidTerms($whereTerms),
            $neededColumns,
            $limit,
            $offset,
        );
        $rowids = self::intList($base['matchedRowids'] ?? null, 'matchedRowids');
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $rowsById = self::rowsById($currentSource);
        $checks = [];
        $rejected = [];
        foreach ($rowids as $rowid) {
            $row = $rowsById[$rowid] ?? null;
            $reasons = [];
            if ($row === null) {
                $reasons[] = 'missing-current-row';
            }
            foreach ($constraints as $constraint) {
                if (!self::rowidMatches($rowid, $constraint)) {
                    $reasons[] = 'rowid-' . strtolower(str_replace(' ', '-', $constraint['operator']));
                }
            }
            if ($reasons !== []) {
                $rejected[] = $rowid;
            }
            $checks[] = [
                'rowid' => $rowid,
                'source' => $row === null ? 'missing-current-row' : 'current',
                'constraints' => $constraints,
                'ready' => $reasons === [],
                'reasons' => $reasons,
                'payloadSignature' => $row === null ? null : self::signature($row),
            ];
        }

        $sampleChecks = self::sampleRowidChecks(self::currentSamples($currentSource, $selectedName), $constraints, $rowsById);
        $sampleRejected = array_values(array_map(
            static fn (array $check): int => (int) $check['rowid'],
            array_filter($sampleChecks, static fn (array $check): bool => ($check['ready'] ?? false) !== true),
        ));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next189-ready'
            && $constraints !== []
            && $checks !== []
            && $rejected === []
            && $sampleRejected === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next193-ready' : 'requires-current-source-rowid-reprepare',
            'rowidAliasFence' => [
                'ready' => $ready,
                'constraints' => $constraints,
                'rowids' => $rowids,
                'rejectedRowids' => $rejected,
                'sampleRejectedRowids' => $sampleRejected,
                'checks' => $checks,
                'sampleChecks' => $sampleChecks,
                'signature' => self::signature([$constraints, $checks, $sampleChecks]),
            ],
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next193Ready' => $ready,
                'next193RowidConstraints' => $constraints,
                'next193RejectedRowids' => array_values(array_unique(array_merge($rejected, $sampleRejected))),
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next193RowidAliasSignature' => self::signature([$constraints, $checks, $sampleChecks]),
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $constraints,
                $rowids,
                $rejected
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT193 ROWID ALIAS FENCE '
                . $selectedName
                . ($ready ? ' CURRENT ROWIDS VERIFIED' : ' REQUIRES CURRENT SOURCE ROWID REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext189Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next193',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next193 reuses STAT4 expression partial current-source payload fences and adds rowid/_rowid_/oid alias constraint admission checks',
            'non_overlap' => 'avoids accepted next189 payload partial fences, next188 duplicate peer fences, range-cost ranking, expression ORDER BY, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only rejects stale STAT4 partial-expression plans whose current rowid alias constraints are not proven against the current source',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $whereTerms
     * @return list<array{alias:string,operator:string,values:list<int>}>
     */
    private static function rowidConstraints(array $whereTerms): array
    {
        $constraints = [];
        foreach ($whereTerms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next193 where terms must be arrays');
            }
            $left = $term['left'] ?? null;
            if (!is_array($left) || !isset($left['column']) || !self::isRowidAlias((string) $left['column'])) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $constraints[] = match ($operator) {
                '=' => ['alias' => (string) $left['column'], 'operator' => '=', 'values' => [self::intValue($term['right'] ?? null, 'rowid right value')]],
                'IN' => ['alias' => (string) $left['column'], 'operator' => 'IN', 'values' => self::intValues($term['values'] ?? null, 'rowid IN values')],
                'BETWEEN' => ['alias' => (string) $left['column'], 'operator' => 'BETWEEN', 'values' => [
                    self::intValue($term['lower'] ?? null, 'rowid lower value'),
                    self::intValue($term['upper'] ?? null, 'rowid upper value'),
                ]],
                '>' => ['alias' => (string) $left['column'], 'operator' => '>', 'values' => [self::intValue($term['right'] ?? null, 'rowid right value')]],
                '>=' => ['alias' => (string) $left['column'], 'operator' => '>=', 'values' => [self::intValue($term['right'] ?? null, 'rowid right value')]],
                '<' => ['alias' => (string) $left['column'], 'operator' => '<', 'values' => [self::intValue($term['right'] ?? null, 'rowid right value')]],
                '<=' => ['alias' => (string) $left['column'], 'operator' => '<=', 'values' => [self::intValue($term['right'] ?? null, 'rowid right value')]],
                default => throw new \InvalidArgumentException('SQLite next193 unsupported rowid alias operator ' . $operator),
            };
        }

        return $constraints;
    }

    /**
     * @param list<array<string,mixed>> $whereTerms
     * @return list<array<string,mixed>>
     */
    private static function nonRowidTerms(array $whereTerms): array
    {
        $terms = [];
        foreach ($whereTerms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite next193 where terms must be arrays');
            }
            $left = $term['left'] ?? null;
            if (is_array($left) && isset($left['column']) && self::isRowidAlias((string) $left['column'])) {
                continue;
            }
            $terms[] = $term;
        }

        return $terms;
    }

    /** @param array{alias:string,operator:string,values:list<int>} $constraint */
    private static function rowidMatches(int $rowid, array $constraint): bool
    {
        $values = $constraint['values'];

        return match ($constraint['operator']) {
            '=' => $rowid === $values[0],
            'IN' => in_array($rowid, $values, true),
            'BETWEEN' => $rowid >= $values[0] && $rowid <= $values[1],
            '>' => $rowid > $values[0],
            '>=' => $rowid >= $values[0],
            '<' => $rowid < $values[0],
            '<=' => $rowid <= $values[0],
            default => false,
        };
    }

    /**
     * @param list<array{key:mixed,rowid:int}> $samples
     * @param list<array{alias:string,operator:string,values:list<int>}> $constraints
     * @param array<int,array<string,mixed>> $rowsById
     * @return list<array<string,mixed>>
     */
    private static function sampleRowidChecks(array $samples, array $constraints, array $rowsById): array
    {
        $checks = [];
        foreach ($samples as $sample) {
            $rowid = $sample['rowid'];
            $reasons = [];
            if (!isset($rowsById[$rowid])) {
                $reasons[] = 'missing-current-sample-row';
            }
            foreach ($constraints as $constraint) {
                if (!self::rowidMatches($rowid, $constraint)) {
                    $reasons[] = 'sample-rowid-' . strtolower(str_replace(' ', '-', $constraint['operator']));
                }
            }
            $checks[] = [
                'rowid' => $rowid,
                'key' => $sample['key'],
                'ready' => $reasons === [],
                'reasons' => $reasons,
            ];
        }

        return $checks;
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array{key:mixed,rowid:int}>
     */
    private static function currentSamples(array $source, string $selectedName): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next193 needs current indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next193 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') !== $selectedName) {
                continue;
            }
            $samples = $index['stat4Samples'] ?? null;
            if (!is_array($samples) || !array_is_list($samples)) {
                throw new \InvalidArgumentException('SQLite next193 needs STAT4 sample list');
            }
            $out = [];
            foreach ($samples as $sample) {
                if (!is_array($sample)) {
                    throw new \InvalidArgumentException('SQLite next193 STAT4 samples must be arrays');
                }
                $values = $sample['sample'] ?? null;
                if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                    throw new \InvalidArgumentException('SQLite next193 STAT4 samples need expression key and rowid');
                }
                $out[] = ['key' => $values[0], 'rowid' => self::intValue($values[1], 'sample rowid')];
            }

            return $out;
        }

        throw new \InvalidArgumentException('SQLite next193 selected index missing from current source');
    }

    /**
     * @param array<string,mixed> $source
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next193 needs current rows');
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row)) {
                throw new \InvalidArgumentException('SQLite next193 current rows need rowid');
            }
            $rowid = self::intValue($row['rowid'], 'current rowid');
            $out[$rowid] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private static function intList(mixed $value, string $name): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next193 needs integer list ' . $name);
        }

        return array_map(static fn (mixed $item): int => self::intValue($item, $name), $value);
    }

    /** @return list<int> */
    private static function intValues(mixed $value, string $name): array
    {
        if (!is_array($value) || !array_is_list($value) || $value === []) {
            throw new \InvalidArgumentException('SQLite next193 needs non-empty integer list ' . $name);
        }

        return array_map(static fn (mixed $item): int => self::intValue($item, $name), $value);
    }

    private static function intValue(mixed $value, string $name): int
    {
        if (is_array($value) && array_key_exists('literal', $value)) {
            $value = $value['literal'];
        }
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next193 invalid integer ' . $name);
        }

        return (int) $value;
    }

    private static function isRowidAlias(string $column): bool
    {
        return in_array(strtolower($column), ['rowid', '_rowid_', 'oid'], true);
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param list<array{alias:string,operator:string,values:list<int>}> $constraints
     * @param list<int> $rowids
     * @param list<int> $rejected
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $constraints, array $rowids, array $rejected): array
    {
        $program[] = [
            'opcode' => 'RowidAliasFence',
            'mode' => 'next193-current-source-stat4-expression-partial-rowid',
            'ready' => $ready,
            'constraints' => $constraints,
            'rowids' => $rowids,
            'rejectedRowids' => $rejected,
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
    }
}
