<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current current-source seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next285-288-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next285-288-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next285',
            'rowvalue-update-delete-returning-window-current-source-next286',
            'rowvalue-update-delete-returning-window-current-source-next287',
            'rowvalue-update-delete-returning-window-current-source-next288',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next285Receipt']));
        $t->same(64, strlen($result['next286Ledger']));
        $t->same(64, strlen($result['next287Window']));
        $t->same(true, $result['next287RetryWindowRows'] > 0);
        $t->same(64, strlen($result['next288Seal']));
        $t->same(true, $result['next288Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next285-288 after current ' . $name] = $callback;
}

return $tests;
