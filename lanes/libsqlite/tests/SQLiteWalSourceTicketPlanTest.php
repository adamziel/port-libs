<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$tests = [];

$digest = static fn (string $value): string => hash('sha256', $value);
$next221 = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next221',
    'database_path' => '/srv/www/wp-content/database/wp-next222.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next222.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next222.sqlite-journal',
    'shm_path' => '/srv/www/wp-content/database/wp-next222.sqlite-shm',
    'next_source_token' => [
        'id' => 'hot-journal-checkpoint-source:222',
        'epoch' => 222,
        'checkpoint_frame' => 45,
        'checkpoint_cookie' => 91222,
    ],
    'checkpoint_frame' => 45,
    'checkpoint_cookie' => 91222,
    'checkpoint_admitted' => true,
    'operation_names' => ['verify_shm_read_mark_reset_receipt_next221'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next221'],
];

$ticket = static function (string $name, string $kind, array $overrides = []) use ($next221, $digest): array {
    return array_merge([
        'name' => $name,
        'kind' => $kind,
        'source_id' => $next221['next_source_token']['id'],
        'epoch' => 222,
        'checkpoint_frame' => 45,
        'checkpoint_cookie' => 91222,
        'ticket_sha256' => $digest($name . ':' . $kind),
        'sidecar_retired' => true,
        'sync_receipt' => true,
    ], $overrides);
};

$tickets = [
    $ticket('database-ticket', 'database'),
    $ticket('wal-ticket', 'wal'),
    $ticket('journal-ticket', 'journal'),
    $ticket('shm-ticket', 'shm'),
];
$plan = static fn (?array $base = null, ?array $input = null): array => SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::sourceTicketPlan($base ?? $next221, $input ?? $tickets);

$blockedTickets = [
    $ticket('database-ticket', 'database', ['source_id' => 'older']),
    $ticket('wal-ticket', 'wal', ['epoch' => 221]),
    $ticket('journal-ticket', 'journal', ['checkpoint_frame' => 44, 'checkpoint_cookie' => 7]),
    $ticket('shm-ticket', 'shm', ['sidecar_retired' => false, 'sync_receipt' => false]),
];
$missingShm = array_slice($tickets, 0, 3);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-next222'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'source_tickets_seal_sidecar_retired_checkpoint_source'],
    'paths' => [static fn (): mixed => [$plan()['database_path'], $plan()['wal_path'], $plan()['journal_path'], $plan()['shm_path']], [$next221['database_path'], $next221['wal_path'], $next221['journal_path'], $next221['shm_path']]],
    'token' => [static fn (): mixed => $plan()['next_source_token']['id'], 'hot-journal-checkpoint-source:222'],
    'frame cookie' => [static fn (): mixed => [$plan()['checkpoint_frame'], $plan()['checkpoint_cookie']], [45, 91222]],
    'ticket count' => [static fn (): mixed => count($plan()['ticket_rows']), 4],
    'admitted names' => [static fn (): mixed => $plan()['admitted_ticket_names'], ['database-ticket', 'wal-ticket', 'journal-ticket', 'shm-ticket']],
    'blocked names empty' => [static fn (): mixed => $plan()['blocked_ticket_names'], []],
    'required kinds' => [static fn (): mixed => $plan()['required_ticket_kinds'], ['database', 'wal', 'journal', 'shm']],
    'admitted kinds' => [static fn (): mixed => $plan()['admitted_ticket_kinds'], ['database', 'wal', 'journal', 'shm']],
    'missing kinds empty' => [static fn (): mixed => $plan()['missing_ticket_kinds'], []],
    'sealed' => [static fn (): mixed => $plan()['source_ticket_sealed'], true],
    'action' => [static fn (): mixed => $plan()['current_source_ticket_action'], 'seal_current_source_ticket_next222'],
    'transition' => [static fn (): mixed => $plan()['ticket_rows'][1]['transition'], 'wal-ticket>seal-source-ticket:next222'],
    'guards' => [static fn (): mixed => $plan()['guard_matches'], [true, true, true]],
    'blocked guards empty' => [static fn (): mixed => $plan()['blocked_guard_names'], []],
    'operation inherited' => [static fn (): mixed => in_array('verify_shm_read_mark_reset_receipt_next221', $plan()['operation_names'], true), true],
    'operation next222' => [static fn (): mixed => in_array('seal_checkpoint_current_source_ticket_next222', $plan()['operation_names'], true), true],
    'digest length' => [static fn (): mixed => strlen($plan()['ticket_digest']), 64],
    'dependency next221' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next221', $plan()['dependencies'], true), true],
    'dependency next222' => [static fn (): mixed => in_array('sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next222', $plan()['dependencies'], true), true],
    'dependency wordpress' => [static fn (): mixed => in_array('wordpress-import-hot-journal-source-ticket', $plan()['dependencies'], true), true],
    'closure' => [static fn (): mixed => str_contains($plan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat sidecar deletion'), true],
    'blocked status' => [static fn (): mixed => $plan(null, $blockedTickets)['status'], 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next222'],
    'blocked names' => [static fn (): mixed => $plan(null, $blockedTickets)['blocked_ticket_names'], ['database-ticket', 'wal-ticket', 'journal-ticket', 'shm-ticket']],
    'blocked reasons' => [static fn (): mixed => $plan(null, $blockedTickets)['ticket_rows'][3]['blocked_reasons'], ['ticket_sidecar_not_retired', 'ticket_sync_receipt_missing']],
    'missing shm' => [static fn (): mixed => $plan(null, $missingShm)['missing_ticket_kinds'], ['shm']],
    'missing shm guard' => [static fn (): mixed => $plan(null, $missingShm)['blocked_guard_names'], ['required_source_ticket_kinds_present']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['wal hot journal savepoint checkpoint current source next222 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'bad status rejected' => static fn () => $plan(array_merge($next221, ['status' => 'blocked'])),
    'empty tickets rejected' => static fn () => $plan(null, []),
    'bad token rejected' => static fn () => $plan(array_merge($next221, ['next_source_token' => ['id' => '', 'epoch' => 0]])),
    'bad checkpoint rejected' => static fn () => $plan(array_merge($next221, ['checkpoint_frame' => 0])),
    'bad ticket kind rejected' => static fn () => $plan(null, [$ticket('bad', 'database', ['kind' => 'sidecar'])]),
    'bad digest rejected' => static fn () => $plan(null, [$ticket('bad', 'database', ['ticket_sha256' => 'short'])]),
    'bad numeric rejected' => static fn () => $plan(null, [$ticket('bad', 'database', ['epoch' => '222'])]),
];

foreach ($throws as $name => $callback) {
    $tests['wal hot journal savepoint checkpoint current source next222 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
