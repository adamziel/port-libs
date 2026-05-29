<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined current-source continuation seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-source-continuation-seal.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-continuation', $result['status']);
        $t->same(['rowvalue-update-delete-returning-window-current-source-continuation'], $result['candidateStatuses']);
        $t->same(64, strlen($result['sourceWindowHash']));
        $t->same('after-ready-window-metadata', $result['awaitedReadyRange']);
        $t->same(64, strlen($result['throughputAuditHash']));
        $t->same(true, $result['keepsIndependentSlices']);
        $t->same(64, strlen($result['isolationHash']));
        $t->same(64, strlen($result['finalSealHash']));
        $t->same(true, $result['ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source continuation ' . $name] = $callback;
}

return $tests;
