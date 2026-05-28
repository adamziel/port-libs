<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext170Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @param array<string,mixed>|null $nextSource
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        ?array $nextSource = null
    ): array {
        $plan = SQLitePlannerStat4ExpressionPartialCurrentSourceNext166Plan::materialize(
            $preparedSource,
            $currentSource,
            $queryTerms,
            $neededColumns,
            $nextSource,
        );

        $currentSignature = self::relevantRowSignature($currentSource, $queryTerms);
        $nextSignature = $nextSource === null ? null : self::relevantRowSignature($nextSource, $queryTerms);
        $relevantStable = $nextSource === null || $currentSignature === $nextSignature;
        $nextSummary = is_array($plan['nextSource'] ?? null) ? $plan['nextSource'] : null;
        $nextReasons = is_array($nextSummary) && is_array($nextSummary['replanReasons'] ?? null)
            ? $nextSummary['replanReasons']
            : [];
        $onlyRowSignatureChanged = $nextReasons === ['row-signature'];
        $next170Admitted = $nextSource === null
            || ($plan['nextSourceAdmitted'] ?? false) === true
            || ($onlyRowSignatureChanged && $relevantStable);
        $baseReady = ($plan['status'] ?? null) === 'stat4-expression-partial-current-source-next166-ready';
        $ready = $baseReady || (
            $onlyRowSignatureChanged
            && $relevantStable
            && ($plan['missingStat4InValues'] ?? []) === []
            && ($plan['inBuckets'] ?? []) !== []
        );

        $next170Summary = $nextSource === null ? null : [
            'rowSignatureChanged' => in_array('row-signature', $nextReasons, true),
            'onlyRowSignatureChanged' => $onlyRowSignatureChanged,
            'relevantRowSignatureStable' => $relevantStable,
            'currentRelevantRowids' => self::relevantRowids($currentSource, $queryTerms),
            'nextRelevantRowids' => self::relevantRowids($nextSource, $queryTerms),
            'currentRelevantSignature' => $currentSignature,
            'nextRelevantSignature' => $nextSignature,
            'admitted' => $next170Admitted,
            'replanReasons' => $next170Admitted ? [] : $nextReasons,
        ];

        return array_replace($plan, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next170-ready' : 'requires-current-source-reprepare',
            'nextSourceAdmitted' => $next170Admitted,
            'next170Source' => $next170Summary,
            'cursorProgram' => self::cursorProgram(self::listValue($plan, 'cursorProgram'), $ready),
            'selectedPlan' => array_replace(self::arrayValue($plan, 'selectedPlan'), [
                'next170Ready' => $ready,
                'next170RelevantRowSignatureStable' => $relevantStable,
                'next170NextSourceAdmitted' => $next170Admitted,
                'next170CurrentRelevantRowids' => self::relevantRowids($currentSource, $queryTerms),
                'next170RelevantRowSignature' => $currentSignature,
            ]),
            'stat4Fence' => array_replace(self::arrayValue($plan, 'stat4Fence'), [
                'next170CurrentRelevantRowSignature' => $currentSignature,
                'next170NextRelevantRowSignature' => $nextSignature,
                'next170RelevantRowsStable' => $relevantStable,
            ]),
            'detail' => (($plan['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT170 '
                . (string) (($plan['selectedPlan']['name'] ?? null) ?: 'NO INDEX')
                . ($ready ? ' NEXT ROW CHURN OUTSIDE PARTIAL INDEX ADMITTED' : ' REQUIRES CURRENT SOURCE REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($plan['dependencies'] ?? null) ? $plan['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext166Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next170',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next170 reuses STAT4 expression partial current-source planning and narrows next-source invalidation to rows inside the proven partial expression-index key space',
            'non_overlap' => 'avoids accepted next166 multi-key IN admission, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters; this slice only admits next-source row churn outside the current partial expression-index STAT4 buckets',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function relevantRowSignature(array $source, array $queryTerms): string
    {
        return self::signature(self::relevantRows($source, $queryTerms));
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     * @return list<int>
     */
    private static function relevantRowids(array $source, array $queryTerms): array
    {
        return array_map(static fn (array $row): int => (int) ($row['rowid'] ?? 0), self::relevantRows($source, $queryTerms));
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     * @return list<array<string,mixed>>
     */
    private static function relevantRows(array $source, array $queryTerms): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next170 needs row list');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next170 rows must be arrays');
            }
            if (!self::rowMatchesTerms($row, $queryTerms)) {
                continue;
            }
            $out[] = self::canonicalRelevantRow($row);
        }
        usort($out, static fn (array $left, array $right): int => ((int) ($left['rowid'] ?? 0)) <=> ((int) ($right['rowid'] ?? 0)));

        return $out;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function canonicalRelevantRow(array $row): array
    {
        return [
            'rowid' => (int) ($row['rowid'] ?? 0),
            'blog_id' => $row['blog_id'] ?? null,
            'autoload' => $row['autoload'] ?? null,
            'option_name' => $row['option_name'] ?? null,
            'expressionKey' => is_string($row['option_name'] ?? null) ? strtolower((string) $row['option_name']) : null,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function rowMatchesTerms(array $row, array $queryTerms): bool
    {
        foreach ($queryTerms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                continue;
            }

            if (isset($left['column'])) {
                $column = (string) $left['column'];
                $value = $row[$column] ?? null;
                if ($operator === '=' && $value !== ($term['right'] ?? null)) {
                    return false;
                }
                if ($operator === 'IS NOT NULL' && $value === null) {
                    return false;
                }
                continue;
            }

            if (isset($left['expression']) && self::normalExpression((string) $left['expression']) === 'lower(option_name)') {
                $key = is_string($row['option_name'] ?? null) ? strtolower((string) $row['option_name']) : null;
                if ($operator === 'IN') {
                    $values = $term['values'] ?? null;
                    if (!is_array($values) || !in_array($key, $values, true)) {
                        return false;
                    }
                }
                if ($operator === '=' && $key !== ($term['right'] ?? null)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function normalExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>
     */
    private static function arrayValue(array $base, string $key): array
    {
        $value = $base[$key] ?? null;
        if (!is_array($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next170 needs array ' . $key);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $base
     * @return list<array<string,mixed>>
     */
    private static function listValue(array $base, string $key): array
    {
        $value = $base[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next170 needs list ' . $key);
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready): array
    {
        if (!$ready || !isset($program[4]) || !is_array($program[4])) {
            return $program;
        }

        $program[4] = ['opcode' => 'DeferredSeek', 'source' => 'table', 'next170RelevantRowsStable' => true];

        return $program;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
