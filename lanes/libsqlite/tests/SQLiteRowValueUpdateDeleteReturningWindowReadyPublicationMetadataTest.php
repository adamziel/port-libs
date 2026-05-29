<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan.php';

$candidateReadyPublication = static function (array $rowids): array {
    $rows = [];
    foreach (array_values($rowids) as $index => $rowid) {
        $rows[] = ['row_number' => $index + 1, 'current_rowid' => $rowid, 'status' => 'retry-ready'];
    }

    return [
        'status' => 'rowvalue-update-delete-returning-window-ready-publication-candidate',
        'after_ready' => true,
        'retry_window_rows' => $rows,
    ];
};

$readyPublicationCandidates = [
    $candidateReadyPublication([2, 5]),
    $candidateReadyPublication([3, 6, 8]),
    $candidateReadyPublication([4]),
    $candidateReadyPublication([7, 9]),
];

$readyPublicationPlan = static fn (): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareReadyWindowPublicationMetadata($readyPublicationCandidates);

$readyPublicationCases = [
    'status' => [static fn (): mixed => $readyPublicationPlan()['status'], 'rowvalue-update-delete-returning-window-ready-publication-metadata'],
    'ready statuses' => [static fn (): mixed => $readyPublicationPlan()['ready_candidate_statuses'], [
        'rowvalue-update-delete-returning-window-ready-publication-candidate',
        'rowvalue-update-delete-returning-window-ready-publication-candidate',
        'rowvalue-update-delete-returning-window-ready-publication-candidate',
        'rowvalue-update-delete-returning-window-ready-publication-candidate',
    ]],
    'candidate ordinals' => [static fn (): mixed => $readyPublicationPlan()['ready_candidate_ordinals'], [1, 2, 3, 4]],
    'row counts' => [static fn (): mixed => $readyPublicationPlan()['retry_window_row_counts'], [1 => 2, 2 => 3, 3 => 1, 4 => 2]],
    'rowids retained' => [static fn (): mixed => $readyPublicationPlan()['retry_window_rowids'][2], [3, 6, 8]],
    'receipt hash length' => [static fn (): mixed => strlen($readyPublicationPlan()['publication_receipt']), 64],
    'ledger hash length' => [static fn (): mixed => strlen($readyPublicationPlan()['publication_ledger']), 64],
    'handoff hash length' => [static fn (): mixed => strlen($readyPublicationPlan()['publication_handoff']), 64],
    'seal hash length' => [static fn (): mixed => strlen($readyPublicationPlan()['publication_seal']), 64],
    'ready flag' => [static fn (): mixed => $readyPublicationPlan()['publication_ready'], true],
    'dependency closure' => [static fn (): mixed => str_contains($readyPublicationPlan()['dependency_closure'], 'no new support component needed'), true],
    'non overlap' => [static fn (): mixed => str_contains($readyPublicationPlan()['non_overlap'], 'avoids suite'), true],
    'bad candidate count rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareReadyWindowPublicationMetadata(array_slice($readyPublicationCandidates, 0, 3)), InvalidArgumentException::class],
    'bad status rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareReadyWindowPublicationMetadata([['status' => 'bad']]), InvalidArgumentException::class],
    'bad after ready rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::prepareReadyWindowPublicationMetadata([
        $candidateReadyPublication([1]),
        ['status' => 'rowvalue-update-delete-returning-window-ready-publication-candidate', 'after_ready' => false, 'retry_window_rows' => []],
        $candidateReadyPublication([3]),
        $candidateReadyPublication([4]),
    ]), InvalidArgumentException::class],
];

$tests = [];
foreach ($readyPublicationCases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window ready publication metadata ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
