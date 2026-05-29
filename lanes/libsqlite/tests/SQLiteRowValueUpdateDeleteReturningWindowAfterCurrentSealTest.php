<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-after-current-seal.php';

        $t->same('rowvalue-update-delete-returning-window-after-current-seal', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next277',
            'rowvalue-update-delete-returning-window-current-source-next278',
            'rowvalue-update-delete-returning-window-current-source-next279',
            'rowvalue-update-delete-returning-window-current-source-next280',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['attestation']));
        $t->same(64, strlen($result['manifest']));
        $t->same(64, strlen($result['bridge']));
        $t->same(64, strlen($result['seal']));
        $t->same(true, $result['sealReady']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window after current seal ' . $name] = $callback;
}

return $tests;
