<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next277-280-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next277-280-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next277',
            'rowvalue-update-delete-returning-window-current-source-next278',
            'rowvalue-update-delete-returning-window-current-source-next279',
            'rowvalue-update-delete-returning-window-current-source-next280',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next277Attestation']));
        $t->same(64, strlen($result['next278Manifest']));
        $t->same(64, strlen($result['next279Bridge']));
        $t->same(64, strlen($result['next280Seal']));
        $t->same(true, $result['next280Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next277-280 after current ' . $name] = $callback;
}

return $tests;
