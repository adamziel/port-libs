<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext239Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext235Plan::compare(
            $sql,
            $currentTables,
            $nextTables,
            self::baseCursor($cursor),
        );
        $fence = self::resumeFence($base);
        self::validateCursor($cursor, $fence);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next239-ready';
        $base['compoundLimitResumeFenceNext239'] = $fence;
        $base['cursor']['compoundLimitResumeTokenNext239'] = $fence['resumeToken'];
        $base['cursor']['currentLimitSignatureNext239'] = $fence['currentLimitSignature'];
        $base['cursor']['recursiveWindowSignatureNext239'] = $fence['recursiveWindowSignature'];
        $base['cursor']['requiredResumeAcksNext239'] = $fence['requiredResumeAcks'];
        $base['cursor']['nextSourceCursorNext239'] = $fence['nextSourceCursor'];
        $base['replanReasons'][] = 'compound-limit-resume-fence-next239';
        $base['replanReasons'][] = 'current-output-recursive-window-signature-next239';
        $base['dependencies'][] = 'sqlite-compound-limit-resume-fence-current-source-next239';
        $base['dependency_closure'] = 'no new support component needed; next239 reuses accepted compound SELECT, recursive CTE trace, window ranking, and current-source cursor tokens to fence LIMIT/OFFSET resume promotion';
        $base['non_overlap'] = 'next239 extends accepted next235 promotion barriers by binding the final current LIMIT output signature to recursive and window signatures before replaying a next-source cursor; it avoids accepted compound row composition, JSON table, WAL/VFS, B-tree, encoding, and status-only surfaces';

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
    private static function resumeFence(array $plan): array
    {
        $barrier = is_array($plan['recursiveWindowPromotionBarrierNext235'] ?? null)
            ? $plan['recursiveWindowPromotionBarrierNext235']
            : [];
        $limitTrace = is_array($plan['limitTrace'] ?? null) ? $plan['limitTrace'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $currentLabels = self::rowLabels($currentRows);
        $nextLabels = self::rowLabels($nextRows);
        $currentLimit = is_array($limitTrace['current'] ?? null) ? $limitTrace['current'] : [];
        $nextLimit = is_array($limitTrace['next'] ?? null) ? $limitTrace['next'] : [];

        $currentLimitSignature = self::token([
            'labels' => $currentLabels,
            'rows' => self::canonicalRows($currentRows),
            'preLimitCount' => (int) ($currentLimit['preLimitCount'] ?? count($currentRows)),
            'admittedCount' => count($currentRows),
            'limit' => (int) ($currentLimit['limit'] ?? count($currentRows)),
            'offset' => (int) ($currentLimit['offset'] ?? 0),
            'suppressedCount' => (int) ($currentLimit['suppressedCount'] ?? 0),
        ]);
        $recursiveWindowSignature = self::token([
            'recursiveSkipped' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'recursiveEmitted' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'windowCurrent' => self::stringList($sourceWindow['currentAdmittedLabels'] ?? []),
            'windowNext' => self::stringList($sourceWindow['nextAdmittedLabels'] ?? []),
            'windowFunctions' => self::stringList($barrier['windowFunctions'] ?? []),
        ]);
        $requiredResumeAcks = [
            'limit:' . $currentLimitSignature,
            'recursive-window:' . $recursiveWindowSignature,
            'promotion:' . (string) ($barrier['barrierToken'] ?? ''),
        ];
        $resumeToken = self::token([
            'currentLimitSignature' => $currentLimitSignature,
            'recursiveWindowSignature' => $recursiveWindowSignature,
            'requiredResumeAcks' => $requiredResumeAcks,
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextSourceCursor' => is_array($barrier['promotedNextSourceCursor'] ?? null) ? $barrier['promotedNextSourceCursor'] : [],
        ]);

        return [
            'resumeToken' => $resumeToken,
            'currentLimitSignature' => $currentLimitSignature,
            'recursiveWindowSignature' => $recursiveWindowSignature,
            'requiredResumeAcks' => $requiredResumeAcks,
            'requiredResumeAckCount' => count($requiredResumeAcks),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => array_values(array_diff($nextLabels, $currentLabels)),
            'currentOnlyLabels' => array_values(array_diff($currentLabels, $nextLabels)),
            'currentLimitCount' => count($currentRows),
            'nextLimitCount' => count($nextRows),
            'currentSuppressedCount' => (int) ($currentLimit['suppressedCount'] ?? 0),
            'nextSuppressedCount' => (int) ($nextLimit['suppressedCount'] ?? 0),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'resumeState' => 'held-until-current-limit-recursive-window-signatures-match',
            'nextSourceCursor' => is_array($barrier['promotedNextSourceCursor'] ?? null) ? $barrier['promotedNextSourceCursor'] : [],
            'yieldBoundary' => 'compound-recursive-window-next239-limit-resume-fence',
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
            'compoundLimitResumeTokenNext239' => 'resumeToken',
            'currentLimitSignatureNext239' => 'currentLimitSignature',
            'recursiveWindowSignatureNext239' => 'recursiveWindowSignature',
        ] as $cursorKey => $fenceKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $fence[$fenceKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next239 cursor does not match resume fence');
            }
        }
        if (!array_key_exists('acknowledgedResumeAcksNext239', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedResumeAcksNext239'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next239 resume acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedResumeAcksNext239']));
        $required = self::stringList($fence['requiredResumeAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next239 resume acknowledgements do not match required limit/recursive/window set');
        }
    }

    /** @param mixed $rows @return list<array<string,mixed>> */
    private static function rows(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, static fn (mixed $row): bool => is_array($row)));
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function rowLabels(array $rows): array
    {
        $labels = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row['label'] ?? $row['option_name'] ?? $row['name'] ?? reset($row));
        }

        return $labels;
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function canonicalRows(array $rows): array
    {
        $canonical = [];
        foreach ($rows as $row) {
            ksort($row);
            $canonical[] = $row;
        }

        return $canonical;
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
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
