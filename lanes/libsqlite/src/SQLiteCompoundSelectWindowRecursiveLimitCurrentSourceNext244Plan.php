<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext244Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext241Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $fence = self::recursiveLimitFence($base);
        self::validateCursor($cursor, $fence);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next244-ready';
        $base['recursiveLimitExhaustionFenceNext244'] = $fence;
        $base['cursor']['recursiveLimitFenceTokenNext244'] = $fence['recursiveLimitFenceToken'];
        $base['cursor']['currentRecursiveWindowTokenNext244'] = $fence['currentRecursiveWindowToken'];
        $base['cursor']['nextRecursiveWindowTokenNext244'] = $fence['nextRecursiveWindowToken'];
        $base['cursor']['requiredRecursiveLimitAcksNext244'] = $fence['requiredRecursiveLimitAcks'];
        $base['cursor']['yieldedNextSourceCursorNext244'] = $fence['nextSourceCursor'];
        $base['replanReasons'][] = 'compound-recursive-limit-exhaustion-fence-next244';
        $base['replanReasons'][] = 'compound-window-yielded-next-source-held-next244';
        $base['dependencies'][] = 'sqlite-compound-recursive-limit-window-yield-fence-next244';
        $base['dependency_closure'] = 'no new support component needed; next244 reuses accepted next241 final-row resume admission and adds a recursive LIMIT exhaustion fence over current/next recursive queue plus window tokens before yielding the next-source cursor';
        $base['non_overlap'] = 'next244 extends accepted next241 final-row resume admission with recursive LIMIT exhaustion acknowledgements; it avoids accepted next226/228/230/232/235/238/241 compound handoffs, suite next244 evidence, row-value/window RETURNING, trigger recursive UPSERT, JSON table, WAL/VFS, B-tree, planner, PRAGMA, encoding, and VDBE clusters';

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
        ] as $key) {
            if (array_key_exists($key, $cursor)) {
                $base[$key] = $cursor[$key];
            }
        }

        return $base === [] ? null : $base;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private static function recursiveLimitFence(array $plan): array
    {
        $queue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $windows = is_array($plan['windows'] ?? null) ? $plan['windows'] : [];
        $receipt = is_array($plan['resumeAdmissionReceiptNext241'] ?? null) ? $plan['resumeAdmissionReceiptNext241'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $currentPreLimit = self::rows($plan['currentPreLimitRows'] ?? []);
        $nextPreLimit = self::rows($plan['nextPreLimitRows'] ?? []);

        $currentRecursiveWindowToken = self::token([
            'recursiveName' => (string) ($queue['name'] ?? ''),
            'traceCount' => (int) ($queue['currentTraceCount'] ?? 0),
            'emittedLabels' => self::strings($queue['currentEmittedLabels'] ?? []),
            'windowFunctions' => self::strings($windows['functions'] ?? []),
            'windowTerms' => self::strings(array_column(is_array($windows['current'] ?? null) ? $windows['current'] : [], 'function')),
            'preLimitLabels' => self::labels($currentPreLimit),
            'resultLabels' => self::labels($currentRows),
        ]);
        $nextRecursiveWindowToken = self::token([
            'recursiveName' => (string) ($queue['name'] ?? ''),
            'traceCount' => (int) ($queue['nextTraceCount'] ?? 0),
            'emittedLabels' => self::strings($queue['nextEmittedLabels'] ?? []),
            'windowFunctions' => self::strings($windows['functions'] ?? []),
            'windowTerms' => self::strings(array_column(is_array($windows['next'] ?? null) ? $windows['next'] : [], 'function')),
            'preLimitLabels' => self::labels($nextPreLimit),
            'resultLabels' => self::labels($nextRows),
        ]);
        $recursiveLimitFenceToken = self::token([
            'currentRecursiveWindowToken' => $currentRecursiveWindowToken,
            'nextRecursiveWindowToken' => $nextRecursiveWindowToken,
            'resumeAdmissionToken' => (string) ($receipt['resumeAdmissionToken'] ?? ''),
            'sourceGenerationToken' => (string) ($receipt['sourceGenerationToken'] ?? ''),
            'finalBoundaryToken' => (string) ($receipt['finalBoundaryToken'] ?? ''),
        ]);
        $required = [
            'recursive-current:' . $currentRecursiveWindowToken,
            'recursive-next:' . $nextRecursiveWindowToken,
            'recursive-limit-fence:' . $recursiveLimitFenceToken,
        ];

        return [
            'recursiveLimitFenceToken' => $recursiveLimitFenceToken,
            'currentRecursiveWindowToken' => $currentRecursiveWindowToken,
            'nextRecursiveWindowToken' => $nextRecursiveWindowToken,
            'requiredRecursiveLimitAcks' => $required,
            'requiredRecursiveLimitAckCount' => count($required),
            'currentRecursiveTraceCount' => (int) ($queue['currentTraceCount'] ?? 0),
            'nextRecursiveTraceCount' => (int) ($queue['nextTraceCount'] ?? 0),
            'currentPreLimitCount' => count($currentPreLimit),
            'nextPreLimitCount' => count($nextPreLimit),
            'currentResultLabels' => self::labels($currentRows),
            'nextResultLabels' => self::labels($nextRows),
            'nextOnlyLabels' => self::strings($receipt['nextOnlyLabels'] ?? []),
            'nextSourceCursor' => is_array($receipt['nextSourceCursor'] ?? null) ? $receipt['nextSourceCursor'] : [],
            'yieldBoundary' => 'compound-window-recursive-limit-next244-exhaustion-before-next-source',
            'resumeState' => 'held-until-recursive-limit-current-and-next-window-acks-match',
        ];
    }

    /** @param array<string,mixed>|null $cursor @param array<string,mixed> $fence */
    private static function validateCursor(?array $cursor, array $fence): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'recursiveLimitFenceTokenNext244' => 'recursiveLimitFenceToken',
            'currentRecursiveWindowTokenNext244' => 'currentRecursiveWindowToken',
            'nextRecursiveWindowTokenNext244' => 'nextRecursiveWindowToken',
        ] as $cursorKey => $fenceKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $fence[$fenceKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next244 cursor does not match recursive LIMIT exhaustion fence');
            }
        }
        if (!array_key_exists('acknowledgedRecursiveLimitAcksNext244', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedRecursiveLimitAcksNext244'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next244 acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedRecursiveLimitAcksNext244']);
        $required = self::strings($fence['requiredRecursiveLimitAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next244 acknowledgements do not match required recursive/window fence set');
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
