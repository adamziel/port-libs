<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext247Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext244Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $seal = self::offsetYieldSeal($base);
        self::validateCursor($cursor, $seal);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next247-ready';
        $base['recursiveOffsetYieldSealNext247'] = $seal;
        $base['cursor']['recursiveOffsetYieldSealTokenNext247'] = $seal['recursiveOffsetYieldSealToken'];
        $base['cursor']['currentSkippedWindowTokenNext247'] = $seal['currentSkippedWindowToken'];
        $base['cursor']['nextSourceCursorTokenNext247'] = $seal['nextSourceCursorToken'];
        $base['cursor']['requiredRecursiveOffsetYieldAcksNext247'] = $seal['requiredRecursiveOffsetYieldAcks'];
        $base['cursor']['yieldDecisionNext247'] = $seal['yieldDecision'];
        $base['replanReasons'][] = 'compound-recursive-limit-offset-yield-seal-next247';
        $base['replanReasons'][] = 'compound-window-next-source-cursor-held-after-offset-skip-next247';
        $base['dependencies'][] = 'sqlite-compound-recursive-limit-offset-window-yield-seal-next247';
        $base['dependency_closure'] = 'no new support component needed; next247 reuses accepted compound SELECT recursive LIMIT/OFFSET, window result metrics, next244 recursive exhaustion acknowledgements, and adds a skipped-row lineage seal before yielding the next-source cursor';
        $base['non_overlap'] = 'next247 extends accepted next244 recursive LIMIT exhaustion fencing by binding OFFSET-skipped recursive rows, window result labels, and the yielded next-source cursor into one acknowledgement set; it avoids accepted next244 exhaustion-only fencing, next243 replay tickets, next241 resume admission, row-value/window RETURNING, trigger recursive UPSERT, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, and suite evidence clusters';

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
            'resumeAdmissionTokenNext241',
            'currentResultTokenNext241',
            'nextResultTokenNext241',
            'windowLimitReceiptTokenNext241',
            'acknowledgedResumeAdmissionAcksNext241',
            'recursiveLimitFenceTokenNext244',
            'currentRecursiveWindowTokenNext244',
            'nextRecursiveWindowTokenNext244',
            'acknowledgedRecursiveLimitAcksNext244',
        ] as $key) {
            if (array_key_exists($key, $cursor)) {
                $base[$key] = $cursor[$key];
            }
        }

        return $base === [] ? null : $base;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private static function offsetYieldSeal(array $plan): array
    {
        $queue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $fence = is_array($plan['recursiveLimitExhaustionFenceNext244'] ?? null) ? $plan['recursiveLimitExhaustionFenceNext244'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $currentSkipped = self::strings($queue['currentSkippedLabels'] ?? []);
        $nextSkipped = self::strings($queue['nextSkippedLabels'] ?? []);
        $nextCursor = is_array($fence['nextSourceCursor'] ?? null) ? $fence['nextSourceCursor'] : [];

        $currentSkippedWindowToken = self::token([
            'skipped' => $currentSkipped,
            'currentResultLabels' => self::labels($currentRows),
            'currentWindowMetrics' => self::metrics($currentRows),
            'currentRecursiveWindowToken' => (string) ($fence['currentRecursiveWindowToken'] ?? ''),
        ]);
        $nextSourceCursorToken = self::token([
            'nextCursor' => $nextCursor,
            'nextSkipped' => $nextSkipped,
            'nextResultLabels' => self::labels($nextRows),
            'nextWindowMetrics' => self::metrics($nextRows),
            'nextRecursiveWindowToken' => (string) ($fence['nextRecursiveWindowToken'] ?? ''),
        ]);
        $recursiveOffsetYieldSealToken = self::token([
            'currentSkippedWindowToken' => $currentSkippedWindowToken,
            'nextSourceCursorToken' => $nextSourceCursorToken,
            'recursiveLimitFenceToken' => (string) ($fence['recursiveLimitFenceToken'] ?? ''),
            'requiredRecursiveLimitAcks' => self::strings($fence['requiredRecursiveLimitAcks'] ?? []),
        ]);
        $required = [
            'offset-current-skipped:' . $currentSkippedWindowToken,
            'offset-next-cursor:' . $nextSourceCursorToken,
            'offset-yield-seal:' . $recursiveOffsetYieldSealToken,
        ];

        return [
            'recursiveOffsetYieldSealToken' => $recursiveOffsetYieldSealToken,
            'currentSkippedWindowToken' => $currentSkippedWindowToken,
            'nextSourceCursorToken' => $nextSourceCursorToken,
            'requiredRecursiveOffsetYieldAcks' => $required,
            'requiredRecursiveOffsetYieldAckCount' => count($required),
            'currentSkippedLabels' => $currentSkipped,
            'nextSkippedLabels' => $nextSkipped,
            'currentResultLabels' => self::labels($currentRows),
            'nextResultLabels' => self::labels($nextRows),
            'currentWindowMetrics' => self::metrics($currentRows),
            'nextWindowMetrics' => self::metrics($nextRows),
            'nextOnlyLabels' => self::strings($fence['nextOnlyLabels'] ?? []),
            'nextSourceCursor' => $nextCursor,
            'yieldDecision' => 'held-until-recursive-offset-window-and-next-cursor-acks-match',
            'yieldBoundary' => 'compound-window-recursive-limit-next247-offset-skip-before-next-source-cursor',
        ];
    }

    /** @param array<string,mixed>|null $cursor @param array<string,mixed> $seal */
    private static function validateCursor(?array $cursor, array $seal): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'recursiveOffsetYieldSealTokenNext247' => 'recursiveOffsetYieldSealToken',
            'currentSkippedWindowTokenNext247' => 'currentSkippedWindowToken',
            'nextSourceCursorTokenNext247' => 'nextSourceCursorToken',
        ] as $cursorKey => $sealKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $seal[$sealKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next247 cursor does not match recursive OFFSET yield seal');
            }
        }
        if (!array_key_exists('acknowledgedRecursiveOffsetYieldAcksNext247', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedRecursiveOffsetYieldAcksNext247'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next247 acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedRecursiveOffsetYieldAcksNext247']);
        $required = self::strings($seal['requiredRecursiveOffsetYieldAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next247 acknowledgements do not match required OFFSET/window yield seal');
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
                ksort($row);
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param list<array<string,mixed>> $rows @return list<string> */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['option_name'] ?? $row['name'] ?? json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)), $rows));
    }

    /** @param list<array<string,mixed>> $rows @return list<mixed> */
    private static function metrics(array $rows): array
    {
        return array_values(array_map(static fn (array $row): mixed => $row['rn'] ?? $row['metric'] ?? $row['rank'] ?? null, $rows));
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
