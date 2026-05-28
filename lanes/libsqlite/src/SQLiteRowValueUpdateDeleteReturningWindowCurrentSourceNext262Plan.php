<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext262Plan
{
    /**
     * @param array<string,list<array<string,mixed>>> $tables
     * @param list<string> $yieldStatements
     * @param list<string> $attemptStatements
     * @param list<string> $retryStatements
     * @param list<list<string>> $uniqueConstraints
     * @param list<string>|null $acknowledgedYieldTickets
     * @param list<string>|null $acknowledgedNextRowTickets
     * @param list<string>|null $acknowledgedBoundaryTickets
     * @param list<string>|null $acknowledgedPeerTokens
     * @param list<string> $peerColumns
     * @return array<string,mixed>
     */
    public static function execute(
        array $tables,
        array $yieldStatements,
        array $attemptStatements,
        array $retryStatements,
        array $uniqueConstraints,
        string $savepoint = 'wp_options_rowvalue_window_current_next262',
        string $rowIdColumn = 'option_id',
        ?array $acknowledgedYieldTickets = null,
        ?string $resumeAfterTicket = null,
        ?array $acknowledgedNextRowTickets = null,
        ?array $acknowledgedBoundaryTickets = null,
        ?array $acknowledgedPeerTokens = null,
        array $peerColumns = ['status'],
    ): array {
        self::peerColumns($peerColumns);

        $base = SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext260Plan::execute(
            $tables,
            $yieldStatements,
            $attemptStatements,
            $retryStatements,
            $uniqueConstraints,
            $savepoint,
            $rowIdColumn,
            $acknowledgedYieldTickets,
            $resumeAfterTicket,
            $acknowledgedNextRowTickets,
            $acknowledgedBoundaryTickets,
        );

        $groups = self::peerGroups($base['boundary_window_rows_next260'], $peerColumns, $rowIdColumn);
        $requiredTokens = array_values(array_map(
            static fn (array $group): string => $group['peer_token_next262'],
            array_filter($groups, static fn (array $group): bool => ($group['crosses_source_next262'] ?? false) === true),
        ));
        $acknowledged = $acknowledgedPeerTokens === null ? $requiredTokens : self::tokenSet($acknowledgedPeerTokens);
        $rows = self::peerRows($base['boundary_window_rows_next260'], $groups, $acknowledged, $rowIdColumn);
        $readyRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['peer_ready_next262'] ?? null) === true));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => ($row['peer_ready_next262'] ?? null) !== true));
        $crossingGroups = array_values(array_filter($groups, static fn (array $group): bool => ($group['crosses_source_next262'] ?? false) === true));
        $readyGroups = array_values(array_filter($groups, static fn (array $group): bool => in_array($group['peer_token_next262'], $acknowledged, true)));

        return array_merge($base, [
            'status' => 'rowvalue-update-delete-returning-window-current-source-next262',
            'peer_group_columns_next262' => $peerColumns,
            'peer_group_admission_next262' => [
                'savepoint' => $savepoint,
                'source_boundary_released_next260' => (bool) ($base['boundary_fence_next260']['current_to_next_boundary_released'] ?? false),
                'peer_column_count' => count($peerColumns),
                'peer_group_count' => count($groups),
                'crossing_peer_group_count' => count($crossingGroups),
                'required_peer_token_count' => count($requiredTokens),
                'acknowledged_peer_token_count' => count($acknowledged),
                'missing_peer_tokens' => array_values(array_diff($requiredTokens, $acknowledged)),
                'unexpected_peer_tokens' => array_values(array_diff($acknowledged, $requiredTokens)),
                'ready_peer_group_count' => count($readyGroups),
                'row_count' => count($rows),
                'ready_row_count' => count($readyRows),
                'blocked_row_count' => count($blockedRows),
                'peer_groups_complete' => $blockedRows === [] && array_diff($requiredTokens, $acknowledged) === [] && array_diff($acknowledged, $requiredTokens) === [],
                'peer_digest' => self::digest($groups),
            ],
            'peer_groups_next262' => $groups,
            'crossing_peer_groups_next262' => $crossingGroups,
            'required_peer_tokens_next262' => $requiredTokens,
            'acknowledged_peer_tokens_next262' => $acknowledged,
            'peer_rows_next262' => $rows,
            'peer_ready_rows_next262' => $readyRows,
            'peer_blocked_rows_next262' => $blockedRows,
            'peer_ready_rowids_next262' => array_column($readyRows, 'peer_rowid_next262'),
            'peer_blocked_rowids_next262' => array_column($blockedRows, 'peer_rowid_next262'),
            'peer_state_next262' => $blockedRows === [] && array_diff($requiredTokens, $acknowledged) === [] && array_diff($acknowledged, $requiredTokens) === []
                ? 'current-source-peer-groups-complete-next-source-visible-next262'
                : 'next-source-peer-groups-held-for-current-source-next262',
            'dependency_closure_next262' => 'no new support component needed; next262 reuses row-value UPDATE/DELETE RETURNING window rows, next260 frame-boundary receipts, and native source epochs while adding GROUPS/RANGE peer-group admission across current and retry sources',
            'dependencies_next262' => [
                'sqlite-rowvalue-returning-window-peer-groups-next262',
                'sqlite-rowvalue-returning-peer-source-boundary-next262',
                'wordpress-rowvalue-returning-window-peer-groups-next262',
            ],
            'non_overlap_next262' => 'adds GROUPS/RANGE peer-group admission for RETURNING rows whose peer value spans current-source and retry-source rows; avoids next260 adjacent frame-boundary receipts, next259 CURRENT ROW frame close, next256 commit watermarks, next255 next-row admission, row-value savepoint-only variants, trigger RETURNING, WAL/VFS, JSON table, planner, B-tree, encoding, PRAGMA, and suite-runner surfaces',
        ]);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<string> $peerColumns
     * @return list<array<string,mixed>>
     */
    private static function peerGroups(array $rows, array $peerColumns, string $rowIdColumn): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $keyParts = [];
            foreach ($peerColumns as $column) {
                if (!array_key_exists($column, $row)) {
                    throw new \InvalidArgumentException("SQLite row-value returning window next262 peer column {$column} is missing");
                }
                $keyParts[] = $column . '=' . self::scalar($row[$column] ?? null, $column);
            }
            $key = implode('|', $keyParts);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'peer_key_next262' => $key,
                    'peer_columns_next262' => $peerColumns,
                    'peer_values_next262' => array_intersect_key($row, array_fill_keys($peerColumns, true)),
                    'rowids_next262' => [],
                    'tickets_next262' => [],
                    'epochs_next262' => [],
                    'peer_token_next262' => '',
                ];
            }
            $groups[$key]['rowids_next262'][] = self::rowId($row['boundary_rowid_next260'] ?? $row[$rowIdColumn] ?? null, $rowIdColumn);
            $groups[$key]['tickets_next262'][] = self::token($row['ticket'] ?? null, 'ticket');
            $groups[$key]['epochs_next262'][] = self::token($row['next_row_source_epoch_next255'] ?? $row['source_epoch_next251'] ?? null, 'source epoch');
        }

        $out = [];
        foreach ($groups as $group) {
            $epochs = array_values(array_unique($group['epochs_next262']));
            $group['epochs_next262'] = $epochs;
            $group['crosses_source_next262'] = count($epochs) > 1;
            $group['peer_row_count_next262'] = count($group['rowids_next262']);
            $group['peer_token_next262'] = hash('sha256', json_encode([
                'key' => $group['peer_key_next262'],
                'rowids' => $group['rowids_next262'],
                'tickets' => $group['tickets_next262'],
                'epochs' => $epochs,
            ], JSON_THROW_ON_ERROR));
            $out[] = $group;
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<array<string,mixed>> $groups
     * @param list<string> $acknowledged
     * @return list<array<string,mixed>>
     */
    private static function peerRows(array $rows, array $groups, array $acknowledged, string $rowIdColumn): array
    {
        $byTicket = [];
        foreach ($groups as $group) {
            foreach ($group['tickets_next262'] as $ticket) {
                $byTicket[$ticket] = $group;
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $ticket = self::token($row['ticket'] ?? null, 'ticket');
            $group = $byTicket[$ticket] ?? null;
            if ($group === null) {
                throw new \InvalidArgumentException('SQLite row-value returning window next262 peer group is missing');
            }
            $crosses = (bool) $group['crosses_source_next262'];
            $acknowledgedGroup = !$crosses || in_array($group['peer_token_next262'], $acknowledged, true);
            $boundaryReady = ($row['boundary_ready_next260'] ?? null) === true;
            $reasons = [];
            if (!$boundaryReady) {
                $reasons[] = 'frame-boundary-not-ready-next262';
            }
            if (!$acknowledgedGroup) {
                $reasons[] = 'source-crossing-peer-group-not-acknowledged-next262';
            }

            $rowId = self::rowId($row['boundary_rowid_next260'] ?? $row[$rowIdColumn] ?? null, $rowIdColumn);
            $out[] = [
                'peer_ordinal_next262' => count($out) + 1,
                'peer_rowid_next262' => $rowId,
                'peer_key_next262' => $group['peer_key_next262'],
                'peer_token_next262' => $group['peer_token_next262'],
                'peer_group_crosses_source_next262' => $crosses,
                'peer_group_acknowledged_next262' => $acknowledgedGroup,
                'peer_boundary_ready_next262' => $boundaryReady,
                'peer_ready_next262' => $reasons === [],
                'peer_blocked_reasons_next262' => $reasons,
                'peer_receipt_next262' => hash('sha256', $ticket . '|' . $group['peer_token_next262'] . '|' . ($reasons === [] ? 'ready' : 'blocked')),
            ] + $row;
        }

        return $out;
    }

    /**
     * @param list<string> $columns
     */
    private static function peerColumns(array $columns): void
    {
        if ($columns === []) {
            throw new \InvalidArgumentException('SQLite row-value returning window next262 needs peer columns');
        }
        foreach ($columns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite row-value returning window next262 peer column must be a non-empty string');
            }
        }
    }

    /**
     * @param list<string> $tokens
     * @return list<string>
     */
    private static function tokenSet(array $tokens): array
    {
        $set = [];
        foreach ($tokens as $token) {
            $set[] = self::token($token, 'peer token');
        }

        return array_values(array_unique($set));
    }

    private static function scalar(mixed $value, string $column): string
    {
        if (!is_scalar($value) && $value !== null) {
            throw new \InvalidArgumentException("SQLite row-value returning window next262 peer column {$column} must be scalar or null");
        }

        return $value === null ? 'NULL' : (string) $value;
    }

    private static function rowId(mixed $value, string $rowIdColumn): int|string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException("SQLite row-value returning window next262 rowid column {$rowIdColumn} must be int or string");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite row-value returning window next262 {$label} is missing");
        }

        return $value;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function digest(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }
}
