<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current handoff' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-admission-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-admission-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next273',
            'rowvalue-update-delete-returning-window-current-source-next274',
            'rowvalue-update-delete-returning-window-current-source-next275',
            'rowvalue-update-delete-returning-window-current-source-next276',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['admission']));
        $t->same(64, strlen($result['returningBalance']));
        $t->same(64, strlen($result['sourcePackage']));
        $t->same(64, strlen($result['handoff']));
        $t->same(true, $result['handoffReady']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source admission after current ' . $name] = $callback;
}

return $tests;
