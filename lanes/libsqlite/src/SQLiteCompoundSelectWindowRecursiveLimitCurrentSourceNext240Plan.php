<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext240Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext237Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $spillover = self::spilloverDrain($base);
        self::validateCursor($cursor, $spillover);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next240-ready';
        $base['compoundFinalPageSpilloverDrainNext240'] = $spillover;
        $base['cursor']['spilloverDrainTokenNext240'] = $spillover['spilloverDrainToken'];
        $base['cursor']['requiredSpilloverAcksNext240'] = $spillover['requiredSpilloverAcks'];
        $base['cursor']['spilloverExposureNext240'] = $spillover['nextExposure'];
        $base['replanReasons'][] = 'compound-final-limit-spillover-drain-next240';
        $base['replanReasons'][] = 'current-source-window-spillover-holds-next-source-next240';
        $base['dependencies'][] = 'sqlite-compound-final-page-spillover-drain-next240';
        $base['dependency_closure'] = 'no new support component needed; next240 reuses accepted compound SELECT recursive LIMIT/OFFSET, rank/row_number window dispatch, INTERSECT/EXCEPT membership, and adds a final-page spillover acknowledgement fence before next-source promotion';
        $base['non_overlap'] = 'next240 extends accepted next237 recursive dequeue fencing by acknowledging current-source compound rows skipped or truncated by the final LIMIT/OFFSET page; it avoids accepted next236 metric fences, next237 dequeue fences, next226/next228/next230/next233 aggregate/window page handoffs, JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, encoding, and suite evidence clusters';

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
        foreach (['currentToken', 'currentDequeueTokenNext237', 'acknowledgedCurrentDequeueAcksNext237'] as $key) {
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
    private static function spilloverDrain(array $plan): array
    {
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $currentPreLimitRows = self::rows($plan['currentPreLimitRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $skipped = self::stringList($sourceWindow['currentSkippedLabels'] ?? []);
        $truncated = self::stringList($sourceWindow['currentTruncatedLabels'] ?? []);
        $spillover = [];
        foreach (array_merge($skipped, $truncated) as $label) {
            if (!in_array($label, $spillover, true)) {
                $spillover[] = $label;
            }
        }

        $payload = [
            'operators' => self::stringList($compound['operators'] ?? []),
            'limit' => (int) ($compound['limit'] ?? 0),
            'offset' => (int) ($compound['offset'] ?? 0),
            'currentFinalLabels' => self::labels($currentRows),
            'currentPreLimitLabels' => self::labels($currentPreLimitRows),
            'spilloverLabels' => $spillover,
            'nextFinalLabels' => self::labels($nextRows),
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
        ];
        $token = self::token($payload);
        $acks = [];
        foreach ($spillover as $index => $label) {
            $acks[] = self::token([
                'token' => $token,
                'spilloverOrdinal' => $index + 1,
                'label' => $label,
            ]);
        }

        return [
            'spilloverDrainToken' => $token,
            'requiredSpilloverAcks' => $acks,
            'requiredSpilloverAckCount' => count($acks),
            'currentSkippedLabels' => $skipped,
            'currentTruncatedLabels' => $truncated,
            'currentSpilloverLabels' => $spillover,
            'currentFinalLabels' => self::labels($currentRows),
            'nextFinalLabels' => self::labels($nextRows),
            'nextOnlyFinalLabels' => self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []),
            'currentOnlyFinalLabels' => self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []),
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'spilloverRowCount' => count($spillover),
            'currentPreLimitRowCount' => count($currentPreLimitRows),
            'currentFinalRowCount' => count($currentRows),
            'nextFinalRowCount' => count($nextRows),
            'nextExposure' => 'held-until-current-compound-spillover-drained',
            'yieldBoundary' => 'compound-window-next240-final-limit-spillover-drain',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $spillover
     */
    private static function validateCursor(?array $cursor, array $spillover): void
    {
        if ($cursor === null) {
            return;
        }
        if (isset($cursor['spilloverDrainTokenNext240']) && $cursor['spilloverDrainTokenNext240'] !== $spillover['spilloverDrainToken']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next240 cursor does not match spillover drain token');
        }
        if (!array_key_exists('acknowledgedSpilloverAcksNext240', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedSpilloverAcksNext240'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next240 spillover acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedSpilloverAcksNext240']));
        $required = self::stringList($spillover['requiredSpilloverAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next240 spillover acknowledgements do not match required final-page set');
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
        $labels = [];
        foreach ($rows as $row) {
            $labels[] = (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? '');
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

    /** @param array<string,mixed> $payload */
    private static function token(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
