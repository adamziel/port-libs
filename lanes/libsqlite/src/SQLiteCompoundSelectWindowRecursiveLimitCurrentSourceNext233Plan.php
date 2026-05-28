<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext233Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext230Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $resume = self::resumeFence($base);
        self::validateCursor($cursor, $resume);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next233-ready';
        $base['currentSourceResumeNext233'] = $resume;
        $base['cursor']['currentResumeToken'] = $resume['currentResumeToken'];
        $base['cursor']['requiredCurrentOrdinalAcks'] = $resume['requiredCurrentOrdinalAcks'];
        $base['cursor']['nextExposure'] = $resume['nextExposure'];
        $base['replanReasons'][] = 'compound-window-current-final-order-ordinal-resume-next233';
        $base['replanReasons'][] = 'next-source-compound-page-held-until-current-ordinal-acks-next233';
        $base['dependencies'][] = 'sqlite-compound-window-current-source-resume-ordinal-next233';
        $base['dependency_closure'] = 'no new support component needed; next233 reuses accepted next230 compound SELECT, recursive LIMIT/OFFSET, avg/first_value windows, current-source tokens, and adds a bounded final-order ordinal acknowledgement contract before next-source rows are exposed';
        $base['non_overlap'] = 'next233 extends accepted next230 avg/first_value UNION/INTERSECT/EXCEPT fencing with a final ORDER BY ordinal resume token for current-source page handoff; it avoids accepted next228 drain acknowledgements, suite next233 evidence, JSON table, WAL/VFS, B-tree, encoding, planner range-cost, trigger, and PRAGMA surfaces';

        return $base;
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>|null
     */
    private static function baseCursor(?array $cursor): ?array
    {
        if ($cursor === null || !isset($cursor['currentToken'])) {
            return null;
        }

        return ['currentToken' => $cursor['currentToken']];
    }

    /**
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function resumeFence(array $plan): array
    {
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $currentToken = (string) ($sourceWindow['currentToken'] ?? '');
        $nextToken = (string) ($sourceWindow['nextToken'] ?? '');
        $currentOrdinals = self::ordinalAcks($currentRows, $currentToken);
        $nextOnly = self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []);
        $payload = [
            'currentToken' => $currentToken,
            'currentOrder' => self::orderedLabels($currentRows),
            'nextOrder' => self::orderedLabels($nextRows),
            'requiredOrdinals' => $currentOrdinals,
            'nextOnly' => $nextOnly,
        ];
        $resumeToken = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'currentResumeToken' => $resumeToken,
            'requiredCurrentOrdinalAcks' => $currentOrdinals,
            'requiredAckCount' => count($currentOrdinals),
            'currentFinalOrderLabels' => self::orderedLabels($currentRows),
            'nextFinalOrderLabels' => self::orderedLabels($nextRows),
            'currentLastOrdinal' => count($currentRows),
            'nextFirstOrdinal' => $nextRows === [] ? null : 1,
            'nextOnlyLabels' => $nextOnly,
            'currentOnlyLabels' => self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []),
            'currentToken' => $currentToken,
            'nextToken' => $nextToken,
            'tokensDiffer' => $currentToken !== $nextToken,
            'nextExposure' => 'held-until-current-final-order-ordinals-acked',
            'yieldBoundary' => 'compound-window-next233-current-final-order-resume-fences-next-source',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $resume
     */
    private static function validateCursor(?array $cursor, array $resume): void
    {
        if ($cursor === null) {
            return;
        }
        if (isset($cursor['currentResumeToken']) && $cursor['currentResumeToken'] !== $resume['currentResumeToken']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next233 cursor does not match current resume token');
        }
        if (!array_key_exists('acknowledgedCurrentOrdinalAcks', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCurrentOrdinalAcks'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next233 acknowledged ordinals must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedCurrentOrdinalAcks']));
        $required = self::stringList($resume['requiredCurrentOrdinalAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next233 current ordinal acknowledgements do not match required resume set');
        }
    }

    /**
     * @param mixed $value
     * @return list<array<string,mixed>>
     */
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function orderedLabels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''), $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function ordinalAcks(array $rows, string $currentToken): array
    {
        $acks = [];
        foreach ($rows as $index => $row) {
            $acks[] = hash('sha256', json_encode([
                'token' => $currentToken,
                'ordinal' => $index + 1,
                'label' => (string) ($row['label'] ?? ''),
                'row' => $row,
            ], JSON_THROW_ON_ERROR));
        }

        return $acks;
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
}
