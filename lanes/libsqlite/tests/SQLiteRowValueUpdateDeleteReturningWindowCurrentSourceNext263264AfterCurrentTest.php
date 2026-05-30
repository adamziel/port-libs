<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'next263 peer checkpoint candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-peer-checkpoint-admission.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next263', $result['status']);
        $t->same(4, $result['checkpointCount']);
        $t->same(1, $result['crossingCheckpointCount']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next264 final receipt candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-final-receipt-admission.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next264', $result['status']);
        $t->same(8, $result['finalReceiptCount']);
        $t->same(true, $result['finalReceiptsComplete']);
        $t->same('rowvalue-returning-current-source-final-receipts-complete-next264', $result['finalState']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'combined after-current handoff' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-after-current-segment-peer.php';

        $t->same('rowvalue-update-delete-returning-window-after-current-segment-peer', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next261',
            'application-rowvalue-returning-window-peer-group-admission self-test passed',
            'rowvalue-update-delete-returning-window-current-source-next263',
            'rowvalue-update-delete-returning-window-current-source-next264',
        ], $result['candidateStatuses']);
        $t->same(4, $result['next263CheckpointCount']);
        $t->same(8, $result['next264FinalReceiptCount']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next263-264 after current ' . $name] = $callback;
}

return $tests;
