<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current closure' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-closure-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-closure-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next269',
            'rowvalue-update-delete-returning-window-current-source-next270',
            'rowvalue-update-delete-returning-window-current-source-next271',
            'rowvalue-update-delete-returning-window-current-source-next272',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['closureReceipt']));
        $t->same(64, strlen($result['deleteGuard']));
        $t->same(64, strlen($result['updateFence']));
        $t->same(64, strlen($result['afterCurrentReceipt']));
        $t->same(true, $result['afterCurrentReady']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source closure after current ' . $name] = $callback;
}

return $tests;
