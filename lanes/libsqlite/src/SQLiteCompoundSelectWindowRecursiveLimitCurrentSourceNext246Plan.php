<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Plan
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
        $handoff = self::sourceHandoff($base);
        self::validateCursor($cursor, $handoff);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next246-ready';
        $base['compoundRecursiveLimitSourceHandoffNext246'] = $handoff;
        $base['cursor']['sourceHandoffTokenNext246'] = $handoff['sourceHandoffToken'];
        $base['cursor']['recursiveLimitCursorTokenNext246'] = $handoff['recursiveLimitCursorToken'];
        $base['cursor']['currentSourceSignatureNext246'] = $handoff['currentSourceSignature'];
        $base['cursor']['requiredSourceHandoffAcksNext246'] = $handoff['requiredSourceHandoffAcks'];
        $base['cursor']['nextExposureNext246'] = $handoff['nextExposure'];
        $base['replanReasons'][] = 'compound-recursive-limit-current-source-handoff-next246';
        $base['replanReasons'][] = 'window-replay-and-spillover-acks-before-next-source-next246';
        $base['dependencies'][] = 'sqlite-compound-recursive-limit-current-source-handoff-next246';
        $base['dependency_closure'] = 'no new support component needed; next246 reuses accepted compound SELECT recursive LIMIT/OFFSET, spillover drain, and window replay tickets, then adds a current-source handoff fence before next-source exposure';
        $base['non_overlap'] = 'next246 extends accepted next243 replay tickets by requiring a combined current-source handoff over recursive LIMIT cursor exhaustion, final-page spillover, and window replay tickets; it avoids accepted next240 spillover-only, next243 replay-only, next242 commit-fence, JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, encoding, and suite evidence clusters';

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
    private static function sourceHandoff(array $plan): array
    {
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $spillover = is_array($plan['compoundFinalPageSpilloverDrainNext240'] ?? null) ? $plan['compoundFinalPageSpilloverDrainNext240'] : [];
        $replay = is_array($plan['compoundWindowReplayFenceNext243'] ?? null) ? $plan['compoundWindowReplayFenceNext243'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];

        $currentLabels = self::stringList($sourceWindow['currentAdmittedLabels'] ?? []);
        $nextLabels = self::stringList($sourceWindow['nextAdmittedLabels'] ?? []);
        $spilloverLabels = self::stringList($spillover['currentSpilloverLabels'] ?? []);
        $replayTickets = self::stringList($replay['requiredReplayTickets'] ?? []);
        $recursiveEmitted = self::stringList($recursiveQueue['currentEmittedLabels'] ?? []);
        $recursiveSkipped = self::stringList($recursiveQueue['currentSkippedLabels'] ?? []);
        $limitRemaining = (int) ($recursiveQueue['currentLimitRemaining'] ?? 0);
        $offsetRemaining = (int) ($recursiveQueue['currentOffsetRemaining'] ?? 0);

        $recursiveLimitCursorToken = self::token([
            'name' => (string) ($recursiveQueue['name'] ?? ''),
            'operator' => (string) ($recursiveQueue['operator'] ?? ''),
            'emitted' => $recursiveEmitted,
            'skipped' => $recursiveSkipped,
            'limitRemaining' => $limitRemaining,
            'offsetRemaining' => $offsetRemaining,
            'finalLimit' => (int) ($compound['limit'] ?? 0),
            'finalOffset' => (int) ($compound['offset'] ?? 0),
        ]);
        $currentSourceSignature = self::token([
            'currentToken' => (string) ($sourceWindow['currentToken'] ?? ''),
            'windowReplayToken' => (string) ($replay['windowReplayToken'] ?? ''),
            'spilloverDrainToken' => (string) ($spillover['spilloverDrainToken'] ?? ''),
            'currentLabels' => $currentLabels,
            'spilloverLabels' => $spilloverLabels,
            'replayTickets' => $replayTickets,
        ]);
        $nextSourceCandidateToken = self::token([
            'nextToken' => (string) ($sourceWindow['nextToken'] ?? ''),
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []),
        ]);
        $requiredAcks = [
            'recursive-limit:' . $recursiveLimitCursorToken,
            'current-source:' . $currentSourceSignature,
            'next-candidate:' . $nextSourceCandidateToken,
        ];
        $sourceHandoffToken = self::token([
            'recursiveLimitCursorToken' => $recursiveLimitCursorToken,
            'currentSourceSignature' => $currentSourceSignature,
            'nextSourceCandidateToken' => $nextSourceCandidateToken,
            'requiredAcks' => $requiredAcks,
        ]);
        $complete = $limitRemaining === 0 && $offsetRemaining === 0 && $currentLabels !== [] && $replayTickets !== [];

        return [
            'sourceHandoffToken' => $sourceHandoffToken,
            'recursiveLimitCursorToken' => $recursiveLimitCursorToken,
            'currentSourceSignature' => $currentSourceSignature,
            'nextSourceCandidateToken' => $nextSourceCandidateToken,
            'requiredSourceHandoffAcks' => $requiredAcks,
            'requiredSourceHandoffAckCount' => count($requiredAcks),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'nextOnlyLabels' => self::stringList($sourceWindow['nextOnlyAdmittedLabels'] ?? []),
            'currentOnlyLabels' => self::stringList($sourceWindow['currentOnlyAdmittedLabels'] ?? []),
            'recursiveEmittedLabels' => $recursiveEmitted,
            'recursiveSkippedLabels' => $recursiveSkipped,
            'recursiveLimitRemaining' => $limitRemaining,
            'recursiveOffsetRemaining' => $offsetRemaining,
            'recursiveLimitExhausted' => $limitRemaining === 0 && $offsetRemaining === 0,
            'spilloverLabels' => $spilloverLabels,
            'spilloverAckCount' => (int) ($spillover['requiredSpilloverAckCount'] ?? 0),
            'replayTicketCount' => count($replayTickets),
            'currentSourceComplete' => $complete,
            'nextExposure' => 'held-until-current-source-recursive-limit-window-handoff-acks',
            'yieldBoundary' => 'compound-window-recursive-next246-current-source-handoff',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $handoff
     */
    private static function validateCursor(?array $cursor, array $handoff): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'sourceHandoffTokenNext246' => 'sourceHandoffToken',
            'recursiveLimitCursorTokenNext246' => 'recursiveLimitCursorToken',
            'currentSourceSignatureNext246' => 'currentSourceSignature',
        ] as $cursorKey => $handoffKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $handoff[$handoffKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next246 cursor does not match current-source handoff');
            }
        }
        if (!array_key_exists('acknowledgedSourceHandoffAcksNext246', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedSourceHandoffAcksNext246'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next246 source-handoff acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedSourceHandoffAcksNext246']));
        $required = self::stringList($handoff['requiredSourceHandoffAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next246 source-handoff acknowledgements do not match required current-source set');
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

    /** @param mixed $payload */
    private static function token(mixed $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
