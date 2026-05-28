<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext243Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext240Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $replay = self::replayFence($base);
        self::validateCursor($cursor, $replay);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next243-ready';
        $base['compoundWindowReplayFenceNext243'] = $replay;
        $base['cursor']['windowReplayTokenNext243'] = $replay['windowReplayToken'];
        $base['cursor']['currentReplaySignatureNext243'] = $replay['currentReplaySignature'];
        $base['cursor']['requiredReplayTicketsNext243'] = $replay['requiredReplayTickets'];
        $base['cursor']['nextExposureNext243'] = $replay['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-current-replay-ticket-next243';
        $base['replanReasons'][] = 'next-source-held-until-window-metric-lineage-replayed-next243';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-replay-ticket-next243';
        $base['dependency_closure'] = 'no new support component needed; next243 reuses accepted compound SELECT recursive LIMIT/OFFSET, window row metrics, spillover drain, and adds a current-row replay-ticket fence before next-source promotion';
        $base['non_overlap'] = 'next243 extends accepted next240 spillover drain by binding final current rows to window metric and recursive lineage replay tickets; it avoids accepted next240 spillover-only acknowledgement, next238 source-generation seal, next226 aggregate EXCEPT/INTERSECT behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters';

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
        foreach (['currentToken', 'currentDequeueTokenNext237', 'acknowledgedCurrentDequeueAcksNext237', 'spilloverDrainTokenNext240', 'acknowledgedSpilloverAcksNext240'] as $key) {
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
    private static function replayFence(array $plan): array
    {
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $spillover = is_array($plan['compoundFinalPageSpilloverDrainNext240'] ?? null) ? $plan['compoundFinalPageSpilloverDrainNext240'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];

        $rowFrames = [];
        foreach ($currentRows as $index => $row) {
            $rowFrames[] = [
                'ordinal' => $index + 1,
                'id' => $row['id'] ?? $row['option_id'] ?? null,
                'label' => self::label($row),
                'metric' => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null,
            ];
        }

        $payload = [
            'currentToken' => (string) ($sourceWindow['currentToken'] ?? ''),
            'spilloverToken' => (string) ($spillover['spilloverDrainToken'] ?? ''),
            'rows' => $rowFrames,
            'recursiveEmitted' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'recursiveSkipped' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
            'spilloverLabels' => self::stringList($spillover['currentSpilloverLabels'] ?? []),
            'nextLabels' => self::labels($nextRows),
        ];
        $replayToken = self::token($payload);
        $currentReplaySignature = self::token([
            'rows' => $rowFrames,
            'recursiveLineage' => $payload['recursiveEmitted'],
            'spilloverToken' => $payload['spilloverToken'],
        ]);

        $tickets = [];
        foreach ($rowFrames as $frame) {
            $tickets[] = self::token([
                'replayToken' => $replayToken,
                'row' => $frame,
                'lineage' => $payload['recursiveEmitted'],
            ]);
        }

        return [
            'windowReplayToken' => $replayToken,
            'currentReplaySignature' => $currentReplaySignature,
            'requiredReplayTickets' => $tickets,
            'requiredReplayTicketCount' => count($tickets),
            'currentReplayRows' => $rowFrames,
            'currentLabels' => self::labels($currentRows),
            'nextLabels' => self::labels($nextRows),
            'nextOnlyLabels' => self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []),
            'currentOnlyLabels' => self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []),
            'recursiveEmittedLabels' => $payload['recursiveEmitted'],
            'recursiveSkippedLabels' => $payload['recursiveSkipped'],
            'spilloverLabels' => $payload['spilloverLabels'],
            'spilloverDrainToken' => $payload['spilloverToken'],
            'nextExposure' => 'held-until-current-window-replay-tickets-match',
            'yieldBoundary' => 'compound-window-recursive-next243-current-replay-fence',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $replay
     */
    private static function validateCursor(?array $cursor, array $replay): void
    {
        if ($cursor === null) {
            return;
        }
        if (isset($cursor['windowReplayTokenNext243']) && $cursor['windowReplayTokenNext243'] !== $replay['windowReplayToken']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next243 cursor does not match window replay token');
        }
        if (isset($cursor['currentReplaySignatureNext243']) && $cursor['currentReplaySignatureNext243'] !== $replay['currentReplaySignature']) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next243 cursor does not match current replay signature');
        }
        if (!array_key_exists('acknowledgedReplayTicketsNext243', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedReplayTicketsNext243'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next243 replay tickets must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ticket): string => (string) $ticket, $cursor['acknowledgedReplayTicketsNext243']));
        $required = self::stringList($replay['requiredReplayTickets'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next243 replay tickets do not match current window row set');
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
        return array_values(array_map(static fn (array $row): string => self::label($row), $rows));
    }

    /** @param array<string,mixed> $row */
    private static function label(array $row): string
    {
        return (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? '');
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
