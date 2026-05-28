<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext252Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext249Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $watermark = self::yieldWatermark($base);
        self::validateCursor($cursor, $watermark);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next252-ready';
        $base['compoundFinalPageYieldWatermarkNext252'] = $watermark;
        $base['cursor']['finalPageYieldWatermarkTokenNext252'] = $watermark['finalPageYieldWatermarkToken'];
        $base['cursor']['currentFinalPageTokenNext252'] = $watermark['currentFinalPageToken'];
        $base['cursor']['nextFinalPageTokenNext252'] = $watermark['nextFinalPageToken'];
        $base['cursor']['requiredFinalPageYieldAcksNext252'] = $watermark['requiredFinalPageYieldAcks'];
        $base['cursor']['nextExposureNext252'] = $watermark['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-final-page-yield-watermark-next252';
        $base['replanReasons'][] = 'next-source-held-until-final-page-yield-watermark-acks-next252';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-final-page-yield-watermark-next252';
        $base['dependency_closure'] = 'no new support component needed; next252 reuses accepted compound SELECT recursive LIMIT/OFFSET, next249 promotion epochs, current/next final pages, recursive lineage tokens, and window metric tokens';
        $base['non_overlap'] = 'next252 extends accepted next249 promotion epochs with a final-page yield watermark that binds current and next limited rows to their recursive/window tokens before next-source exposure; it avoids next248 receipt-only promotion, next249 epoch-only admission, accepted batch217 next249 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, VDBE, and suite evidence clusters';

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
            'promotionEpochTokenNext249',
            'recursiveLineageTokenNext249',
            'windowMetricTokenNext249',
            'acknowledgedPromotionEpochAcksNext249',
        ] as $key) {
            if (array_key_exists($key, $cursor)) {
                $base[$key] = $cursor[$key];
            }
        }

        return $base === [] ? null : $base;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private static function yieldWatermark(array $plan): array
    {
        $epoch = is_array($plan['compoundRecursiveWindowPromotionEpochNext249'] ?? null) ? $plan['compoundRecursiveWindowPromotionEpochNext249'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $currentFinalPage = self::pageRows($currentRows);
        $nextFinalPage = self::pageRows($nextRows);
        $currentToken = self::token([
            'rows' => $currentFinalPage,
            'labels' => self::strings($epoch['currentLabels'] ?? []),
            'metrics' => $epoch['currentWindowMetrics'] ?? [],
        ]);
        $nextToken = self::token([
            'rows' => $nextFinalPage,
            'labels' => self::strings($epoch['nextLabels'] ?? []),
            'metrics' => $epoch['nextWindowMetrics'] ?? [],
        ]);
        $watermarkToken = self::token([
            'promotionEpochToken' => (string) ($epoch['promotionEpochToken'] ?? ''),
            'recursiveLineageToken' => (string) ($epoch['recursiveLineageToken'] ?? ''),
            'windowMetricToken' => (string) ($epoch['windowMetricToken'] ?? ''),
            'currentFinalPageToken' => $currentToken,
            'nextFinalPageToken' => $nextToken,
            'nextOnlyLabels' => self::strings($epoch['nextOnlyLabels'] ?? []),
            'currentOnlyLabels' => self::strings($epoch['currentOnlyLabels'] ?? []),
        ]);
        $acks = [
            'watermark:' . $watermarkToken,
            'current-page:' . $currentToken,
            'next-page:' . $nextToken,
            'lineage:' . (string) ($epoch['recursiveLineageToken'] ?? ''),
            'metrics:' . (string) ($epoch['windowMetricToken'] ?? ''),
        ];

        return [
            'finalPageYieldWatermarkToken' => $watermarkToken,
            'currentFinalPageToken' => $currentToken,
            'nextFinalPageToken' => $nextToken,
            'requiredFinalPageYieldAcks' => $acks,
            'requiredFinalPageYieldAckCount' => count($acks),
            'currentFinalPageRows' => $currentFinalPage,
            'nextFinalPageRows' => $nextFinalPage,
            'currentLabels' => self::strings($epoch['currentLabels'] ?? []),
            'nextLabels' => self::strings($epoch['nextLabels'] ?? []),
            'nextOnlyLabels' => self::strings($epoch['nextOnlyLabels'] ?? []),
            'currentOnlyLabels' => self::strings($epoch['currentOnlyLabels'] ?? []),
            'promotionEpochToken' => (string) ($epoch['promotionEpochToken'] ?? ''),
            'recursiveLineageToken' => (string) ($epoch['recursiveLineageToken'] ?? ''),
            'windowMetricToken' => (string) ($epoch['windowMetricToken'] ?? ''),
            'currentPageChanged' => $currentToken !== $nextToken,
            'yieldBoundary' => 'compound-window-recursive-next252-final-page-yield-watermark',
            'nextExposure' => 'held-until-final-page-yield-watermark-acks-match',
        ];
    }

    /** @param array<string,mixed>|null $cursor @param array<string,mixed> $watermark */
    private static function validateCursor(?array $cursor, array $watermark): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'finalPageYieldWatermarkTokenNext252' => 'finalPageYieldWatermarkToken',
            'currentFinalPageTokenNext252' => 'currentFinalPageToken',
            'nextFinalPageTokenNext252' => 'nextFinalPageToken',
        ] as $cursorKey => $watermarkKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $watermark[$watermarkKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next252 cursor does not match final-page yield watermark');
            }
        }
        if (!array_key_exists('acknowledgedFinalPageYieldAcksNext252', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedFinalPageYieldAcksNext252'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next252 final-page yield acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedFinalPageYieldAcksNext252']);
        $required = self::strings($watermark['requiredFinalPageYieldAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next252 final-page yield acknowledgements do not match current/next page tokens');
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

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function pageRows(array $rows): array
    {
        $page = [];
        foreach ($rows as $index => $row) {
            $page[] = [
                'ordinal' => $index + 1,
                'id' => $row['id'] ?? $row['option_id'] ?? null,
                'label' => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''),
                'metric' => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null,
            ];
        }

        return $page;
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
