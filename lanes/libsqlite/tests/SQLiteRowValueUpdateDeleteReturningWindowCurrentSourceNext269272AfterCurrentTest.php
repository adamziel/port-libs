<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current closure' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next269-272-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next269-272-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next269',
            'rowvalue-update-delete-returning-window-current-source-next270',
            'rowvalue-update-delete-returning-window-current-source-next271',
            'rowvalue-update-delete-returning-window-current-source-next272',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next269ClosureReceipt']));
        $t->same(64, strlen($result['next270DeleteGuard']));
        $t->same(64, strlen($result['next271UpdateFence']));
        $t->same(64, strlen($result['next272AfterCurrentReceipt']));
        $t->same(true, $result['next272Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next269-272 after current ' . $name] = $callback;
}

return $tests;
