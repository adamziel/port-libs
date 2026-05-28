<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext241Plan
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
        $receipt = self::resumeReceipt($base);
        self::validateCursor($cursor, $receipt);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next241-ready';
        $base['resumeAdmissionReceiptNext241'] = $receipt;
        $base['cursor']['resumeAdmissionTokenNext241'] = $receipt['resumeAdmissionToken'];
        $base['cursor']['currentResultTokenNext241'] = $receipt['currentResultToken'];
        $base['cursor']['nextResultTokenNext241'] = $receipt['nextResultToken'];
        $base['cursor']['windowLimitReceiptTokenNext241'] = $receipt['windowLimitReceiptToken'];
        $base['cursor']['requiredResumeAdmissionAcksNext241'] = $receipt['requiredResumeAdmissionAcks'];
        $base['cursor']['resumeNextSourceCursorNext241'] = $receipt['nextSourceCursor'];
        $base['replanReasons'][] = 'compound-recursive-window-resume-admission-next241';
        $base['replanReasons'][] = 'compound-final-row-token-next241';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-resume-admission-next241';
        $base['dependency_closure'] = 'no new support component needed; next241 reuses accepted next238 source-generation seals and adds final current/next result-row plus recursive/window/LIMIT receipt tokens before a yielded next-source cursor may resume';
        $base['non_overlap'] = 'next241 extends accepted next238 source-generation/final-boundary acknowledgements with final-row resume admission receipts; it avoids accepted next226/229/232/235/238 compound handoffs, suite next241 veryquick evidence, row-value/window RETURNING, trigger recursive UPSERT, JSON table, WAL/VFS, B-tree, planner, PRAGMA, ATTACH, encoding, and VDBE clusters';

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
    private static function resumeReceipt(array $plan): array
    {
        $seal = is_array($plan['sourceGenerationSealNext238'] ?? null) ? $plan['sourceGenerationSealNext238'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $windows = is_array($plan['windows'] ?? null) ? $plan['windows'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $limitTrace = is_array($plan['limitTrace'] ?? null) ? $plan['limitTrace'] : [];
        $currentRows = self::rowList($plan['currentRows'] ?? []);
        $nextRows = self::rowList($plan['nextRows'] ?? []);
        $currentLabels = self::rowLabels($currentRows);
        $nextLabels = self::rowLabels($nextRows);
        $currentResultToken = self::token($currentRows);
        $nextResultToken = self::token($nextRows);
        $windowLimitReceiptToken = self::token([
            'sourceGenerationToken' => (string) ($seal['sourceGenerationToken'] ?? ''),
            'finalBoundaryToken' => (string) ($seal['finalBoundaryToken'] ?? ''),
            'recursiveName' => (string) ($recursiveQueue['name'] ?? ''),
            'recursiveTraceCount' => (int) ($recursiveQueue['currentTraceCount'] ?? 0),
            'windowFunctions' => self::stringList($windows['functions'] ?? []),
            'currentWindowCount' => count(is_array($windows['current'] ?? null) ? $windows['current'] : []),
            'nextWindowCount' => count(is_array($windows['next'] ?? null) ? $windows['next'] : []),
            'currentLimit' => self::traceInt($limitTrace, 'current', 'limit'),
            'currentOffset' => self::traceInt($limitTrace, 'current', 'offset'),
            'nextLimit' => self::traceInt($limitTrace, 'next', 'limit'),
            'nextOffset' => self::traceInt($limitTrace, 'next', 'offset'),
            'currentSkippedLabels' => self::stringList($sourceWindow['currentSkippedLabels'] ?? []),
            'nextSkippedLabels' => self::stringList($sourceWindow['nextSkippedLabels'] ?? []),
            'currentTruncatedLabels' => self::stringList($sourceWindow['currentTruncatedLabels'] ?? []),
            'nextTruncatedLabels' => self::stringList($sourceWindow['nextTruncatedLabels'] ?? []),
        ]);
        $resumeAdmissionToken = self::token([
            'currentResultToken' => $currentResultToken,
            'nextResultToken' => $nextResultToken,
            'windowLimitReceiptToken' => $windowLimitReceiptToken,
            'sourceGenerationToken' => (string) ($seal['sourceGenerationToken'] ?? ''),
            'nextSourceCursor' => is_array($seal['nextSourceCursor'] ?? null) ? $seal['nextSourceCursor'] : [],
        ]);
        $requiredAcks = [
            'current-result:' . $currentResultToken,
            'next-result:' . $nextResultToken,
            'window-limit:' . $windowLimitReceiptToken,
        ];

        return [
            'resumeAdmissionToken' => $resumeAdmissionToken,
            'currentResultToken' => $currentResultToken,
            'nextResultToken' => $nextResultToken,
            'windowLimitReceiptToken' => $windowLimitReceiptToken,
            'requiredResumeAdmissionAcks' => $requiredAcks,
            'requiredResumeAdmissionAckCount' => count($requiredAcks),
            'currentRowCount' => count($currentRows),
            'nextRowCount' => count($nextRows),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => array_values(array_diff($nextLabels, $currentLabels)),
            'currentOnlyLabels' => array_values(array_diff($currentLabels, $nextLabels)),
            'sourceGenerationToken' => (string) ($seal['sourceGenerationToken'] ?? ''),
            'finalBoundaryToken' => (string) ($seal['finalBoundaryToken'] ?? ''),
            'nextSourceCursor' => is_array($seal['nextSourceCursor'] ?? null) ? $seal['nextSourceCursor'] : [],
            'resumeState' => 'held-until-current-next-results-and-window-limit-receipt-acks-match',
            'yieldBoundary' => 'compound-recursive-window-next241-final-row-resume-admission',
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
            'resumeAdmissionTokenNext241' => 'resumeAdmissionToken',
            'currentResultTokenNext241' => 'currentResultToken',
            'nextResultTokenNext241' => 'nextResultToken',
            'windowLimitReceiptTokenNext241' => 'windowLimitReceiptToken',
        ] as $cursorKey => $receiptKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $receipt[$receiptKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next241 cursor does not match final-row resume admission receipt');
            }
        }
        if (!array_key_exists('acknowledgedResumeAdmissionAcksNext241', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedResumeAdmissionAcksNext241'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next241 resume acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedResumeAdmissionAcksNext241']));
        $required = self::stringList($receipt['requiredResumeAdmissionAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next241 resume acknowledgements do not match required current/next/window result set');
        }
    }

    /** @param mixed $value @return list<array<string,mixed>> */
    private static function rowList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                ksort($row);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function rowLabels(array $rows): array
    {
        $labels = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row['label'] ?? $row['option_name'] ?? $row['name'] ?? json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }

        return $labels;
    }

    /** @param mixed $value @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
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
