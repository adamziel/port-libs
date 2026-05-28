<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext191Plan
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
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext188Plan::materialize(
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
        $rows = self::matchedRows($base);
        $payloadFence = self::payloadExpressionFence($rows, $expression, self::sampleKeysByRowid($currentIndex));
        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next188-ready'
            && $payloadFence['allPayloadExpressionKeysMatch'] === true
            && $payloadFence['mismatchedRowids'] === []
            && $payloadFence['nullExpressionRowids'] === [];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next191-ready' : 'requires-current-source-expression-payload-reprepare',
            'payloadExpressionFence' => $payloadFence,
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next191Ready' => $ready,
                'next191Expression' => $expression,
                'next191PayloadExpressionColumn' => $currentIndex['expressionColumn'] ?? null,
                'next191MismatchedRowids' => $payloadFence['mismatchedRowids'],
                'next191PayloadExpressionSignature' => $payloadFence['signature'],
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next191PayloadExpressionSignature' => $payloadFence['signature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $payloadFence
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT191 PAYLOAD EXPRESSION FENCE '
                . $selectedName
                . ($ready ? ' COVERING PAYLOAD KEYS VERIFIED' : ' REQUIRES CURRENT SOURCE EXPRESSION PAYLOAD REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext188Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next191',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next191 reuses current-source STAT4 expression partial peer fences and adds a covering payload expression-key recheck',
            'non_overlap' => 'avoids accepted next188 peer rowid fencing, next185 sample provenance, next182 LIMIT windows, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters; this slice only admits current-source partial expression-index rows whose covering payload still recomputes to the indexed expression key',
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
            throw new \InvalidArgumentException('SQLite next191 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next191 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next191 selected index missing from source');
    }

    /** @param array<string,mixed> $index */
    private static function expression(array $index): string
    {
        $expression = $index['expression'] ?? null;
        if (!is_string($expression) || trim($expression) === '') {
            throw new \InvalidArgumentException('SQLite next191 selected index needs expression text');
        }
        $normalized = strtolower(preg_replace('/\s+/', '', $expression) ?? '');
        if ($normalized !== 'lower(option_name)') {
            throw new \InvalidArgumentException('SQLite next191 supports lower(option_name) expression indexes');
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
            throw new \InvalidArgumentException('SQLite next191 needs matched row list');
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function payloadExpressionFence(array $rows, string $expression, array $sampleKeysByRowid): array
    {
        $details = [];
        $mismatched = [];
        $nulls = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists('rowid', $row) || !array_key_exists('expressionKey', $row)) {
                throw new \InvalidArgumentException('SQLite next191 matched rows need rowid and expressionKey');
            }
            if (!is_int($row['rowid']) && !ctype_digit((string) $row['rowid'])) {
                throw new \InvalidArgumentException('SQLite next191 matched rowid must be an integer');
            }
            $payload = $row['payload'] ?? null;
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite next191 matched rows need payload arrays');
            }
            if (!array_key_exists('option_name', $payload)) {
                throw new \InvalidArgumentException('SQLite next191 payload missing option_name');
            }

            $rowid = (int) $row['rowid'];
            $actual = self::evaluateLowerOptionName($payload['option_name']);
            $expected = (string) $row['expressionKey'];
            $sampleKey = $sampleKeysByRowid[$rowid] ?? null;
            $matches = $actual !== null && $actual === $expected && ($sampleKey === null || $sampleKey === $actual);
            if ($actual === null) {
                $nulls[] = $rowid;
            }
            if (!$matches) {
                $mismatched[] = $rowid;
            }
            $details[] = [
                'rowid' => $rowid,
                'expression' => $expression,
                'indexedExpressionKey' => $expected,
                'stat4SampleExpressionKey' => $sampleKey,
                'payloadOptionName' => $payload['option_name'],
                'payloadExpressionKey' => $actual,
                'matchesIndexedKey' => $matches,
            ];
        }

        return [
            'expression' => $expression,
            'checkedRowids' => array_column($details, 'rowid'),
            'details' => $details,
            'mismatchedRowids' => $mismatched,
            'nullExpressionRowids' => $nulls,
            'allPayloadExpressionKeysMatch' => $mismatched === [] && $nulls === [],
            'signature' => self::signature($details),
        ];
    }

    /**
     * @param array<string,mixed> $index
     * @return array<int,string>
     */
    private static function sampleKeysByRowid(array $index): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite next191 needs STAT4 sample list');
        }
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next191 STAT4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_key_exists(0, $values) || !array_key_exists(1, $values)) {
                throw new \InvalidArgumentException('SQLite next191 STAT4 samples need expression key and rowid');
            }
            if (!is_int($values[1]) && !ctype_digit((string) $values[1])) {
                throw new \InvalidArgumentException('SQLite next191 STAT4 sample rowid must be an integer');
            }
            $out[(int) $values[1]] = (string) $values[0];
        }

        return $out;
    }

    private static function evaluateLowerOptionName(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('SQLite next191 option_name payload must be scalar or null');
        }

        return strtolower((string) $value);
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param array<string,mixed> $payloadFence
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $payloadFence): array
    {
        if (!$ready) {
            return $program;
        }
        $program[] = [
            'opcode' => 'RecheckCoveringPayloadExpressionKey',
            'mode' => 'next191-current-source-stat4-expression-partial-payload',
            'expression' => $payloadFence['expression'],
            'rowids' => $payloadFence['checkedRowids'],
            'signature' => $payloadFence['signature'],
        ];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
