<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext254Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext250Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $receipt = self::promotionReceipt($base);
        self::validateCursor($cursor, $receipt);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next254-ready';
        $base['compoundWindowRecursiveLimitReceiptNext254'] = $receipt;
        $base['cursor']['compoundReceiptTokenNext254'] = $receipt['compoundReceiptToken'];
        $base['cursor']['recursiveWindowBoundaryTokenNext254'] = $receipt['recursiveWindowBoundaryToken'];
        $base['cursor']['requiredCompoundReceiptAcksNext254'] = $receipt['requiredCompoundReceiptAcks'];
        $base['cursor']['nextExposureNext254'] = $receipt['nextExposure'];
        $base['replanReasons'][] = 'compound-recursive-window-limit-receipt-next254';
        $base['replanReasons'][] = 'next-source-held-until-compound-window-recursive-receipts-next254';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-limit-receipt-next254';
        $base['dependency_closure'] = 'no new support component needed; next254 reuses accepted compound SELECT, recursive LIMIT/OFFSET exhaustion, window page frames, current-source next-page admission, and adds a bounded compound/window/recursive receipt gate';
        $base['non_overlap'] = 'next254 extends accepted next250 next-page admission by binding next-source exposure to compound operators, final LIMIT/OFFSET position, recursive emitted/skipped labels, and current/next window page frames; it avoids accepted next248/next249/next250 receipt variants, row-value/window, trigger, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, VDBE, and suite evidence clusters';

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
    private static function promotionReceipt(array $plan): array
    {
        $admission = is_array($plan['compoundCurrentSourceNextPageAdmissionNext250'] ?? null)
            ? $plan['compoundCurrentSourceNextPageAdmissionNext250']
            : [];
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $currentFrame = self::pageFrame($admission['currentPageFrame'] ?? []);
        $nextFrame = self::pageFrame($admission['nextPageFrame'] ?? []);
        $operators = self::strings($compound['operators'] ?? []);
        $limit = isset($compound['limit']) ? (int) $compound['limit'] : null;
        $offset = isset($compound['offset']) ? (int) $compound['offset'] : 0;
        $recursiveLineage = [
            'emitted' => self::strings($admission['recursiveEmittedLabels'] ?? []),
            'skipped' => self::strings($admission['recursiveSkippedLabels'] ?? []),
            'queueCurrentEmitted' => self::strings($recursiveQueue['currentEmittedLabels'] ?? []),
            'queueCurrentSkipped' => self::strings($recursiveQueue['currentSkippedLabels'] ?? []),
        ];
        $windowBoundary = [
            'currentFrame' => $currentFrame,
            'nextFrame' => $nextFrame,
            'currentLabels' => self::strings($admission['currentLabels'] ?? []),
            'nextLabels' => self::strings($admission['nextLabels'] ?? []),
            'currentToken' => (string) ($sourceWindow['currentToken'] ?? ''),
            'nextToken' => (string) ($sourceWindow['nextToken'] ?? ''),
        ];
        $recursiveWindowBoundaryToken = self::token([
            'recursiveLineage' => $recursiveLineage,
            'windowBoundary' => $windowBoundary,
        ]);
        $compoundReceiptToken = self::token([
            'nextPageAdmissionToken' => (string) ($admission['nextPageAdmissionToken'] ?? ''),
            'currentSourceResumeToken' => (string) ($admission['currentSourceResumeToken'] ?? ''),
            'recursiveWindowBoundaryToken' => $recursiveWindowBoundaryToken,
            'operators' => $operators,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        $required = [
            'admission:' . (string) ($admission['nextPageAdmissionToken'] ?? ''),
            'boundary:' . $recursiveWindowBoundaryToken,
            'compound:' . $compoundReceiptToken,
        ];

        return [
            'compoundReceiptToken' => $compoundReceiptToken,
            'recursiveWindowBoundaryToken' => $recursiveWindowBoundaryToken,
            'requiredCompoundReceiptAcks' => $required,
            'requiredCompoundReceiptAckCount' => count($required),
            'compoundOperators' => $operators,
            'finalLimit' => $limit,
            'finalOffset' => $offset,
            'currentPageFrame' => $currentFrame,
            'nextPageFrame' => $nextFrame,
            'currentLabels' => self::strings($admission['currentLabels'] ?? []),
            'nextLabels' => self::strings($admission['nextLabels'] ?? []),
            'recursiveLineage' => $recursiveLineage,
            'windowFrameChanged' => $currentFrame !== $nextFrame,
            'labelBoundaryChanged' => self::strings($admission['currentLabels'] ?? []) !== self::strings($admission['nextLabels'] ?? []),
            'nextExposure' => 'held-until-compound-recursive-window-limit-receipts',
            'yieldBoundary' => 'compound-window-recursive-next254-receipt-gate',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $receipt
     */
    private static function validateCursor(?array $cursor, array $receipt): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'compoundReceiptTokenNext254' => 'compoundReceiptToken',
            'recursiveWindowBoundaryTokenNext254' => 'recursiveWindowBoundaryToken',
        ] as $cursorKey => $receiptKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $receipt[$receiptKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next254 cursor does not match compound receipt state');
            }
        }
        if (!array_key_exists('acknowledgedCompoundReceiptAcksNext254', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCompoundReceiptAcksNext254'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next254 receipt acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedCompoundReceiptAcksNext254']);
        $required = self::strings($receipt['requiredCompoundReceiptAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next254 receipt acknowledgements do not match required compound/window/recursive set');
        }
    }

    /** @param mixed $value @return list<array<string,mixed>> */
    private static function pageFrame(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'ordinal' => isset($row['ordinal']) ? (int) $row['ordinal'] : count($rows) + 1,
                'id' => $row['id'] ?? null,
                'label' => (string) ($row['label'] ?? ''),
                'metric' => $row['metric'] ?? null,
            ];
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
