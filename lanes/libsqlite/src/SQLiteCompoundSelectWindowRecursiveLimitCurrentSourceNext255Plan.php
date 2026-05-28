<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext255Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext250Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $resume = self::continuationResume($base);
        self::validateCursor($cursor, $resume);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next255-ready';
        $base['compoundWindowRecursiveContinuationResumeNext255'] = $resume;
        $base['cursor']['continuationResumeTokenNext255'] = $resume['continuationResumeToken'];
        $base['cursor']['currentContinuationTokenNext255'] = $resume['currentContinuationToken'];
        $base['cursor']['nextContinuationTokenNext255'] = $resume['nextContinuationToken'];
        $base['cursor']['requiredContinuationAcksNext255'] = $resume['requiredContinuationAcks'];
        $base['cursor']['nextExposureNext255'] = $resume['nextExposure'];
        $base['replanReasons'][] = 'compound-recursive-window-continuation-resume-next255';
        $base['replanReasons'][] = 'compound-limit-held-until-current-and-next-continuation-acks-next255';
        $base['dependencies'][] = 'sqlite-compound-recursive-window-continuation-resume-next255';
        $base['dependency_closure'] = 'no new support component needed; next255 reuses accepted compound SELECT execution, recursive LIMIT/OFFSET tracing, per-arm window output, next246 handoff, and next250 next-page admission tokens';
        $base['non_overlap'] = 'next255 extends accepted next250 next-page admission with a continuation resume fence over current page labels, held next page labels, recursive emitted/skipped lineage, and current/next spillover labels; it avoids accepted next250 admission-only behavior, next248/next249 promotion fences, JSON table, WAL/VFS, B-tree, planner, trigger, PRAGMA, encoding, and suite evidence clusters';

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
            'nextPageAdmissionTokenNext250',
            'currentSourceResumeTokenNext250',
            'acknowledgedNextPageAdmissionAcksNext250',
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
    private static function continuationResume(array $plan): array
    {
        $admission = is_array($plan['compoundCurrentSourceNextPageAdmissionNext250'] ?? null) ? $plan['compoundCurrentSourceNextPageAdmissionNext250'] : [];
        $handoff = is_array($plan['compoundRecursiveLimitSourceHandoffNext246'] ?? null) ? $plan['compoundRecursiveLimitSourceHandoffNext246'] : [];
        $sourceWindow = is_array($plan['sourceWindow'] ?? null) ? $plan['sourceWindow'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);

        $currentLabels = self::labels($currentRows);
        $nextLabels = self::labels($nextRows);
        $currentMetrics = self::metrics($currentRows);
        $nextMetrics = self::metrics($nextRows);
        $currentSpillover = self::strings($sourceWindow['currentTruncatedLabels'] ?? []);
        $nextSpillover = self::strings($sourceWindow['nextTruncatedLabels'] ?? []);
        $recursiveLineage = [
            'emitted' => self::strings($handoff['recursiveEmittedLabels'] ?? $admission['recursiveEmittedLabels'] ?? []),
            'skipped' => self::strings($handoff['recursiveSkippedLabels'] ?? $admission['recursiveSkippedLabels'] ?? []),
            'limitExhausted' => (bool) ($admission['recursiveLimitExhausted'] ?? $handoff['recursiveLimitExhausted'] ?? false),
        ];

        $currentContinuationToken = self::token([
            'resumeToken' => (string) ($admission['currentSourceResumeToken'] ?? ''),
            'labels' => $currentLabels,
            'metrics' => $currentMetrics,
            'spillover' => $currentSpillover,
            'lineage' => $recursiveLineage,
        ]);
        $nextContinuationToken = self::token([
            'candidateToken' => (string) ($admission['nextPageCandidateToken'] ?? ''),
            'labels' => $nextLabels,
            'metrics' => $nextMetrics,
            'spillover' => $nextSpillover,
            'nextOnlyLabels' => self::strings($handoff['nextOnlyLabels'] ?? []),
            'lineage' => $recursiveLineage,
        ]);
        $continuationResumeToken = self::token([
            'admissionToken' => (string) ($admission['nextPageAdmissionToken'] ?? ''),
            'currentContinuationToken' => $currentContinuationToken,
            'nextContinuationToken' => $nextContinuationToken,
            'requiredAdmissionAcks' => self::strings($admission['requiredNextPageAdmissionAcks'] ?? []),
        ]);

        $required = [
            'admission:' . (string) ($admission['nextPageAdmissionToken'] ?? ''),
            'current-continuation:' . $currentContinuationToken,
            'next-continuation:' . $nextContinuationToken,
            'resume:' . $continuationResumeToken,
        ];

        return [
            'continuationResumeToken' => $continuationResumeToken,
            'currentContinuationToken' => $currentContinuationToken,
            'nextContinuationToken' => $nextContinuationToken,
            'requiredContinuationAcks' => $required,
            'requiredContinuationAckCount' => count($required),
            'currentLabels' => $currentLabels,
            'nextLabels' => $nextLabels,
            'currentMetrics' => $currentMetrics,
            'nextMetrics' => $nextMetrics,
            'currentSpilloverLabels' => $currentSpillover,
            'nextSpilloverLabels' => $nextSpillover,
            'recursiveLineage' => $recursiveLineage,
            'labelsChanged' => $currentLabels !== $nextLabels,
            'metricsChanged' => $currentMetrics !== $nextMetrics,
            'spilloverChanged' => $currentSpillover !== $nextSpillover,
            'nextExposure' => 'held-until-compound-window-recursive-continuation-resume-acks',
            'yieldBoundary' => 'compound-window-recursive-next255-continuation-resume',
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
        foreach ([
            'continuationResumeTokenNext255' => 'continuationResumeToken',
            'currentContinuationTokenNext255' => 'currentContinuationToken',
            'nextContinuationTokenNext255' => 'nextContinuationToken',
        ] as $cursorKey => $resumeKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $resume[$resumeKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next255 cursor does not match continuation resume state');
            }
        }
        if (!array_key_exists('acknowledgedContinuationAcksNext255', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedContinuationAcksNext255'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next255 continuation acknowledgements must be a list');
        }

        $acknowledged = self::strings($cursor['acknowledgedContinuationAcksNext255']);
        $required = self::strings($resume['requiredContinuationAcks'] ?? []);
        if (array_values(array_diff($required, $acknowledged)) !== [] || array_values(array_diff($acknowledged, $required)) !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next255 continuation acknowledgements do not match required current/next resume set');
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
        return array_values(array_map(static fn (array $row): string => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''), $rows));
    }

    /** @param list<array<string,mixed>> $rows @return list<int|string|null> */
    private static function metrics(array $rows): array
    {
        return array_values(array_map(static fn (array $row): int|string|null => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null, $rows));
    }

    /** @param mixed $value @return list<string> */
    private static function strings(mixed $value): array
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
