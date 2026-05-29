<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'next265 ledger candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next265.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next265', $result['status']);
        $t->same(8, $result['ledgerCount']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next266 watermark candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next266.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next266', $result['status']);
        $t->same(true, $result['currentSourceClosed']);
        $t->true(str_contains($result['dependencyClosure'], 'source-epoch audit watermark'));
    },
    'next267 handoff batch candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next267.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next267', $result['status']);
        $t->same(3, $result['batchCount']);
        $t->true(str_contains($result['dependencyClosure'], 'handoff batches'));
    },
    'next268 manifest candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next268.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next268', $result['status']);
        $t->same(true, $result['handoffComplete']);
        $t->same(64, strlen($result['manifestReceipt']));
        $t->true(str_contains($result['dependencyClosure'], 'final manifest'));
    },
    'combined after-current handoff' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next265-268-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next265-268-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next265',
            'rowvalue-update-delete-returning-window-current-source-next266',
            'rowvalue-update-delete-returning-window-current-source-next267',
            'rowvalue-update-delete-returning-window-current-source-next268',
        ], $result['candidateStatuses']);
        $t->same(8, $result['next265LedgerCount']);
        $t->same(true, $result['next266CurrentSourceClosed']);
        $t->same(3, $result['next267BatchCount']);
        $t->same(true, $result['next268HandoffComplete']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next265-268 after current ' . $name] = $callback;
}

return $tests;
