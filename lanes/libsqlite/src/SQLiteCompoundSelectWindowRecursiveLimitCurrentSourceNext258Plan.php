<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext258Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $handoff = self::sourceHandoff($base);
        self::validateCursor($cursor, $handoff);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next258-ready';
        $base['compoundWindowRecursiveSourceHandoffNext258'] = $handoff;
        $base['cursor']['compoundSourceHandoffTokenNext258'] = $handoff['compoundSourceHandoffToken'];
        $base['cursor']['currentPageHighWaterTokenNext258'] = $handoff['currentPageHighWaterToken'];
        $base['cursor']['recursiveQueueDigestNext258'] = $handoff['recursiveQueueDigest'];
        $base['cursor']['requiredSourceHandoffAcksNext258'] = $handoff['requiredSourceHandoffAcks'];
        $base['cursor']['nextExposureNext258'] = $handoff['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-current-source-high-water-next258';
        $base['replanReasons'][] = 'next-source-held-until-current-page-high-water-next258';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-current-source-handoff-next258';
        $base['dependency_closure'] = 'no new support component needed; next258 reuses accepted compound SELECT, recursive LIMIT/OFFSET queue tracing, window frame receipts, next-page admission, and adds a bounded current-page high-water handoff before next-source exposure';
        $base['non_overlap'] = 'next258 extends accepted next254 receipt gating with a final current-page high-water token and recursive queue digest before next-source exposure; it avoids accepted next253/next254 compound/window/recursive LIMIT receipt behavior, row-value/window, trigger, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, VDBE, and suite evidence clusters';

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
            'currentDequeueTokenNext237',
            'acknowledgedCurrentDequeueAcksNext237',
            'spilloverDrainTokenNext240',
            'acknowledgedSpilloverAcksNext240',
            'windowReplayTokenNext243',
            'currentReplaySignatureNext243',
            'acknowledgedReplayTicketsNext243',
            'sourceHandoffTokenNext246',
            'recursiveLimitCursorTokenNext246',
            'currentSourceSignatureNext246',
            'acknowledgedSourceHandoffAcksNext246',
            'nextPageAdmissionTokenNext250',
            'currentSourceResumeTokenNext250',
            'acknowledgedNextPageAdmissionAcksNext250',
            'compoundReceiptTokenNext254',
            'recursiveWindowBoundaryTokenNext254',
            'acknowledgedCompoundReceiptAcksNext254',
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
    private static function sourceHandoff(array $plan): array
    {
        $receipt = is_array($plan['compoundWindowRecursiveLimitReceiptNext254'] ?? null)
            ? $plan['compoundWindowRecursiveLimitReceiptNext254']
            : [];
        $recursive = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $currentFrame = self::frame($receipt['currentPageFrame'] ?? []);
        $nextFrame = self::frame($receipt['nextPageFrame'] ?? []);
        $currentLast = $currentFrame === [] ? null : $currentFrame[count($currentFrame) - 1];
        $nextFirst = $nextFrame[0] ?? null;
        $recursiveDigest = self::token([
            'emitted' => self::strings($recursive['currentEmittedLabels'] ?? []),
            'skipped' => self::strings($recursive['currentSkippedLabels'] ?? []),
            'queueFronts' => self::strings($recursive['currentQueueFronts'] ?? []),
            'limitRemaining' => $recursive['currentLimitRemaining'] ?? null,
            'offsetRemaining' => $recursive['currentOffsetRemaining'] ?? null,
        ]);
        $currentPageHighWaterToken = self::token([
            'currentLast' => $currentLast,
            'currentFrameCount' => count($currentFrame),
            'currentLabels' => self::strings($receipt['currentLabels'] ?? []),
            'currentSourceToken' => (string) ($sourceWindow['currentToken'] ?? ''),
        ]);
        $nextCandidateToken = self::token([
            'nextFirst' => $nextFirst,
            'nextFrameCount' => count($nextFrame),
            'nextLabels' => self::strings($receipt['nextLabels'] ?? []),
            'nextSourceToken' => (string) ($sourceWindow['nextToken'] ?? ''),
        ]);
        $compoundSourceHandoffToken = self::token([
            'receipt' => (string) ($receipt['compoundReceiptToken'] ?? ''),
            'boundary' => (string) ($receipt['recursiveWindowBoundaryToken'] ?? ''),
            'highWater' => $currentPageHighWaterToken,
            'recursiveDigest' => $recursiveDigest,
            'nextCandidate' => $nextCandidateToken,
        ]);
        $required = [
            'receipt:' . (string) ($receipt['compoundReceiptToken'] ?? ''),
            'high-water:' . $currentPageHighWaterToken,
            'recursive-digest:' . $recursiveDigest,
            'handoff:' . $compoundSourceHandoffToken,
        ];

        return [
            'compoundSourceHandoffToken' => $compoundSourceHandoffToken,
            'currentPageHighWaterToken' => $currentPageHighWaterToken,
            'recursiveQueueDigest' => $recursiveDigest,
            'nextCandidateToken' => $nextCandidateToken,
            'requiredSourceHandoffAcks' => $required,
            'requiredSourceHandoffAckCount' => count($required),
            'currentHighWaterLabel' => is_array($currentLast) ? (string) ($currentLast['label'] ?? '') : null,
            'currentHighWaterMetric' => is_array($currentLast) ? ($currentLast['metric'] ?? null) : null,
            'nextCandidateLabel' => is_array($nextFirst) ? (string) ($nextFirst['label'] ?? '') : null,
            'currentFrameCount' => count($currentFrame),
            'nextFrameCount' => count($nextFrame),
            'currentLabels' => self::strings($receipt['currentLabels'] ?? []),
            'nextLabels' => self::strings($receipt['nextLabels'] ?? []),
            'recursiveEmittedCount' => count(self::strings($recursive['currentEmittedLabels'] ?? [])),
            'recursiveSkippedCount' => count(self::strings($recursive['currentSkippedLabels'] ?? [])),
            'labelBoundaryChanged' => self::strings($receipt['currentLabels'] ?? []) !== self::strings($receipt['nextLabels'] ?? []),
            'nextExposure' => 'held-until-current-page-high-water-and-recursive-digest-acks',
            'yieldBoundary' => 'compound-window-recursive-next258-current-source-handoff',
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
        foreach ([
            'compoundSourceHandoffTokenNext258' => 'compoundSourceHandoffToken',
            'currentPageHighWaterTokenNext258' => 'currentPageHighWaterToken',
            'recursiveQueueDigestNext258' => 'recursiveQueueDigest',
        ] as $cursorKey => $handoffKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $handoff[$handoffKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next258 cursor does not match current-source handoff state');
            }
        }
        if (!array_key_exists('acknowledgedSourceHandoffAcksNext258', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedSourceHandoffAcksNext258'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next258 handoff acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedSourceHandoffAcksNext258']);
        $required = self::strings($handoff['requiredSourceHandoffAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next258 handoff acknowledgements do not match required high-water set');
        }
    }

    /** @param mixed $value @return list<array<string,mixed>> */
    private static function frame(mixed $value): array
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

    /** @param mixed $value @return list<string> */
    private static function strings(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /** @param mixed $payload */
    private static function token(mixed $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
