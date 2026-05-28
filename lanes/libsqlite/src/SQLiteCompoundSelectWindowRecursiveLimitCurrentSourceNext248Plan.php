<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext248Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext243Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $promotion = self::nextPromotion($base);
        self::validateCursor($cursor, $promotion);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next248-ready';
        $base['compoundNextSourcePromotionFenceNext248'] = $promotion;
        $base['cursor']['nextPromotionTokenNext248'] = $promotion['nextPromotionToken'];
        $base['cursor']['nextDeltaSignatureNext248'] = $promotion['nextDeltaSignature'];
        $base['cursor']['requiredNextPromotionReceiptsNext248'] = $promotion['requiredNextPromotionReceipts'];
        $base['cursor']['nextPromotionExposureNext248'] = $promotion['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-next-source-promotion-receipt-next248';
        $base['replanReasons'][] = 'next-source-held-until-current-replay-and-next-delta-receipts-match-next248';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-next-source-promotion-next248';
        $base['dependency_closure'] = 'no new support component needed; next248 reuses accepted compound SELECT recursive LIMIT/OFFSET, current window replay tickets, spillover drains, and adds a next-source promotion receipt keyed to the next result delta';
        $base['non_overlap'] = 'next248 extends accepted next243 current-row replay tickets by admitting next-source rows only after next-only/current-only delta receipts match the replay and spillover tokens; it avoids accepted next245 compound behavior, next246/next247 storage/window handoffs, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters';

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
    private static function nextPromotion(array $plan): array
    {
        $replay = is_array($plan['compoundWindowReplayFenceNext243'] ?? null) ? $plan['compoundWindowReplayFenceNext243'] : [];
        $spillover = is_array($plan['compoundFinalPageSpilloverDrainNext240'] ?? null) ? $plan['compoundFinalPageSpilloverDrainNext240'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $currentLabels = self::labels($currentRows);
        $nextLabels = self::labels($nextRows);
        $nextOnlyLabels = self::stringList($replay['nextOnlyLabels'] ?? $sourceWindow['nextOnlyAdmittedLabels'] ?? []);
        $currentOnlyLabels = self::stringList($replay['currentOnlyLabels'] ?? $sourceWindow['currentOnlyAdmittedLabels'] ?? []);
        $nextFrames = self::frames($nextRows);
        $nextDeltaSignature = self::token([
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => $nextOnlyLabels,
            'currentOnlyLabels' => $currentOnlyLabels,
            'nextFrames' => $nextFrames,
        ]);
        $promotionToken = self::token([
            'windowReplayToken' => (string) ($replay['windowReplayToken'] ?? ''),
            'currentReplaySignature' => (string) ($replay['currentReplaySignature'] ?? ''),
            'spilloverDrainToken' => (string) ($spillover['spilloverDrainToken'] ?? ''),
            'nextDeltaSignature' => $nextDeltaSignature,
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
        ]);
        $receipts = [];
        foreach ($nextOnlyLabels as $index => $label) {
            $receipts[] = self::token([
                'promotionToken' => $promotionToken,
                'kind' => 'next-only',
                'ordinal' => $index + 1,
                'label' => $label,
                'nextDeltaSignature' => $nextDeltaSignature,
            ]);
        }
        foreach ($currentOnlyLabels as $index => $label) {
            $receipts[] = self::token([
                'promotionToken' => $promotionToken,
                'kind' => 'current-only',
                'ordinal' => $index + 1,
                'label' => $label,
                'nextDeltaSignature' => $nextDeltaSignature,
            ]);
        }

        return [
            'nextPromotionToken' => $promotionToken,
            'nextDeltaSignature' => $nextDeltaSignature,
            'requiredNextPromotionReceipts' => $receipts,
            'requiredNextPromotionReceiptCount' => count($receipts),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => $nextOnlyLabels,
            'currentOnlyLabels' => $currentOnlyLabels,
            'nextFrames' => $nextFrames,
            'windowReplayToken' => (string) ($replay['windowReplayToken'] ?? ''),
            'currentReplaySignature' => (string) ($replay['currentReplaySignature'] ?? ''),
            'spilloverDrainToken' => (string) ($spillover['spilloverDrainToken'] ?? ''),
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'nextExposure' => 'held-until-next-source-promotion-receipts-match',
            'yieldBoundary' => 'compound-window-recursive-next248-next-source-promotion-fence',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $promotion
     */
    private static function validateCursor(?array $cursor, array $promotion): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'nextPromotionTokenNext248' => 'nextPromotionToken',
            'nextDeltaSignatureNext248' => 'nextDeltaSignature',
        ] as $cursorKey => $promotionKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $promotion[$promotionKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next248 cursor does not match next-source promotion receipt');
            }
        }
        if (!array_key_exists('acknowledgedNextPromotionReceiptsNext248', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedNextPromotionReceiptsNext248'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next248 promotion receipts must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $receipt): string => (string) $receipt, $cursor['acknowledgedNextPromotionReceiptsNext248']));
        $required = self::stringList($promotion['requiredNextPromotionReceipts'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next248 promotion receipts do not match next-source delta set');
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
                'metric' => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null,
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
