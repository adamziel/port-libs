<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext235Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext232Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $barrier = self::barrier($base);
        self::validateCursor($cursor, $barrier);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next235-ready';
        $base['recursiveWindowPromotionBarrierNext235'] = $barrier;
        $base['cursor']['promotionBarrierTokenNext235'] = $barrier['barrierToken'];
        $base['cursor']['requiredPromotionAcksNext235'] = $barrier['requiredPromotionAcks'];
        $base['cursor']['currentPageTokenNext235'] = $barrier['currentPageToken'];
        $base['cursor']['recursiveTraceTokenNext235'] = $barrier['recursiveTraceToken'];
        $base['cursor']['windowFrameTokenNext235'] = $barrier['windowFrameToken'];
        $base['cursor']['nextSourceCursorNext235'] = $barrier['promotedNextSourceCursor'];
        $base['replanReasons'][] = 'compound-recursive-window-promotion-barrier-next235';
        $base['replanReasons'][] = 'recursive-limit-trace-and-window-frame-acks-next235';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-promotion-barrier-next235';
        $base['dependency_closure'] = 'no new support component needed; next235 reuses accepted next232 compound SELECT recursive/window current-page handoff and adds bounded recursive trace plus window-frame acknowledgement tokens before promoting a next-source cursor';
        $base['non_overlap'] = 'next235 extends accepted next232 page-ack handoff by requiring recursive queue and window-frame acknowledgements before next-source promotion; it avoids accepted next226 sum/count EXCEPT+INTERSECT, next229 dense-rank UNION/EXCEPT source tokens, JSON table, WAL/VFS, B-tree, encoding, planner range-cost, and status-only surfaces';

        return $base;
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>|null
     */
    private static function baseCursor(?array $cursor): ?array
    {
        if ($cursor === null) {
            return null;
        }

        $base = [];
        foreach (['currentToken', 'currentPageTokenNext232', 'acknowledgedCurrentAcksNext232'] as $key) {
            if (array_key_exists($key, $cursor)) {
                $base[$key] = $cursor[$key];
            }
        }

        return $base === [] ? null : $base;
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function barrier(array $plan): array
    {
        $handoff = is_array($plan['currentSourceHandoffNext232'] ?? null) ? $plan['currentSourceHandoffNext232'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $windows = is_array($plan['windows'] ?? null) ? $plan['windows'] : [];
        $limitTrace = is_array($plan['limitTrace'] ?? null) ? $plan['limitTrace'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $currentPageToken = (string) ($handoff['currentPageToken'] ?? '');
        $recursiveTraceToken = self::token([
            'name' => $recursiveQueue['name'] ?? null,
            'columns' => self::stringList($recursiveQueue['columns'] ?? []),
            'operator' => $recursiveQueue['operator'] ?? null,
            'currentTraceCount' => (int) ($recursiveQueue['currentTraceCount'] ?? 0),
            'currentSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'currentEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'currentLimitRemaining' => (int) ($recursiveQueue['currentLimitRemaining'] ?? 0),
            'currentOffsetRemaining' => (int) ($recursiveQueue['currentOffsetRemaining'] ?? 0),
        ]);
        $windowFrameToken = self::token([
            'functions' => self::stringList($windows['functions'] ?? []),
            'currentWindowTerms' => self::windowTermSummary($windows['current'] ?? []),
            'aggregateMetrics' => self::floatList($windows['aggregateMetrics'] ?? []),
            'currentPreLimitCount' => is_array($limitTrace['current'] ?? null) ? (int) ($limitTrace['current']['preLimitCount'] ?? 0) : 0,
            'currentSkippedLabels' => self::stringList($sourceWindow['currentSkippedLabels'] ?? []),
            'currentTruncatedLabels' => self::stringList($sourceWindow['currentTruncatedLabels'] ?? []),
        ]);
        $requiredPromotionAcks = [
            'page:' . $currentPageToken,
            'recursive:' . $recursiveTraceToken,
            'window:' . $windowFrameToken,
        ];
        $barrierToken = self::token([
            'currentPageToken' => $currentPageToken,
            'recursiveTraceToken' => $recursiveTraceToken,
            'windowFrameToken' => $windowFrameToken,
            'requiredPromotionAcks' => $requiredPromotionAcks,
            'nextCursor' => is_array($handoff['nextSourceCursor'] ?? null) ? $handoff['nextSourceCursor'] : [],
        ]);
        $currentLabels = self::stringList($handoff['currentLabels'] ?? []);
        $nextLabels = self::stringList($handoff['nextLabels'] ?? []);

        return [
            'barrierToken' => $barrierToken,
            'currentPageToken' => $currentPageToken,
            'recursiveTraceToken' => $recursiveTraceToken,
            'windowFrameToken' => $windowFrameToken,
            'requiredPromotionAcks' => $requiredPromotionAcks,
            'requiredPromotionAckCount' => count($requiredPromotionAcks),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => self::stringList($handoff['nextOnlyLabels'] ?? []),
            'currentOnlyLabels' => self::stringList($handoff['currentOnlyLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'windowFunctions' => self::stringList($windows['functions'] ?? []),
            'windowMetricCount' => count(self::floatList($windows['aggregateMetrics'] ?? [])),
            'currentPageAckCount' => (int) ($handoff['requiredAckCount'] ?? 0),
            'promotionState' => 'held-until-page-recursive-and-window-acks-match',
            'promotedNextSourceCursor' => is_array($handoff['nextSourceCursor'] ?? null) ? $handoff['nextSourceCursor'] : [],
            'yieldBoundary' => 'compound-recursive-window-next235-promotion-barrier-fences-next-source',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $barrier
     */
    private static function validateCursor(?array $cursor, array $barrier): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'promotionBarrierTokenNext235' => 'barrierToken',
            'currentPageTokenNext235' => 'currentPageToken',
            'recursiveTraceTokenNext235' => 'recursiveTraceToken',
            'windowFrameTokenNext235' => 'windowFrameToken',
        ] as $cursorKey => $barrierKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $barrier[$barrierKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next235 cursor does not match promotion barrier');
            }
        }
        if (!array_key_exists('acknowledgedPromotionAcksNext235', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedPromotionAcksNext235'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next235 promotion acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedPromotionAcksNext235']));
        $required = self::stringList($barrier['requiredPromotionAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next235 promotion acknowledgements do not match required page/recursive/window set');
        }
    }

    /** @param mixed $value @return list<array<string,int|string>> */
    private static function windowTermSummary(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $summary = [];
        foreach ($value as $term) {
            if (!is_array($term)) {
                continue;
            }
            $summary[] = [
                'arm' => (int) ($term['arm'] ?? 0),
                'alias' => (string) ($term['alias'] ?? ''),
                'function' => (string) ($term['function'] ?? ''),
                'partitionCount' => (int) ($term['partitionCount'] ?? 0),
                'orderCount' => (int) ($term['orderCount'] ?? 0),
            ];
        }

        return $summary;
    }

    /** @param mixed $value @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /** @param mixed $value @return list<float> */
    private static function floatList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): float => round((float) $item, 6), $value));
    }

    /** @param array<string,mixed> $payload */
    private static function token(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
