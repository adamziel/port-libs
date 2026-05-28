<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext253Plan
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
        $admission = self::currentSourceAdmission($base);
        self::validateCursor($cursor, $admission);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next253-ready';
        $base['compoundCurrentSourceAdmissionNext253'] = $admission;
        $base['cursor']['currentSourceAdmissionTokenNext253'] = $admission['currentSourceAdmissionToken'];
        $base['cursor']['currentRecursiveLimitTokenNext253'] = $admission['currentRecursiveLimitToken'];
        $base['cursor']['currentWindowPageTokenNext253'] = $admission['currentWindowPageToken'];
        $base['cursor']['requiredCurrentSourceAcksNext253'] = $admission['requiredCurrentSourceAcks'];
        $base['cursor']['currentExposureNext253'] = $admission['currentExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-current-source-admission-next253';
        $base['replanReasons'][] = 'current-source-held-until-recursive-limit-window-page-acks-next253';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-current-source-admission-next253';
        $base['dependency_closure'] = 'no new support component needed; next253 reuses accepted compound SELECT recursive LIMIT/OFFSET, window output, and next249 promotion epochs, then adds a current-source admission fence before next-source promotion';
        $base['non_overlap'] = 'next253 extends accepted next249 promotion epoch handling by fencing current-source exposure with recursive LIMIT/OFFSET lineage, final page labels, and window metric tickets before any next-source promotion acknowledgement; it avoids accepted next249 epoch-only behavior, next250/next251 row-value/window behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, VDBE, and suite evidence clusters';

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
    private static function currentSourceAdmission(array $plan): array
    {
        $epoch = is_array($plan['compoundRecursiveWindowPromotionEpochNext249'] ?? null) ? $plan['compoundRecursiveWindowPromotionEpochNext249'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $finalPage = self::frames($currentRows);
        $currentLabels = self::labels($currentRows);
        $currentMetrics = self::metrics($currentRows);
        $recursiveLimit = [
            'emittedLabels' => self::strings($recursiveQueue['currentEmittedLabels'] ?? []),
            'skippedLabels' => self::strings($recursiveQueue['currentSkippedLabels'] ?? []),
            'truncatedLabels' => self::strings($sourceWindow['currentTruncatedLabels'] ?? []),
            'limit' => self::scalar($sourceWindow['currentLimit'] ?? $sourceWindow['limit'] ?? null),
            'offset' => self::scalar($sourceWindow['currentOffset'] ?? $sourceWindow['offset'] ?? null),
        ];
        $recursiveLimitToken = self::token($recursiveLimit);
        $windowPageToken = self::token([
            'finalPage' => $finalPage,
            'currentLabels' => $currentLabels,
            'currentMetrics' => $currentMetrics,
        ]);
        $admissionToken = self::token([
            'promotionEpochToken' => (string) ($epoch['promotionEpochToken'] ?? ''),
            'recursiveLineageToken' => (string) ($epoch['recursiveLineageToken'] ?? ''),
            'windowMetricToken' => (string) ($epoch['windowMetricToken'] ?? ''),
            'recursiveLimitToken' => $recursiveLimitToken,
            'windowPageToken' => $windowPageToken,
        ]);
        $acks = [
            'current-source:' . $admissionToken,
            'recursive-limit:' . $recursiveLimitToken,
            'window-page:' . $windowPageToken,
        ];

        return [
            'currentSourceAdmissionToken' => $admissionToken,
            'currentRecursiveLimitToken' => $recursiveLimitToken,
            'currentWindowPageToken' => $windowPageToken,
            'requiredCurrentSourceAcks' => $acks,
            'requiredCurrentSourceAckCount' => count($acks),
            'currentLabels' => $currentLabels,
            'currentWindowMetrics' => $currentMetrics,
            'currentFinalPage' => $finalPage,
            'recursiveLimit' => $recursiveLimit,
            'promotionEpochToken' => (string) ($epoch['promotionEpochToken'] ?? ''),
            'recursiveLineageToken' => (string) ($epoch['recursiveLineageToken'] ?? ''),
            'windowMetricToken' => (string) ($epoch['windowMetricToken'] ?? ''),
            'currentExposure' => 'held-until-current-recursive-limit-window-page-acks-match',
            'nextSourcePromotionBlocked' => true,
            'yieldBoundary' => 'compound-window-recursive-next253-current-source-admission-fence',
        ];
    }

    /** @param array<string,mixed>|null $cursor @param array<string,mixed> $admission */
    private static function validateCursor(?array $cursor, array $admission): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'currentSourceAdmissionTokenNext253' => 'currentSourceAdmissionToken',
            'currentRecursiveLimitTokenNext253' => 'currentRecursiveLimitToken',
            'currentWindowPageTokenNext253' => 'currentWindowPageToken',
        ] as $cursorKey => $admissionKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $admission[$admissionKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next253 cursor does not match current-source admission fence');
            }
        }
        if (!array_key_exists('acknowledgedCurrentSourceAcksNext253', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCurrentSourceAcksNext253'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next253 current-source acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedCurrentSourceAcksNext253']);
        $required = self::strings($admission['requiredCurrentSourceAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next253 current-source acknowledgements do not match recursive LIMIT/window page set');
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

    /** @param mixed $value @return int|string|null */
    private static function scalar(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) || $value === null ? $value : (string) $value;
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
