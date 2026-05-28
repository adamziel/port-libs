<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext232Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext229Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $handoff = self::handoff($base);
        self::validateCursor($cursor, $handoff);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next232-ready';
        $base['currentSourceHandoffNext232'] = $handoff;
        $base['cursor']['currentPageTokenNext232'] = $handoff['currentPageToken'];
        $base['cursor']['requiredCurrentAcksNext232'] = $handoff['requiredCurrentAcks'];
        $base['cursor']['nextSourceCursorNext232'] = $handoff['nextSourceCursor'];
        $base['replanReasons'][] = 'compound-dense-rank-except-current-page-handoff-next232';
        $base['replanReasons'][] = 'recursive-limit-window-page-acks-before-next-source-next232';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-next-source-handoff-next232';
        $base['dependency_closure'] = 'no new support component needed; next232 reuses accepted next229 compound SELECT, recursive LIMIT/OFFSET, dense_rank window output, UNION DISTINCT/EXCEPT membership, current-source tokens, and adds a bounded current-page acknowledgement handoff to the next-source cursor';
        $base['non_overlap'] = 'next232 extends accepted next229 dense_rank UNION/EXCEPT compound recursive LIMIT behavior by requiring exact current-page acknowledgements before exposing the next-source cursor; it avoids next228 current-page drain over next224 rank/window behavior, next226 sum/count EXCEPT+INTERSECT, JSON table, WAL/VFS, B-tree, encoding, planner range-cost, and status-only surfaces';

        return $base;
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>|null
     */
    private static function baseCursor(?array $cursor): ?array
    {
        if ($cursor === null || !isset($cursor['currentToken'])) {
            return null;
        }

        return ['currentToken' => $cursor['currentToken']];
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function handoff(array $plan): array
    {
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $currentToken = (string) ($sourceWindow['currentToken'] ?? '');
        $nextToken = (string) ($sourceWindow['nextToken'] ?? '');
        $requiredAcks = self::ackTokens($currentRows, $currentToken);
        $pagePayload = [
            'currentToken' => $currentToken,
            'currentRows' => $currentRows,
            'currentSkippedLabels' => self::stringList($sourceWindow['currentSkippedLabels'] ?? []),
            'currentTruncatedLabels' => self::stringList($sourceWindow['currentTruncatedLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'requiredAcks' => $requiredAcks,
        ];
        $currentPageToken = hash('sha256', json_encode($pagePayload, JSON_THROW_ON_ERROR));
        $nextSourceCursor = [
            'currentToken' => $nextToken,
            'sourceEpoch' => hash('sha256', json_encode([
                'nextToken' => $nextToken,
                'nextRows' => $nextRows,
                'nextSkippedLabels' => self::stringList($sourceWindow['nextSkippedLabels'] ?? []),
                'nextTruncatedLabels' => self::stringList($sourceWindow['nextTruncatedLabels'] ?? []),
            ], JSON_THROW_ON_ERROR)),
            'resumeOffset' => is_array($plan['cursor'] ?? null) ? (int) ($plan['cursor']['resumeOffset'] ?? 0) : 0,
            'rowCount' => count($nextRows),
        ];

        return [
            'currentPageToken' => $currentPageToken,
            'requiredCurrentAcks' => $requiredAcks,
            'requiredAckCount' => count($requiredAcks),
            'currentLabels' => self::labels($currentRows),
            'nextLabels' => self::labels($nextRows),
            'nextOnlyLabels' => self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []),
            'currentOnlyLabels' => self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []),
            'currentSkippedLabels' => self::stringList($sourceWindow['currentSkippedLabels'] ?? []),
            'nextSkippedLabels' => self::stringList($sourceWindow['nextSkippedLabels'] ?? []),
            'currentTruncatedLabels' => self::stringList($sourceWindow['currentTruncatedLabels'] ?? []),
            'nextTruncatedLabels' => self::stringList($sourceWindow['nextTruncatedLabels'] ?? []),
            'currentToken' => $currentToken,
            'nextToken' => $nextToken,
            'tokensDiffer' => $currentToken !== $nextToken,
            'nextSourceCursor' => $nextSourceCursor,
            'nextExposure' => 'held-until-current-page-acks-match',
            'yieldBoundary' => 'compound-recursive-window-next232-current-page-handoff-fences-next-source',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $handoff
     */
    private static function validateCursor(?array $cursor, array $handoff): void
    {
        if ($cursor === null) {
            return;
        }
        if (isset($cursor['currentPageTokenNext232']) && $cursor['currentPageTokenNext232'] !== $handoff['currentPageToken']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next232 cursor does not match current page token');
        }
        if (!array_key_exists('acknowledgedCurrentAcksNext232', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCurrentAcksNext232'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next232 acknowledged current rows must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedCurrentAcksNext232']));
        $required = self::stringList($handoff['requiredCurrentAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next232 current-page acknowledgements do not match required handoff set');
        }
    }

    /**
     * @param mixed $value
     * @return list<array<string,mixed>>
     */
    private static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''), $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function ackTokens(array $rows, string $currentToken): array
    {
        $tokens = [];
        foreach ($rows as $index => $row) {
            $tokens[] = hash('sha256', json_encode([
                'token' => $currentToken,
                'index' => $index,
                'row' => $row,
            ], JSON_THROW_ON_ERROR));
        }

        return $tokens;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }
}
