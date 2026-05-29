<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current current-source seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-ready-seal.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-ready-seal', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next281',
            'rowvalue-update-delete-returning-window-current-source-next282',
            'rowvalue-update-delete-returning-window-current-source-next283',
            'rowvalue-update-delete-returning-window-current-source-next284',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['receipt']));
        $t->same(64, strlen($result['ledger']));
        $t->same(64, strlen($result['windowReceipt']));
        $t->same(true, $result['retryWindowRows'] > 0);
        $t->same(64, strlen($result['readySeal']));
        $t->same(true, $result['ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source ready seal ' . $name] = $callback;
}

return $tests;
