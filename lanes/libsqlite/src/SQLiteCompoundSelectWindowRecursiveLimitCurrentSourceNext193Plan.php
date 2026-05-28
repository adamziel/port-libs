<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext193Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext189Plan::compare($sql, $currentTables, $nextTables, $cursor);
        $source = self::currentSourceFence($sql, $base);
        self::validateSourceCursor($cursor, $source['sourceSignature']);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next193-ready';
        $base['currentSourceNext193'] = $source;
        $base['cursor']['currentSourceSignature'] = $source['sourceSignature'];
        $base['cursor']['nextBoundaryLabel'] = $source['nextBoundaryLabel'];
        $base['replanReasons'][] = 'compound-recursive-window-current-source-signature-next193';
        $base['replanReasons'][] = 'compound-limit-boundary-next-source-admission-next193';
        $base['dependencies'][] = 'sqlite-current-source-recursive-window-signature-next193';
        $base['dependency_closure'] = 'no new support component needed; next193 reuses the accepted compound SELECT, recursive CTE LIMIT/OFFSET, per-arm window evaluation, final LIMIT/OFFSET, and current-source token helpers';
        $base['non_overlap'] = 'avoids accepted next189 row-token fence by adding a separate current-source signature over SQL text, recursive trace, window functions, and final compound boundary admission';

        return $base;
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function currentSourceFence(string $sql, array $plan): array
    {
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursive = is_array($plan['recursiveLimit'] ?? null) ? $plan['recursiveLimit'] : [];
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $windows = is_array($plan['windows'] ?? null) ? $plan['windows'] : [];
        $functions = is_array($windows['functions'] ?? null) ? $windows['functions'] : [];
        $currentLabels = self::stringList($sourceWindow['currentAdmittedLabels'] ?? []);
        $nextLabels = self::stringList($sourceWindow['nextAdmittedLabels'] ?? []);
        $limit = is_int($compound['limit'] ?? null) ? $compound['limit'] : 0;
        $offset = is_int($compound['offset'] ?? null) ? $compound['offset'] : 0;
        $boundaryIndex = $limit > 0 ? $offset + $limit - 1 : $offset;
        $currentBoundary = $currentLabels[$boundaryIndex] ?? null;
        $nextBoundary = $nextLabels[$boundaryIndex] ?? null;
        $payload = [
            'sql' => self::normalizeSql($sql),
            'rowToken' => $sourceWindow['currentToken'] ?? null,
            'operators' => $compound['operators'] ?? [],
            'orderColumns' => $compound['orderColumns'] ?? [],
            'limit' => $limit,
            'offset' => $offset,
            'recursiveEmitted' => self::stringList($recursive['currentEmittedLabels'] ?? []),
            'recursiveSkipped' => self::stringList($recursive['currentSkippedLabels'] ?? []),
            'windowFunctions' => $functions,
            'currentBoundaryLabel' => $currentBoundary,
            'currentRows' => $currentLabels,
        ];

        return [
            'sourceSignature' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'nextSourceSignature' => hash('sha256', json_encode([
                ...$payload,
                'rowToken' => $sourceWindow['nextToken'] ?? null,
                'currentRows' => $nextLabels,
                'currentBoundaryLabel' => $nextBoundary,
            ], JSON_THROW_ON_ERROR)),
            'currentBoundaryLabel' => $currentBoundary,
            'nextBoundaryLabel' => $nextBoundary,
            'boundaryChanged' => $currentBoundary !== $nextBoundary,
            'currentAdmittedCount' => count($currentLabels),
            'nextAdmittedCount' => count($nextLabels),
            'currentRecursiveEmittedCount' => count(self::stringList($recursive['currentEmittedLabels'] ?? [])),
            'nextRecursiveEmittedCount' => count(self::stringList($recursive['nextEmittedLabels'] ?? [])),
            'windowFunctions' => $functions,
            'admission' => ($sourceWindow['currentToken'] ?? null) === ($sourceWindow['nextToken'] ?? null) ? 'next-source-boundary-stable' : 'next-source-boundary-reprepare-required',
        ];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $item): string => (string) $item, $value));
    }

    private static function normalizeSql(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    /**
     * @param array<string,mixed>|null $cursor
     */
    private static function validateSourceCursor(?array $cursor, string $signature): void
    {
        if ($cursor === null || !array_key_exists('currentSourceSignature', $cursor)) {
            return;
        }
        if ($cursor['currentSourceSignature'] !== $signature) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next193 cursor does not match current-source signature');
        }
    }
}
