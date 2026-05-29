<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php';

$candidate298301 = static function (int $next, array $rowids): array {
    $rows = [];
    foreach (array_values($rowids) as $index => $rowid) {
        $rows[] = ['row_number' => $index + 1, 'current_rowid' => $rowid, 'status' => "retry{$next}"];
    }

    return [
        'status' => "rowvalue-update-delete-returning-window-current-source-next{$next}-ready",
        'after_ready' => true,
        'retry_window_rows' => $rows,
    ];
};

$ready298301 = [
    $candidate298301(294, [2, 5]),
    $candidate298301(295, [3, 6, 8]),
    $candidate298301(296, [4]),
    $candidate298301(297, [7, 9]),
];

$plan298301 = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareAfterReadyWindowMetadata($ready298301);

$cases298301 = [
    'status' => [static fn (): mixed => $plan298301()['status'], 'rowvalue-update-delete-returning-window-current-source-next298-301-after-ready'],
    'ready statuses' => [static fn (): mixed => $plan298301()['ready_candidate_statuses'], [
        'rowvalue-update-delete-returning-window-current-source-next294-ready',
        'rowvalue-update-delete-returning-window-current-source-next295-ready',
        'rowvalue-update-delete-returning-window-current-source-next296-ready',
        'rowvalue-update-delete-returning-window-current-source-next297-ready',
    ]],
    'row counts' => [static fn (): mixed => $plan298301()['retry_window_row_counts'], [294 => 2, 295 => 3, 296 => 1, 297 => 2]],
    'rowids retained' => [static fn (): mixed => $plan298301()['retry_window_rowids'][295], [3, 6, 8]],
    'receipt hash length' => [static fn (): mixed => strlen($plan298301()['next298_receipt']), 64],
    'ledger hash length' => [static fn (): mixed => strlen($plan298301()['next299_ledger']), 64],
    'handoff hash length' => [static fn (): mixed => strlen($plan298301()['next300_handoff']), 64],
    'seal hash length' => [static fn (): mixed => strlen($plan298301()['next301_seal']), 64],
    'ready flag' => [static fn (): mixed => $plan298301()['next301_ready'], true],
    'dependency closure' => [static fn (): mixed => str_contains($plan298301()['dependency_closure_next298_301'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($plan298301()['non_overlap_next298_301'], 'avoids suite'), true],
    'bad candidate count rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareAfterReadyWindowMetadata(array_slice($ready298301, 0, 3)), InvalidArgumentException::class],
    'bad status rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareAfterReadyWindowMetadata([['status' => 'bad']]), InvalidArgumentException::class],
    'bad after ready rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareAfterReadyWindowMetadata([
        $candidate298301(294, [1]),
        ['status' => 'rowvalue-update-delete-returning-window-current-source-next295-ready', 'after_ready' => false, 'retry_window_rows' => []],
        $candidate298301(296, [3]),
        $candidate298301(297, [4]),
    ]), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases298301 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next298-301 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
