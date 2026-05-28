<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext242Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext238Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $fence = self::commitFence($base);
        self::validateCursor($cursor, $fence);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next242-ready';
        $base['recursiveLimitWindowCommitFenceNext242'] = $fence;
        $base['cursor']['commitFenceTokenNext242'] = $fence['commitFenceToken'];
        $base['cursor']['recursiveQueueTokenNext242'] = $fence['recursiveQueueToken'];
        $base['cursor']['windowOutputTokenNext242'] = $fence['windowOutputToken'];
        $base['cursor']['finalPageTokenNext242'] = $fence['finalPageToken'];
        $base['cursor']['requiredCommitFenceAcksNext242'] = $fence['requiredCommitFenceAcks'];
        $base['cursor']['nextSourceCursorNext242'] = $fence['nextSourceCursor'];
        $base['replanReasons'][] = 'compound-recursive-limit-window-commit-fence-next242';
        $base['replanReasons'][] = 'recursive-queue-window-final-page-acks-next242';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-commit-fence-current-source-next242';
        $base['dependency_closure'] = 'no new support component needed; next242 reuses native SELECT SQL compound, recursive queue LIMIT/OFFSET, window row-array output, and next238 source-generation seal machinery';
        $base['non_overlap'] = 'next242 extends accepted next238 source-generation seal with a commit fence over recursive queue, window output, and final page acknowledgements; it avoids accepted next235 promotion barriers, next238 source-generation seals, batch209 next238 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, and encoding clusters';

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
        foreach ([
            'currentToken',
            'currentPageTokenNext232',
            'acknowledgedCurrentAcksNext232',
            'promotionBarrierTokenNext235',
            'currentPageTokenNext235',
            'recursiveTraceTokenNext235',
            'windowFrameTokenNext235',
            'acknowledgedPromotionAcksNext235',
            'sourceGenerationTokenNext238',
            'finalBoundaryTokenNext238',
            'acknowledgedSourceGenerationAcksNext238',
        ] as $key) {
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
    private static function commitFence(array $plan): array
    {
        $seal = is_array($plan['sourceGenerationSealNext238'] ?? null) ? $plan['sourceGenerationSealNext238'] : [];
        $queue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $windows = is_array($plan['windows'] ?? null) ? $plan['windows'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $limitTrace = is_array($plan['limitTrace'] ?? null) ? $plan['limitTrace'] : [];
        $cursor = is_array($plan['cursor'] ?? null) ? $plan['cursor'] : [];

        $currentLabels = self::stringList($sourceWindow['currentAdmittedLabels'] ?? []);
        $nextLabels = self::stringList($sourceWindow['nextAdmittedLabels'] ?? []);
        $recursiveQueueToken = self::token([
            'name' => $queue['name'] ?? null,
            'operator' => $queue['operator'] ?? null,
            'columns' => self::stringList($queue['columns'] ?? []),
            'currentEmittedLabels' => self::stringList($queue['currentEmittedLabels'] ?? []),
            'nextEmittedLabels' => self::stringList($queue['nextEmittedLabels'] ?? []),
            'currentSkippedLabels' => self::stringList($queue['currentSkippedLabels'] ?? []),
            'nextSkippedLabels' => self::stringList($queue['nextSkippedLabels'] ?? []),
            'currentLimitRemaining' => (int) ($queue['currentLimitRemaining'] ?? 0),
            'nextLimitRemaining' => (int) ($queue['nextLimitRemaining'] ?? 0),
            'currentOffsetRemaining' => (int) ($queue['currentOffsetRemaining'] ?? 0),
            'nextOffsetRemaining' => (int) ($queue['nextOffsetRemaining'] ?? 0),
        ]);
        $windowOutputToken = self::token([
            'functions' => self::stringList($windows['functions'] ?? []),
            'currentTerms' => self::windowTermSummary($windows['current'] ?? []),
            'nextTerms' => self::windowTermSummary($windows['next'] ?? []),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'currentRows' => self::rowTokenPayload($plan['currentRows'] ?? []),
            'nextRows' => self::rowTokenPayload($plan['nextRows'] ?? []),
        ]);
        $finalPageToken = self::token([
            'sourceGenerationToken' => (string) ($seal['sourceGenerationToken'] ?? ''),
            'finalBoundaryToken' => (string) ($seal['finalBoundaryToken'] ?? ''),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'currentLimit' => self::traceInt($limitTrace, 'current', 'limit'),
            'currentOffset' => self::traceInt($limitTrace, 'current', 'offset'),
            'nextLimit' => self::traceInt($limitTrace, 'next', 'limit'),
            'nextOffset' => self::traceInt($limitTrace, 'next', 'offset'),
        ]);
        $requiredAcks = [
            'recursive:' . $recursiveQueueToken,
            'window:' . $windowOutputToken,
            'final-page:' . $finalPageToken,
        ];
        $commitFenceToken = self::token([
            'recursiveQueueToken' => $recursiveQueueToken,
            'windowOutputToken' => $windowOutputToken,
            'finalPageToken' => $finalPageToken,
            'requiredCommitFenceAcks' => $requiredAcks,
            'nextSourceCursor' => is_array($cursor['generationNextSourceCursorNext238'] ?? null) ? $cursor['generationNextSourceCursorNext238'] : [],
        ]);

        return [
            'commitFenceToken' => $commitFenceToken,
            'recursiveQueueToken' => $recursiveQueueToken,
            'windowOutputToken' => $windowOutputToken,
            'finalPageToken' => $finalPageToken,
            'requiredCommitFenceAcks' => $requiredAcks,
            'requiredCommitFenceAckCount' => count($requiredAcks),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => self::stringList($seal['nextOnlyLabels'] ?? []),
            'currentOnlyLabels' => self::stringList($seal['currentOnlyLabels'] ?? []),
            'recursiveEmittedLabels' => self::stringList($queue['currentEmittedLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($queue['currentSkippedLabels'] ?? []),
            'windowFunctions' => self::stringList($windows['functions'] ?? []),
            'sourceGenerationToken' => (string) ($seal['sourceGenerationToken'] ?? ''),
            'finalBoundaryToken' => (string) ($seal['finalBoundaryToken'] ?? ''),
            'nextSourceCursor' => is_array($cursor['generationNextSourceCursorNext238'] ?? null) ? $cursor['generationNextSourceCursorNext238'] : [],
            'admissionState' => 'held-until-recursive-window-and-final-page-acks-match',
            'yieldBoundary' => 'compound-recursive-window-next242-commit-fence-fences-next-source',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $fence
     */
    private static function validateCursor(?array $cursor, array $fence): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'commitFenceTokenNext242' => 'commitFenceToken',
            'recursiveQueueTokenNext242' => 'recursiveQueueToken',
            'windowOutputTokenNext242' => 'windowOutputToken',
            'finalPageTokenNext242' => 'finalPageToken',
        ] as $cursorKey => $fenceKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $fence[$fenceKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next242 cursor does not match commit fence');
            }
        }
        if (!array_key_exists('acknowledgedCommitFenceAcksNext242', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCommitFenceAcksNext242'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next242 commit-fence acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedCommitFenceAcksNext242']));
        $required = self::stringList($fence['requiredCommitFenceAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next242 commit-fence acknowledgements do not match required recursive/window/final-page set');
        }
    }

    /** @param mixed $value @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
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

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rowTokenPayload(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_map(static function (mixed $row): array {
            if (!is_array($row)) {
                return ['value' => $row];
            }

            return [
                'id' => $row['id'] ?? null,
                'label' => $row['label'] ?? null,
                'rn' => $row['rn'] ?? null,
            ];
        }, $rows));
    }

    /** @param array<string,mixed> $trace */
    private static function traceInt(array $trace, string $side, string $key): int
    {
        $sideTrace = is_array($trace[$side] ?? null) ? $trace[$side] : [];

        return (int) ($sideTrace[$key] ?? 0);
    }

    /** @param mixed $payload */
    private static function token(mixed $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
