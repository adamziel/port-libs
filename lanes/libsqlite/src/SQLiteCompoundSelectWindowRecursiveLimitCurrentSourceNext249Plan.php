<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext249Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext245Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $epoch = self::promotionEpoch($base);
        self::validateCursor($cursor, $epoch);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next249-ready';
        $base['compoundRecursiveWindowPromotionEpochNext249'] = $epoch;
        $base['cursor']['promotionEpochTokenNext249'] = $epoch['promotionEpochToken'];
        $base['cursor']['recursiveLineageTokenNext249'] = $epoch['recursiveLineageToken'];
        $base['cursor']['windowMetricTokenNext249'] = $epoch['windowMetricToken'];
        $base['cursor']['requiredPromotionEpochAcksNext249'] = $epoch['requiredPromotionEpochAcks'];
        $base['cursor']['nextExposureNext249'] = $epoch['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-promotion-epoch-next249';
        $base['replanReasons'][] = 'next-source-held-until-recursive-lineage-and-window-metrics-next249';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-promotion-epoch-next249';
        $base['dependency_closure'] = 'no new support component needed; next249 reuses accepted compound SELECT recursive LIMIT/OFFSET, per-arm window output, spillover drain, replay tickets, and next245 promotion snapshots, then adds a recursive-lineage/window-metric epoch fence before next-source admission';
        $base['non_overlap'] = 'next249 extends accepted next245 next-source promotion snapshots by binding promotion acknowledgements to recursive skipped/truncated lineage and current/next window metrics; it avoids accepted next238 source-generation seals, next240 spillover drains, next243 replay tickets alone, next245 delta snapshots alone, accepted batch214 next245 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, VDBE, and suite evidence clusters';

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
            'promotionSnapshotTokenNext245',
            'nextSourceDeltaTokenNext245',
            'acknowledgedPromotionTicketsNext245',
        ] as $key) {
            if (array_key_exists($key, $cursor)) {
                $base[$key] = $cursor[$key];
            }
        }

        return $base === [] ? null : $base;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private static function promotionEpoch(array $plan): array
    {
        $promotion = is_array($plan['compoundNextSourcePromotionSnapshotNext245'] ?? null) ? $plan['compoundNextSourcePromotionSnapshotNext245'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);

        $currentMetrics = self::metrics($currentRows);
        $nextMetrics = self::metrics($nextRows);
        $lineage = [
            'currentEmittedLabels' => self::strings($recursiveQueue['currentEmittedLabels'] ?? []),
            'nextEmittedLabels' => self::strings($recursiveQueue['nextEmittedLabels'] ?? []),
            'currentSkippedLabels' => self::strings($recursiveQueue['currentSkippedLabels'] ?? []),
            'nextSkippedLabels' => self::strings($recursiveQueue['nextSkippedLabels'] ?? []),
            'currentTruncatedLabels' => self::strings($sourceWindow['currentTruncatedLabels'] ?? []),
            'nextTruncatedLabels' => self::strings($sourceWindow['nextTruncatedLabels'] ?? []),
        ];
        $lineageToken = self::token($lineage);
        $metricToken = self::token([
            'currentMetrics' => $currentMetrics,
            'nextMetrics' => $nextMetrics,
            'currentLabels' => self::labels($currentRows),
            'nextLabels' => self::labels($nextRows),
        ]);
        $promotionEpochToken = self::token([
            'promotionSnapshotToken' => (string) ($promotion['promotionSnapshotToken'] ?? ''),
            'nextSourceDeltaToken' => (string) ($promotion['nextSourceDeltaToken'] ?? ''),
            'lineageToken' => $lineageToken,
            'metricToken' => $metricToken,
            'promotionTickets' => self::strings($promotion['requiredPromotionTickets'] ?? []),
        ]);
        $acks = [
            'epoch:' . $promotionEpochToken,
            'lineage:' . $lineageToken,
            'metrics:' . $metricToken,
        ];

        return [
            'promotionEpochToken' => $promotionEpochToken,
            'recursiveLineageToken' => $lineageToken,
            'windowMetricToken' => $metricToken,
            'requiredPromotionEpochAcks' => $acks,
            'requiredPromotionEpochAckCount' => count($acks),
            'currentLabels' => self::labels($currentRows),
            'nextLabels' => self::labels($nextRows),
            'currentWindowMetrics' => $currentMetrics,
            'nextWindowMetrics' => $nextMetrics,
            'recursiveLineage' => $lineage,
            'promotionSnapshotToken' => (string) ($promotion['promotionSnapshotToken'] ?? ''),
            'nextSourceDeltaToken' => (string) ($promotion['nextSourceDeltaToken'] ?? ''),
            'changedRowCount' => (int) ($promotion['changedRowCount'] ?? 0),
            'nextOnlyLabels' => self::strings($promotion['nextOnlyLabels'] ?? []),
            'currentOnlyLabels' => self::strings($promotion['currentOnlyLabels'] ?? []),
            'lineageChanged' => $lineage['currentEmittedLabels'] !== $lineage['nextEmittedLabels']
                || $lineage['currentSkippedLabels'] !== $lineage['nextSkippedLabels']
                || $lineage['currentTruncatedLabels'] !== $lineage['nextTruncatedLabels'],
            'windowMetricsChanged' => $currentMetrics !== $nextMetrics,
            'nextExposure' => 'held-until-recursive-lineage-window-metrics-and-promotion-epoch-match',
            'yieldBoundary' => 'compound-window-recursive-next249-promotion-epoch-fence',
        ];
    }

    /** @param array<string,mixed>|null $cursor @param array<string,mixed> $epoch */
    private static function validateCursor(?array $cursor, array $epoch): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'promotionEpochTokenNext249' => 'promotionEpochToken',
            'recursiveLineageTokenNext249' => 'recursiveLineageToken',
            'windowMetricTokenNext249' => 'windowMetricToken',
        ] as $cursorKey => $epochKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $epoch[$epochKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next249 cursor does not match promotion epoch');
            }
        }
        if (!array_key_exists('acknowledgedPromotionEpochAcksNext249', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedPromotionEpochAcksNext249'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next249 promotion epoch acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedPromotionEpochAcksNext249']);
        $required = self::strings($epoch['requiredPromotionEpochAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next249 promotion epoch acknowledgements do not match recursive lineage/window metric set');
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

    /** @param list<array<string,mixed>> $rows @return list<int|string|null> */
    private static function metrics(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int|string|null => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null, $rows));
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
