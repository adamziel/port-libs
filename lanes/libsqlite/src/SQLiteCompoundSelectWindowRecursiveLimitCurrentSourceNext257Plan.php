<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext257Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext252Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $checkpoint = self::sourceSwitchCheckpoint($base);
        self::validateCursor($cursor, $checkpoint);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next257-ready';
        $base['compoundSourceSwitchCheckpointNext257'] = $checkpoint;
        $base['cursor']['sourceSwitchCheckpointTokenNext257'] = $checkpoint['sourceSwitchCheckpointToken'];
        $base['cursor']['sourceSwitchDeltaTokenNext257'] = $checkpoint['sourceSwitchDeltaToken'];
        $base['cursor']['requiredSourceSwitchReceiptsNext257'] = $checkpoint['requiredSourceSwitchReceipts'];
        $base['cursor']['nextExposureNext257'] = $checkpoint['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-source-switch-checkpoint-next257';
        $base['replanReasons'][] = 'next-source-held-until-ordered-current-window-checkpoint-next257';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-source-switch-checkpoint-next257';
        $base['dependency_closure'] = 'no new support component needed; next257 reuses accepted compound SELECT recursive LIMIT/OFFSET execution, next252 final-page watermarks, recursive lineage tokens, window metric tokens, and adds a source-switch checkpoint receipt fence';
        $base['non_overlap'] = 'next257 extends accepted next252 final-page yield watermarks with an ordered current-to-next source-switch checkpoint; it avoids accepted batch219 next251/next252 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, VDBE, and suite evidence clusters';

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
            'finalPageYieldWatermarkTokenNext252',
            'currentFinalPageTokenNext252',
            'nextFinalPageTokenNext252',
            'acknowledgedFinalPageYieldAcksNext252',
        ] as $key) {
            if (array_key_exists($key, $cursor)) {
                $base[$key] = $cursor[$key];
            }
        }

        return $base === [] ? null : $base;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private static function sourceSwitchCheckpoint(array $plan): array
    {
        $watermark = is_array($plan['compoundFinalPageYieldWatermarkNext252'] ?? null) ? $plan['compoundFinalPageYieldWatermarkNext252'] : [];
        $currentRows = self::rows($watermark['currentFinalPageRows'] ?? []);
        $nextRows = self::rows($watermark['nextFinalPageRows'] ?? []);
        $nextOnly = self::strings($watermark['nextOnlyLabels'] ?? []);
        $currentOnly = self::strings($watermark['currentOnlyLabels'] ?? []);
        $orderedCurrent = self::orderedPage($currentRows);
        $orderedNext = self::orderedPage($nextRows);
        $deltaToken = self::token([
            'nextOnly' => $nextOnly,
            'currentOnly' => $currentOnly,
            'orderedCurrent' => $orderedCurrent,
            'orderedNext' => $orderedNext,
        ]);
        $checkpointToken = self::token([
            'watermarkToken' => (string) ($watermark['finalPageYieldWatermarkToken'] ?? ''),
            'currentFinalPageToken' => (string) ($watermark['currentFinalPageToken'] ?? ''),
            'nextFinalPageToken' => (string) ($watermark['nextFinalPageToken'] ?? ''),
            'promotionEpochToken' => (string) ($watermark['promotionEpochToken'] ?? ''),
            'recursiveLineageToken' => (string) ($watermark['recursiveLineageToken'] ?? ''),
            'windowMetricToken' => (string) ($watermark['windowMetricToken'] ?? ''),
            'sourceSwitchDeltaToken' => $deltaToken,
        ]);
        $receipts = [];
        foreach ($orderedCurrent as $row) {
            $receipts[] = self::receipt($checkpointToken, 'current-page', $row);
        }
        foreach ($orderedNext as $row) {
            $receipts[] = self::receipt($checkpointToken, 'next-page', $row);
        }
        foreach ($nextOnly as $index => $label) {
            $receipts[] = self::token([
                'checkpoint' => $checkpointToken,
                'kind' => 'next-only-delta',
                'ordinal' => $index + 1,
                'label' => $label,
            ]);
        }
        foreach ($currentOnly as $index => $label) {
            $receipts[] = self::token([
                'checkpoint' => $checkpointToken,
                'kind' => 'current-only-delta',
                'ordinal' => $index + 1,
                'label' => $label,
            ]);
        }

        return [
            'sourceSwitchCheckpointToken' => $checkpointToken,
            'sourceSwitchDeltaToken' => $deltaToken,
            'requiredSourceSwitchReceipts' => $receipts,
            'requiredSourceSwitchReceiptCount' => count($receipts),
            'orderedCurrentPage' => $orderedCurrent,
            'orderedNextPage' => $orderedNext,
            'nextOnlyLabels' => $nextOnly,
            'currentOnlyLabels' => $currentOnly,
            'finalPageYieldWatermarkToken' => (string) ($watermark['finalPageYieldWatermarkToken'] ?? ''),
            'currentFinalPageToken' => (string) ($watermark['currentFinalPageToken'] ?? ''),
            'nextFinalPageToken' => (string) ($watermark['nextFinalPageToken'] ?? ''),
            'recursiveLineageToken' => (string) ($watermark['recursiveLineageToken'] ?? ''),
            'windowMetricToken' => (string) ($watermark['windowMetricToken'] ?? ''),
            'currentRowCount' => count($orderedCurrent),
            'nextRowCount' => count($orderedNext),
            'deltaLabelCount' => count($nextOnly) + count($currentOnly),
            'yieldBoundary' => 'compound-window-recursive-next257-source-switch-checkpoint',
            'nextExposure' => 'held-until-source-switch-checkpoint-receipts-match',
        ];
    }

    /** @param array<string,mixed>|null $cursor @param array<string,mixed> $checkpoint */
    private static function validateCursor(?array $cursor, array $checkpoint): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'sourceSwitchCheckpointTokenNext257' => 'sourceSwitchCheckpointToken',
            'sourceSwitchDeltaTokenNext257' => 'sourceSwitchDeltaToken',
        ] as $cursorKey => $checkpointKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $checkpoint[$checkpointKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next257 cursor does not match source-switch checkpoint');
            }
        }
        if (!array_key_exists('acknowledgedSourceSwitchReceiptsNext257', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedSourceSwitchReceiptsNext257'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next257 source-switch receipts must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedSourceSwitchReceiptsNext257']);
        $required = self::strings($checkpoint['requiredSourceSwitchReceipts'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next257 source-switch receipts do not match ordered current/next page set');
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
    private static function orderedPage(array $rows): array
    {
        $ordered = [];
        foreach ($rows as $index => $row) {
            $ordered[] = [
                'ordinal' => $index + 1,
                'id' => $row['id'] ?? $row['option_id'] ?? null,
                'label' => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''),
                'metric' => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null,
            ];
        }

        return $ordered;
    }

    /** @param array<string,mixed> $row */
    private static function receipt(string $checkpointToken, string $kind, array $row): string
    {
        return self::token([
            'checkpoint' => $checkpointToken,
            'kind' => $kind,
            'ordinal' => $row['ordinal'] ?? null,
            'label' => $row['label'] ?? '',
            'metric' => $row['metric'] ?? null,
        ]);
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
