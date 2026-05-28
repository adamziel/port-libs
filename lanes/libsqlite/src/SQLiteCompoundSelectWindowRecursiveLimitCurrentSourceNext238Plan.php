<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext238Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext235Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $seal = self::sourceGenerationSeal($base);
        self::validateCursor($cursor, $seal);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next238-ready';
        $base['sourceGenerationSealNext238'] = $seal;
        $base['cursor']['sourceGenerationTokenNext238'] = $seal['sourceGenerationToken'];
        $base['cursor']['finalBoundaryTokenNext238'] = $seal['finalBoundaryToken'];
        $base['cursor']['requiredSourceGenerationAcksNext238'] = $seal['requiredSourceGenerationAcks'];
        $base['cursor']['generationNextSourceCursorNext238'] = $seal['nextSourceCursor'];
        $base['replanReasons'][] = 'compound-recursive-window-source-generation-seal-next238';
        $base['replanReasons'][] = 'final-compound-limit-boundary-next238';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-current-source-generation-next238';
        $base['dependency_closure'] = 'no new support component needed; next238 reuses accepted next235 page/recursive/window promotion barriers and adds a source-generation seal over final compound LIMIT boundary movement before next-source cursor admission';
        $base['non_overlap'] = 'next238 extends accepted next235 promotion barriers with source-generation and final-boundary acknowledgements; it avoids accepted next226 sum/count EXCEPT+INTERSECT, next229 dense-rank source tokens, next232 page acknowledgements, next235 promotion barriers, row-value/window RETURNING, trigger recursive UPSERT, JSON table, WAL/VFS, B-tree, planner, PRAGMA, and encoding clusters';

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
    private static function sourceGenerationSeal(array $plan): array
    {
        $barrier = is_array($plan['recursiveWindowPromotionBarrierNext235'] ?? null) ? $plan['recursiveWindowPromotionBarrierNext235'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $limitTrace = is_array($plan['limitTrace'] ?? null) ? $plan['limitTrace'] : [];
        $cursor = is_array($plan['cursor'] ?? null) ? $plan['cursor'] : [];
        $currentLabels = self::stringList($sourceWindow['currentAdmittedLabels'] ?? []);
        $nextLabels = self::stringList($sourceWindow['nextAdmittedLabels'] ?? []);
        $currentSkipped = self::stringList($sourceWindow['currentSkippedLabels'] ?? []);
        $nextSkipped = self::stringList($sourceWindow['nextSkippedLabels'] ?? []);
        $currentTruncated = self::stringList($sourceWindow['currentTruncatedLabels'] ?? []);
        $nextTruncated = self::stringList($sourceWindow['nextTruncatedLabels'] ?? []);
        $nextOnly = self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []);
        $currentOnly = self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []);
        $generation = self::sourceGeneration($currentLabels, $nextLabels, $currentSkipped, $nextSkipped, $currentTruncated, $nextTruncated);
        $finalBoundaryToken = self::token([
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'currentSkipped' => $currentSkipped,
            'nextSkipped' => $nextSkipped,
            'currentTruncated' => $currentTruncated,
            'nextTruncated' => $nextTruncated,
            'currentLimit' => self::traceInt($limitTrace, 'current', 'limit'),
            'currentOffset' => self::traceInt($limitTrace, 'current', 'offset'),
            'nextLimit' => self::traceInt($limitTrace, 'next', 'limit'),
            'nextOffset' => self::traceInt($limitTrace, 'next', 'offset'),
        ]);
        $sourceGenerationToken = self::token([
            'barrierToken' => (string) ($barrier['barrierToken'] ?? ''),
            'sourceGeneration' => $generation,
            'finalBoundaryToken' => $finalBoundaryToken,
            'nextOnly' => $nextOnly,
            'currentOnly' => $currentOnly,
        ]);
        $requiredAcks = [
            'generation:' . $sourceGenerationToken,
            'boundary:' . $finalBoundaryToken,
        ];

        return [
            'sourceGenerationToken' => $sourceGenerationToken,
            'finalBoundaryToken' => $finalBoundaryToken,
            'sourceGeneration' => $generation,
            'requiredSourceGenerationAcks' => $requiredAcks,
            'requiredSourceGenerationAckCount' => count($requiredAcks),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => $nextOnly,
            'currentOnlyLabels' => $currentOnly,
            'currentSkippedLabels' => $currentSkipped,
            'nextSkippedLabels' => $nextSkipped,
            'currentTruncatedLabels' => $currentTruncated,
            'nextTruncatedLabels' => $nextTruncated,
            'promotionBarrierToken' => (string) ($barrier['barrierToken'] ?? ''),
            'nextSourceCursor' => is_array($cursor['nextSourceCursorNext235'] ?? null) ? $cursor['nextSourceCursorNext235'] : [],
            'admissionState' => 'held-until-source-generation-and-final-boundary-acks-match',
            'yieldBoundary' => 'compound-recursive-window-next238-source-generation-seal-fences-next-source',
        ];
    }

    /**
     * @param list<string> $currentLabels
     * @param list<string> $nextLabels
     * @param list<string> $currentSkipped
     * @param list<string> $nextSkipped
     * @param list<string> $currentTruncated
     * @param list<string> $nextTruncated
     * @return array<string,mixed>
     */
    private static function sourceGeneration(array $currentLabels, array $nextLabels, array $currentSkipped, array $nextSkipped, array $currentTruncated, array $nextTruncated): array
    {
        return [
            'currentPageHash' => self::token($currentLabels),
            'nextPageHash' => self::token($nextLabels),
            'currentBoundaryHash' => self::token([$currentSkipped, $currentTruncated]),
            'nextBoundaryHash' => self::token([$nextSkipped, $nextTruncated]),
            'pageChanged' => $currentLabels !== $nextLabels,
            'boundaryChanged' => $currentSkipped !== $nextSkipped || $currentTruncated !== $nextTruncated,
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $seal
     */
    private static function validateCursor(?array $cursor, array $seal): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'sourceGenerationTokenNext238' => 'sourceGenerationToken',
            'finalBoundaryTokenNext238' => 'finalBoundaryToken',
        ] as $cursorKey => $sealKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $seal[$sealKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next238 cursor does not match source-generation seal');
            }
        }
        if (!array_key_exists('acknowledgedSourceGenerationAcksNext238', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedSourceGenerationAcksNext238'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next238 source-generation acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedSourceGenerationAcksNext238']));
        $required = self::stringList($seal['requiredSourceGenerationAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next238 source-generation acknowledgements do not match required generation/boundary set');
        }
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
