<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext256Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext248Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $fence = self::currentLimitResumeFence($base);
        self::validateCursor($cursor, $fence);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next256-ready';
        $base['compoundCurrentLimitResumeFenceNext256'] = $fence;
        $base['cursor']['currentLimitResumeTokenNext256'] = $fence['currentLimitResumeToken'];
        $base['cursor']['currentLimitPageSignatureNext256'] = $fence['currentLimitPageSignature'];
        $base['cursor']['requiredCurrentLimitResumeReceiptsNext256'] = $fence['requiredCurrentLimitResumeReceipts'];
        $base['cursor']['currentLimitResumeExposureNext256'] = $fence['currentExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-current-limit-resume-receipt-next256';
        $base['replanReasons'][] = 'next-source-held-until-current-limit-page-and-recursive-exhaustion-match-next256';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-current-limit-resume-next256';
        $base['dependency_closure'] = 'no new support component needed; next256 reuses parser-level compound SELECT, recursive CTE LIMIT/OFFSET tracing, window frame metrics, accepted next248 promotion receipts, and adds a current final-page resume receipt';
        $base['non_overlap'] = 'next256 extends accepted next248 next-source promotion receipts with a current final LIMIT page resume fence keyed to recursive queue exhaustion and window frame metrics; it avoids accepted next251/next252 compound/window/recursive LIMIT behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters';

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
            'nextPromotionTokenNext248',
            'nextDeltaSignatureNext248',
            'acknowledgedNextPromotionReceiptsNext248',
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
    private static function currentLimitResumeFence(array $plan): array
    {
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $promotion = is_array($plan['compoundNextSourcePromotionFenceNext248'] ?? null) ? $plan['compoundNextSourcePromotionFenceNext248'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $currentFrames = self::frames($currentRows);
        $nextFrames = self::frames($nextRows);
        $currentLabels = self::labels($currentRows);
        $currentLimitPageSignature = self::token([
            'operators' => self::stringList($compound['operators'] ?? []),
            'limit' => $compound['limit'] ?? null,
            'offset' => $compound['offset'] ?? 0,
            'currentLabels' => $currentLabels,
            'currentFrames' => $currentFrames,
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'recursiveLimitRemaining' => $recursiveQueue['currentLimitRemaining'] ?? null,
            'recursiveOffsetRemaining' => $recursiveQueue['currentOffsetRemaining'] ?? null,
        ]);
        $resumeToken = self::token([
            'currentLimitPageSignature' => $currentLimitPageSignature,
            'nextPromotionToken' => (string) ($promotion['nextPromotionToken'] ?? ''),
            'nextDeltaSignature' => (string) ($promotion['nextDeltaSignature'] ?? ''),
            'windowReplayToken' => (string) ($promotion['windowReplayToken'] ?? ''),
            'spilloverDrainToken' => (string) ($promotion['spilloverDrainToken'] ?? ''),
        ]);

        $receipts = [];
        foreach ($currentFrames as $frame) {
            $receipts[] = self::token([
                'resumeToken' => $resumeToken,
                'kind' => 'current-final-limit-row',
                'ordinal' => $frame['ordinal'],
                'id' => $frame['id'],
                'label' => $frame['label'],
                'metric' => $frame['metric'],
                'currentLimitPageSignature' => $currentLimitPageSignature,
            ]);
        }
        $receipts[] = self::token([
            'resumeToken' => $resumeToken,
            'kind' => 'recursive-limit-exhausted',
            'emittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'skippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'remaining' => [
                'limit' => $recursiveQueue['currentLimitRemaining'] ?? null,
                'offset' => $recursiveQueue['currentOffsetRemaining'] ?? null,
            ],
        ]);

        return [
            'currentLimitResumeToken' => $resumeToken,
            'currentLimitPageSignature' => $currentLimitPageSignature,
            'requiredCurrentLimitResumeReceipts' => $receipts,
            'requiredCurrentLimitResumeReceiptCount' => count($receipts),
            'currentLabels' => $currentLabels,
            'nextLabels' => self::labels($nextRows),
            'currentFrames' => $currentFrames,
            'nextFrames' => $nextFrames,
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'recursiveLimitRemaining' => $recursiveQueue['currentLimitRemaining'] ?? null,
            'recursiveOffsetRemaining' => $recursiveQueue['currentOffsetRemaining'] ?? null,
            'nextPromotionToken' => (string) ($promotion['nextPromotionToken'] ?? ''),
            'nextDeltaSignature' => (string) ($promotion['nextDeltaSignature'] ?? ''),
            'currentExposure' => 'held-until-current-limit-resume-receipts-match',
            'yieldBoundary' => 'compound-window-recursive-next256-current-limit-resume-fence',
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
            'currentLimitResumeTokenNext256' => 'currentLimitResumeToken',
            'currentLimitPageSignatureNext256' => 'currentLimitPageSignature',
        ] as $cursorKey => $fenceKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $fence[$fenceKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next256 cursor does not match current LIMIT resume receipt');
            }
        }
        if (!array_key_exists('acknowledgedCurrentLimitResumeReceiptsNext256', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCurrentLimitResumeReceiptsNext256'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next256 resume receipts must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $receipt): string => (string) $receipt, $cursor['acknowledgedCurrentLimitResumeReceiptsNext256']));
        $required = self::stringList($fence['requiredCurrentLimitResumeReceipts'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next256 resume receipts do not match current final LIMIT page');
        }
    }

    /** @param mixed $value @return list<array<string,mixed>> */
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

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''), $rows));
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function frames(array $rows): array
    {
        $frames = [];
        foreach ($rows as $index => $row) {
            $frames[] = [
                'ordinal' => $index + 1,
                'id' => $row['id'] ?? $row['option_id'] ?? null,
                'label' => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''),
                'metric' => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? $row['bucket'] ?? null,
            ];
        }

        return $frames;
    }

    /** @param mixed $value @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    /** @param array<string,mixed> $payload */
    private static function token(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
