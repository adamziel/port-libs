<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext251Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $currentTables
     * @param array<string,list<array<string,mixed>>> $nextTables
     * @param array<string,mixed>|null $cursor
     * @return array<string,mixed>
     */
    public static function compare(string $sql, array $currentTables, array $nextTables, ?array $cursor = null): array
    {
        $base = SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext248Plan::compare($sql, $currentTables, $nextTables, self::baseCursor($cursor));
        $audit = self::deltaAudit($base);
        self::validateCursor($cursor, $audit);

        $base['status'] = 'compound-select-window-recursive-limit-current-source-next251-ready';
        $base['compoundNextSourceDeltaAuditFenceNext251'] = $audit;
        $base['cursor']['nextDeltaAuditTokenNext251'] = $audit['nextDeltaAuditToken'];
        $base['cursor']['nextDeltaAuditSignatureNext251'] = $audit['nextDeltaAuditSignature'];
        $base['cursor']['requiredDeltaAuditReceiptsNext251'] = $audit['requiredDeltaAuditReceipts'];
        $base['cursor']['deltaAuditExposureNext251'] = $audit['nextExposure'];
        $base['replanReasons'][] = 'compound-window-recursive-next-source-delta-audit-next251';
        $base['replanReasons'][] = 'next-source-held-until-compound-operator-and-final-page-audit-next251';
        $base['dependencies'][] = 'sqlite-compound-window-recursive-next-source-delta-audit-next251';
        $base['dependency_closure'] = 'no new support component needed; next251 reuses accepted compound SELECT recursive LIMIT/OFFSET, next248 promotion receipts, window replay tickets, and adds an operator/final-page delta audit fence';
        $base['non_overlap'] = 'next251 layers an operator and final-page ordinal audit over accepted next248 next-source promotion receipts; it avoids accepted next243 replay tickets, next248 promotion-only receipts, JSON table, WAL/VFS, B-tree, planner, PRAGMA, trigger, encoding, and suite evidence clusters';

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
            'nextPromotionTokenNext248',
            'nextDeltaSignatureNext248',
            'acknowledgedNextPromotionReceiptsNext248',
        ] as $key) {
            if (array_key_exists($key, $cursor)) {
                $base[$key] = $cursor[$key];
            }
        }

        return $base === [] ? null : $base;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private static function deltaAudit(array $plan): array
    {
        $promotion = is_array($plan['compoundNextSourcePromotionFenceNext248'] ?? null) ? $plan['compoundNextSourcePromotionFenceNext248'] : [];
        $compound = is_array($plan['compound'] ?? null) ? $plan['compound'] : [];
        $recursiveQueue = is_array($plan['recursiveQueue'] ?? null) ? $plan['recursiveQueue'] : [];
        $currentRows = self::rows($plan['currentRows'] ?? []);
        $nextRows = self::rows($plan['nextRows'] ?? []);
        $operatorTrace = self::operatorTrace($compound);
        $finalPage = self::finalPage($currentRows, $nextRows);
        $auditSignature = self::token([
            'operatorTrace' => $operatorTrace,
            'finalPage' => $finalPage,
            'promotionToken' => (string) ($promotion['nextPromotionToken'] ?? ''),
            'nextDeltaSignature' => (string) ($promotion['nextDeltaSignature'] ?? ''),
            'nextOnlyLabels' => self::stringList($promotion['nextOnlyLabels'] ?? []),
            'currentOnlyLabels' => self::stringList($promotion['currentOnlyLabels'] ?? []),
        ]);
        $auditToken = self::token([
            'auditSignature' => $auditSignature,
            'requiredNextPromotionReceipts' => self::stringList($promotion['requiredNextPromotionReceipts'] ?? []),
            'recursiveEmittedLabels' => self::stringList($recursiveQueue['currentEmittedLabels'] ?? []),
            'recursiveSkippedLabels' => self::stringList($recursiveQueue['currentSkippedLabels'] ?? []),
        ]);

        $receipts = [];
        foreach ($operatorTrace as $trace) {
            $receipts[] = self::token([
                'auditToken' => $auditToken,
                'kind' => 'compound-operator',
                'ordinal' => $trace['ordinal'],
                'operator' => $trace['operator'],
                'auditSignature' => $auditSignature,
            ]);
        }
        foreach ($finalPage as $row) {
            $receipts[] = self::token([
                'auditToken' => $auditToken,
                'kind' => 'final-page-row',
                'source' => $row['source'],
                'ordinal' => $row['ordinal'],
                'label' => $row['label'],
                'metric' => $row['metric'],
                'auditSignature' => $auditSignature,
            ]);
        }

        return [
            'nextDeltaAuditToken' => $auditToken,
            'nextDeltaAuditSignature' => $auditSignature,
            'operatorTrace' => $operatorTrace,
            'finalPageRows' => $finalPage,
            'requiredDeltaAuditReceipts' => $receipts,
            'requiredDeltaAuditReceiptCount' => count($receipts),
            'promotionToken' => (string) ($promotion['nextPromotionToken'] ?? ''),
            'nextDeltaSignature' => (string) ($promotion['nextDeltaSignature'] ?? ''),
            'nextOnlyLabels' => self::stringList($promotion['nextOnlyLabels'] ?? []),
            'currentOnlyLabels' => self::stringList($promotion['currentOnlyLabels'] ?? []),
            'nextExposure' => 'held-until-compound-operator-final-page-audit-matches',
            'yieldBoundary' => 'compound-window-recursive-next251-operator-final-page-delta-audit',
        ];
    }

    /** @param array<string,mixed>|null $cursor @param array<string,mixed> $audit */
    private static function validateCursor(?array $cursor, array $audit): void
    {
        if ($cursor === null) {
            return;
        }

        foreach ([
            'nextDeltaAuditTokenNext251' => 'nextDeltaAuditToken',
            'nextDeltaAuditSignatureNext251' => 'nextDeltaAuditSignature',
        ] as $cursorKey => $auditKey) {
            if (isset($cursor[$cursorKey]) && $cursor[$cursorKey] !== $audit[$auditKey]) {
                throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next251 cursor does not match next-source delta audit');
            }
        }
        if (!array_key_exists('acknowledgedDeltaAuditReceiptsNext251', $cursor)) {
            return;
        }
        if (!is_array($cursor['acknowledgedDeltaAuditReceiptsNext251'])) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next251 delta audit receipts must be a list');
        }

        $acknowledged = array_values(array_map(static fn (mixed $receipt): string => (string) $receipt, $cursor['acknowledgedDeltaAuditReceiptsNext251']));
        $required = self::stringList($audit['requiredDeltaAuditReceipts'] ?? []);
        $missing = array_values(array_diff($required, $acknowledged));
        $unexpected = array_values(array_diff($acknowledged, $required));
        if ($missing !== [] || $unexpected !== []) {
            throw new \InvalidArgumentException('SQLite compound SELECT window recursive LIMIT next251 delta audit receipts do not match compound operator and final-page set');
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

    /** @param array<string,mixed> $compound @return list<array{ordinal:int,operator:string}> */
    private static function operatorTrace(array $compound): array
    {
        $operators = self::stringList($compound['operators'] ?? []);
        $trace = [];
        foreach ($operators as $index => $operator) {
            $trace[] = [
                'ordinal' => $index + 1,
                'operator' => strtoupper($operator),
            ];
        }

        return $trace;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return list<array<string,mixed>>
     */
    private static function finalPage(array $currentRows, array $nextRows): array
    {
        $page = [];
        foreach (['current' => $currentRows, 'next' => $nextRows] as $source => $rows) {
            foreach ($rows as $index => $row) {
                $page[] = [
                    'source' => $source,
                    'ordinal' => $index + 1,
                    'id' => $row['id'] ?? $row['option_id'] ?? null,
                    'label' => (string) ($row['label'] ?? $row['name'] ?? $row['option_name'] ?? ''),
                    'metric' => $row['metric'] ?? $row['rn'] ?? $row['rank'] ?? null,
                ];
            }
        }

        return $page;
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
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
