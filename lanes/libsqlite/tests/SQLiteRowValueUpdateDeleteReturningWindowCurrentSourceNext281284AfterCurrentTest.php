<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current current-source seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next281-284-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next281-284-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next281',
            'rowvalue-update-delete-returning-window-current-source-next282',
            'rowvalue-update-delete-returning-window-current-source-next283',
            'rowvalue-update-delete-returning-window-current-source-next284',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next281Receipt']));
        $t->same(64, strlen($result['next282Ledger']));
        $t->same(64, strlen($result['next283Window']));
        $t->same(true, $result['next283RetryWindowRows'] > 0);
        $t->same(64, strlen($result['next284Seal']));
        $t->same(true, $result['next284Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next281-284 after current ' . $name] = $callback;
}

return $tests;
