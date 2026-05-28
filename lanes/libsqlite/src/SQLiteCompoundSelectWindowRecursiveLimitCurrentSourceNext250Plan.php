<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext250Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $admission = self::nextPageAdmission($base);
        self::validateCursor($cursor, $admission);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next250-ready';
        $base['compoundCurrentSourceNextPageAdmissionNext250'] = $admission;
        $base['cursor']['nextPageAdmissionTokenNext250'] = $admission['nextPageAdmissionToken'];
        $base['cursor']['currentSourceResumeTokenNext250'] = $admission['currentSourceResumeToken'];
        $base['cursor']['requiredNextPageAdmissionAcksNext250'] = $admission['requiredNextPageAdmissionAcks'];
        $base['cursor']['nextExposureNext250'] = $admission['nextExposure'];
        $base['replanReasons'][] = 'compound-recursive-window-current-source-next-page-admission-next250';
        $base['replanReasons'][] = 'recursive-limit-handoff-acks-before-next-page-next250';
        $base['dependencies'][] = 'sqlite-compound-recursive-limit-next-page-admission-next250';
        $base['dependency_closure'] = 'no new support component needed; next250 reuses accepted compound SELECT execution, recursive CTE LIMIT/OFFSET exhaustion, window replay, spillover drain, and current-source handoff tokens';
        $base['non_overlap'] = 'next250 extends accepted next246 current-source handoff with a next-page admission fence after recursive LIMIT/OFFSET exhaustion; it avoids accepted next246 handoff-only behavior, next243 replay-only behavior, next240 spillover-only behavior, JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, encoding, and suite evidence clusters';

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
            'sourceHandoffTokenNext246',
            'recursiveLimitCursorTokenNext246',
            'currentSourceSignatureNext246',
            'acknowledgedSourceHandoffAcksNext246',
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
    private static function nextPageAdmission(array $plan): array
    {
        $handoff = is_array($plan['compoundRecursiveLimitSourceHandoffNext246'] ?? null) ? $plan['compoundRecursiveLimitSourceHandoffNext246'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $limitTrace = is_array($plan['limitTrace'] ?? null) ? $plan['limitTrace'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $requiredHandoffAcks = self::stringList($handoff['requiredSourceHandoffAcks'] ?? []);
        $recursiveEmitted = self::stringList($handoff['recursiveEmittedLabels'] ?? []);
        $recursiveSkipped = self::stringList($handoff['recursiveSkippedLabels'] ?? []);
        $currentLabels = self::stringList($handoff['currentLabels'] ?? []);
        $nextLabels = self::stringList($handoff['nextLabels'] ?? []);
        $currentPage = self::pageFrame($currentRows);
        $nextPage = self::pageFrame($nextRows);

        $currentSourceResumeToken = self::token([
            'sourceHandoffToken' => (string) ($handoff['sourceHandoffToken'] ?? ''),
            'recursiveLimitCursorToken' => (string) ($handoff['recursiveLimitCursorToken'] ?? ''),
            'currentSourceSignature' => (string) ($handoff['currentSourceSignature'] ?? ''),
            'recursiveEmitted' => $recursiveEmitted,
            'recursiveSkipped' => $recursiveSkipped,
            'currentPage' => $currentPage,
        ]);
        $nextPageCandidateToken = self::token([
            'nextSourceCandidateToken' => (string) ($handoff['nextSourceCandidateToken'] ?? ''),
            'nextPage' => $nextPage,
            'nextOnlyLabels' => self::stringList($handoff['nextOnlyLabels'] ?? []),
        ]);
        $nextPageAdmissionToken = self::token([
            'resume' => $currentSourceResumeToken,
            'candidate' => $nextPageCandidateToken,
            'requiredHandoffAcks' => $requiredHandoffAcks,
        ]);

        $required = [
            'source-handoff:' . (string) ($handoff['sourceHandoffToken'] ?? ''),
            'resume:' . $currentSourceResumeToken,
            'next-page:' . $nextPageCandidateToken,
        ];

        return [
            'nextPageAdmissionToken' => $nextPageAdmissionToken,
            'currentSourceResumeToken' => $currentSourceResumeToken,
            'nextPageCandidateToken' => $nextPageCandidateToken,
            'requiredNextPageAdmissionAcks' => $required,
            'requiredNextPageAdmissionAckCount' => count($required),
            'requiredSourceHandoffAckCount' => count($requiredHandoffAcks),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'currentPageFrame' => $currentPage,
            'nextPageFrame' => $nextPage,
            'recursiveEmittedLabels' => $recursiveEmitted,
            'recursiveSkippedLabels' => $recursiveSkipped,
            'recursiveLimitExhausted' => (bool) ($handoff['recursiveLimitExhausted'] ?? false),
            'currentPreLimitCount' => (int) ($limitTrace['current']['preLimitCount'] ?? 0),
            'nextPreLimitCount' => (int) ($limitTrace['next']['preLimitCount'] ?? 0),
            'sourceWindowCurrentToken' => (string) ($sourceWindow['currentToken'] ?? ''),
            'sourceWindowNextToken' => (string) ($sourceWindow['nextToken'] ?? ''),
            'nextExposure' => 'held-until-current-source-handoff-and-resume-page-acks',
            'yieldBoundary' => 'compound-window-recursive-next250-next-page-admission',
        ];
    }

    /**
     * @param array<string,mixed>|null $cursor
     * @param array<string,mixed> $admission
     */
    private static function validateCursor(?array $cursor, array $admission): void
    {
        if ($cursor === null) {
            return;
        }
        foreach ([
            'nextPageAdmissionTokenNext250' => 'nextPageAdmissionToken',
            'currentSourceResumeTokenNext250' => 'currentSourceResumeToken',
        ] as $cursorKey => $admissionKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $admission[$admissionKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next250 cursor does not match next-page admission state');
            }
        }
        if (!array_key_exists('acknowledgedNextPageAdmissionAcksNext250', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedNextPageAdmissionAcksNext250'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next250 admission acknowledgements must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $ack): string => (string) $ack, $cursor['acknowledgedNextPageAdmissionAcksNext250']));
        $required = self::stringList($admission['requiredNextPageAdmissionAcks'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next250 admission acknowledgements do not match required next-page set');
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

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private static function pageFrame(array $rows): array
    {
        $frame = [];
        foreach ($rows as $index => $row) {
            $frame[] = [
                'ordinal' => $index + 1,
                'id' => $row['id'] ?? $row['option_id'] ?? null,
                'label' => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''),
                'metric' => $row['metric'] ?? $row['rank'] ?? $row['bucket'] ?? null,
            ];
        }

        return $frame;
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
