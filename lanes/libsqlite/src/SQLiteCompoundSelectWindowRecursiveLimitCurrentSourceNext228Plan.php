<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext228Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext224Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $drain = self::drainFence($base);
        self::validateCursor($cursor, $drain);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next228-ready';
        $base['currentSourceDrainNext228'] = $drain;
        $base['cursor']['currentDrainToken'] = $drain['currentDrainToken'];
        $base['cursor']['requiredCurrentAcks'] = $drain['requiredCurrentAcks'];
        $base['cursor']['nextExposure'] = $drain['nextExposure'];
        $base['replanReasons'][] = 'compound-recursive-window-current-limited-page-drain-next228';
        $base['replanReasons'][] = 'next-source-window-rank-held-until-current-page-acks-next228';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-current-page-drain-next228';
        $base['dependency_closure'] = 'no new support component needed; next228 reuses accepted compound SELECT, recursive LIMIT/OFFSET, window ranking, current-source token fencing, and adds a bounded current-page drain acknowledgement contract';
        $base['non_overlap'] = 'next228 extends accepted next224 mixed compound/window/recursive LIMIT behavior by fencing next-source exposure until the current limited page has row-level acknowledgements; it avoids accepted next224 token fence, JSON table, WAL/VFS, B-tree, encoding, planner range-cost, and trigger surfaces';

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
    private static function drainFence(array $plan): array
    {
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $currentToken = (string) ($sourceWindow['currentToken'] ?? '');
        $nextToken = (string) ($sourceWindow['nextToken'] ?? '');
        $requiredAcks = self::ackTokens($currentRows, $currentToken);
        $nextOnly = self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []);
        $currentOnly = self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []);
        $skippedNext = self::stringList($sourceWindow['nextSkippedLabels'] ?? []);
        $payload = [
            'currentToken' => $currentToken,
            'currentRows' => $currentRows,
            'requiredAcks' => $requiredAcks,
            'recursiveSkipped' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'recursiveEmitted' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'nextSkipped' => $skippedNext,
        ];
        $drainToken = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'currentDrainToken' => $drainToken,
            'requiredCurrentAcks' => $requiredAcks,
            'requiredAckCount' => count($requiredAcks),
            'currentLabels' => self::labels($currentRows),
            'nextLabels' => self::labels($nextRows),
            'nextOnlyLabels' => $nextOnly,
            'currentOnlyLabels' => $currentOnly,
            'nextSkippedLabels' => $skippedNext,
            'currentToken' => $currentToken,
            'nextToken' => $nextToken,
            'tokensDiffer' => $currentToken !== $nextToken,
            'nextExposure' => 'held-until-current-page-drained',
            'yieldBoundary' => 'compound-recursive-window-next228-current-page-drain-fences-next-source',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $drain
     */
    private static function validateCursor(?array $cursor, array $drain): void
    {
        if ($cursor === null) {
            return;
        }
        if (isset($cursor['currentDrainToken']) && $cursor['currentDrainToken'] !== $drain['currentDrainToken']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next228 cursor does not match current drain token');
        }
        if (!array_key_exists('acknowledgedCurrentAcks', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedCurrentAcks'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next228 acknowledged current rows must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedCurrentAcks']));
        $required = self::stringList($drain['requiredCurrentAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next228 current-page acknowledgements do not match required drain set');
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
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function labels(array $rows): array
    {
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''), $rows));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function ackTokens(array $rows, string $currentToken): array
    {
        $tokens = [];
        foreach ($rows as $index => $row) {
            $tokens[] = hash('sha256', json_encode([
                'token' => $currentToken,
                'index' => $index,
                'row' => $row,
            ], JSON_THROW_ON_ERROR));
        }

        return $tokens;
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
