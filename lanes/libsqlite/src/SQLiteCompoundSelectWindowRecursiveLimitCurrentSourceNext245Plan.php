<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext245Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext243Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $promotion = self::promotionSnapshot($base);
        self::validateCursor($cursor, $promotion);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next245-ready';
        $base['compoundNextSourcePromotionSnapshotNext245'] = $promotion;
        $base['cursor']['promotionSnapshotTokenNext245'] = $promotion['promotionSnapshotToken'];
        $base['cursor']['nextSourceDeltaTokenNext245'] = $promotion['nextSourceDeltaToken'];
        $base['cursor']['requiredPromotionTicketsNext245'] = $promotion['requiredPromotionTickets'];
        $base['cursor']['nextExposureNext245'] = $promotion['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-next-source-promotion-snapshot-next245';
        $base['replanReasons'][] = 'next-source-held-until-current-replay-and-delta-snapshot-next245';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-next-source-promotion-snapshot-next245';
        $base['dependency_closure'] = 'no new support component needed; next245 reuses accepted compound SELECT recursive LIMIT/OFFSET, window replay tickets, and final-page spillover fences, then adds a next-source promotion snapshot for changed WordPress option rows';
        $base['non_overlap'] = 'next245 extends accepted next243 replay-ticket behavior by binding the next-source row delta to a promotion snapshot after current replay is acknowledged; it avoids accepted next242 commit fences, next243 replay tickets alone, batch212 next242/next243 behavior, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, row-value, encoding, and suite evidence clusters';

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
            'currentDequeueTokenNext237',
            'acknowledgedCurrentDequeueAcksNext237',
            'spilloverDrainTokenNext240',
            'acknowledgedSpilloverAcksNext240',
            'windowReplayTokenNext243',
            'currentReplaySignatureNext243',
            'acknowledgedReplayTicketsNext243',
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
    private static function promotionSnapshot(array $plan): array
    {
        $replay = is_array($plan['compoundWindowReplayFenceNext243'] ?? null) ? $plan['compoundWindowReplayFenceNext243'] : [];
        $spillover = is_array($plan['compoundFinalPageSpilloverDrainNext240'] ?? null) ? $plan['compoundFinalPageSpilloverDrainNext240'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $nextOnly = self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []);
        $currentOnly = self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []);
        $changed = [
            'nextOnlyLabels' => $nextOnly,
            'currentOnlyLabels' => $currentOnly,
            'currentLabels' => self::labels($currentRows),
            'nextLabels' => self::labels($nextRows),
        ];
        $deltaToken = self::token([
            'changed' => $changed,
            'currentRows' => self::rowFrames($currentRows),
            'nextRows' => self::rowFrames($nextRows),
            'spilloverToken' => (string) ($spillover['spilloverDrainToken'] ?? ''),
            'replayToken' => (string) ($replay['windowReplayToken'] ?? ''),
        ]);
        $promotionToken = self::token([
            'deltaToken' => $deltaToken,
            'replaySignature' => (string) ($replay['currentReplaySignature'] ?? ''),
            'requiredReplayTickets' => self::stringList($replay['requiredReplayTickets'] ?? []),
            'changed' => $changed,
        ]);

        $tickets = [];
        foreach ($nextOnly as $index => $label) {
            $tickets[] = 'next:' . self::token([
                'promotionToken' => $promotionToken,
                'ordinal' => $index + 1,
                'label' => $label,
            ]);
        }
        foreach ($currentOnly as $index => $label) {
            $tickets[] = 'current:' . self::token([
                'promotionToken' => $promotionToken,
                'ordinal' => $index + 1,
                'label' => $label,
            ]);
        }

        return [
            'promotionSnapshotToken' => $promotionToken,
            'nextSourceDeltaToken' => $deltaToken,
            'requiredPromotionTickets' => $tickets,
            'requiredPromotionTicketCount' => count($tickets),
            'currentLabels' => $changed['currentLabels'],
            'nextLabels' => $changed['nextLabels'],
            'nextOnlyLabels' => $nextOnly,
            'currentOnlyLabels' => $currentOnly,
            'currentReplaySignature' => (string) ($replay['currentReplaySignature'] ?? ''),
            'windowReplayToken' => (string) ($replay['windowReplayToken'] ?? ''),
            'spilloverDrainToken' => (string) ($spillover['spilloverDrainToken'] ?? ''),
            'changedRowCount' => count($nextOnly) + count($currentOnly),
            'nextExposure' => 'held-until-current-replay-and-next-delta-snapshot-match',
            'yieldBoundary' => 'compound-window-recursive-next245-next-source-promotion-snapshot',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $promotion
     */
    private static function validateCursor(?array $cursor, array $promotion): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'promotionSnapshotTokenNext245' => 'promotionSnapshotToken',
            'nextSourceDeltaTokenNext245' => 'nextSourceDeltaToken',
        ] as $cursorKey => $promotionKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $promotion[$promotionKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next245 cursor does not match next-source promotion snapshot');
            }
        }
        if (!array_key_exists('acknowledgedPromotionTicketsNext245', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedPromotionTicketsNext245'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next245 promotion tickets must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ticket): string => (string) $ticket, $cursor['acknowledgedPromotionTicketsNext245']));
        $required = self::stringList($promotion['requiredPromotionTickets'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next245 promotion tickets do not match changed next-source row set');
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

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function rowFrames(array $rows): array
    {
        $frames = [];
        foreach ($rows as $index => $row) {
            $frames[] = [
                'ordinal' => $index + 1,
                'id' => $row['id'] ?? $row['option_id'] ?? null,
                'label' => self::label($row),
                'metric' => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null,
            ];
        }

        return $frames;
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
