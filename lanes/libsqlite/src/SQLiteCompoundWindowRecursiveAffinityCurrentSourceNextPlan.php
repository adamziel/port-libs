<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan. */

    /**
         * @param array<string,list<array<string,mixed>>> $currentTables
         * @param array<string,list<array<string,mixed>>> $nextTables
         * @param array<string,mixed>|null $cursor
         * @return array<string,mixed>
         */
        public static function pageCurrentSourceCursor(string $sql, array $currentTables, array $nextTables, int $limit, int $offset = 0, ?array $cursor = null): array
        {
            if ($limit <= 0) {
                throw new \InvalidArgumentException('SQLite compound window recursive affinity current-source cursor limit must be positive');
            }
            if ($offset < 0) {
                throw new \InvalidArgumentException('SQLite compound window recursive affinity current-source cursor offset must be non-negative');
            }

            $base = SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan::compareRecursiveUnionSourceBoundary($sql, $currentTables, $nextTables);
            $currentSignature = self::cursorSourceSignature($base['currentSignatures']);
            $nextSignature = self::cursorSourceSignature($base['nextSignatures']);
            if ($cursor !== null) {
                self::validateCurrentSourceCursor($cursor, $offset, $currentSignature, $nextSignature);
            }

            $currentRows = self::sliceCursorPage($base['currentRows'], $limit, $offset);
            $nextRows = self::sliceCursorPage($base['nextRows'], $limit, $offset);
            $nextOffset = $offset + $limit;
            $hasMore = $nextOffset < max(count($base['currentRows']), count($base['nextRows']));

            return [
                'status' => 'compound-window-recursive-affinity-current-source-cursor-ready',
                'limit' => $limit,
                'offset' => $offset,
                'currentRows' => $currentRows,
                'nextRows' => $nextRows,
                'currentTotalRows' => count($base['currentRows']),
                'nextTotalRows' => count($base['nextRows']),
                'currentPageIds' => array_column($currentRows, 'id'),
                'nextPageIds' => array_column($nextRows, 'id'),
                'currentPageSources' => array_column($currentRows, 'source'),
                'nextPageSources' => array_column($nextRows, 'source'),
                'currentSignature' => $currentSignature,
                'nextSignature' => $nextSignature,
                'cursor' => [
                    'offset' => $nextOffset,
                    'limit' => $limit,
                    'currentSignature' => $currentSignature,
                    'nextSignature' => $nextSignature,
                    'hasMore' => $hasMore,
                ],
                'windowFence' => [
                    'functions' => array_column($base['windows']['current'], 'function'),
                    'aliases' => array_column($base['windows']['current'], 'alias'),
                    'frameUnits' => array_column($base['windows']['current'], 'frameUnit'),
                ],
                'recursiveFence' => [
                    'operator' => $base['recursive']['operator'],
                    'currentTraceCount' => $base['recursive']['currentTraceCount'],
                    'nextTraceCount' => $base['recursive']['nextTraceCount'],
                    'currentSkipped' => $base['recursive']['currentSkipped'],
                    'nextSkipped' => $base['recursive']['nextSkipped'],
                ],
                'affinityFence' => [
                    'currentKeyClasses' => $base['affinity']['currentKeyClasses'],
                    'nextKeyClasses' => $base['affinity']['nextKeyClasses'],
                    'changedKeyClasses' => $base['affinity']['changedKeyClasses'],
                ],
                'sourceDelta' => $base['sourceDelta'],
                'replanReasons' => array_values(array_unique(array_merge($base['replanReasons'], [
                    'current-source-cursor-fence',
                ]))),
                'dependencies' => [
                    'sqlite-compound-recursive-affinity-window-current-source-source-boundary',
                    'sqlite-compound-current-source-cursor-fence',
                    'sqlite-recursive-union-affinity-page-boundary',
                    'sqlite-window-before-compound-page-resume',
                ],
                'dependency_closure' => 'no new support component needed; cursor reuses native recursive CTE, compound SELECT, window, affinity, and current-source rowset helpers and adds only lane-local cursor fencing',
                'non_overlap' => 'avoids accepted source-boundary recursive affinity window rowset behavior and next143 EXCEPT final ORDER behavior by adding stale-cursor checked current/next paging over the already materialized compound rowsets',
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array<string,mixed>>
         */
        private static function sliceCursorPage(array $rows, int $limit, int $offset): array
        {
            return array_values(array_slice($rows, $offset, $limit));
        }

        /**
         * @param list<string> $rowSignatures
         */
        private static function cursorSourceSignature(array $rowSignatures): string
        {
            return hash('sha256', json_encode($rowSignatures, JSON_THROW_ON_ERROR));
        }

        /**
         * @param array<string,mixed> $cursor
         */
        private static function validateCurrentSourceCursor(array $cursor, int $offset, string $currentSignature, string $nextSignature): void
        {
            if (($cursor['offset'] ?? null) !== $offset) {
                throw new \InvalidArgumentException('SQLite compound window recursive affinity current-source cursor offset does not match requested offset');
            }
            if (($cursor['currentSignature'] ?? null) !== $currentSignature) {
                throw new \InvalidArgumentException('SQLite compound window recursive affinity current-source cursor does not match current source');
            }
            if (($cursor['nextSignature'] ?? null) !== $nextSignature) {
                throw new \InvalidArgumentException('SQLite compound window recursive affinity current-source cursor does not match next source');
            }
        }

}
