<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current current-source seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-after-current-final-seal.php';

        $t->same('rowvalue-update-delete-returning-window-after-current-final-seal', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next285',
            'rowvalue-update-delete-returning-window-current-source-next286',
            'rowvalue-update-delete-returning-window-current-source-next287',
            'rowvalue-update-delete-returning-window-current-source-next288',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['receipt']));
        $t->same(64, strlen($result['ledger']));
        $t->same(64, strlen($result['windowCoverage']));
        $t->same(true, $result['retryWindowRows'] > 0);
        $t->same(64, strlen($result['finalSeal']));
        $t->same(true, $result['finalReady']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window after current final seal ' . $name] = $callback;
}

return $tests;
