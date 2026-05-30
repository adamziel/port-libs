<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next306-309 current-source preflight seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next306-309.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next306-309', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next306',
            'rowvalue-update-delete-returning-window-current-source-next307',
            'rowvalue-update-delete-returning-window-current-source-next308',
            'rowvalue-update-delete-returning-window-current-source-next309',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next306Handoff']));
        $t->same('next302-305', $result['next306AfterReadyRange']);
        $t->same(64, strlen($result['next307SourceAudit']));
        $t->same(true, $result['next307PreservesCurrentSource']);
        $t->same(64, strlen($result['next308Preflight']));
        $t->same(true, $result['next308KeepsThroughputHigh']);
        $t->same(64, strlen($result['next309Final']));
        $t->same(true, $result['next309Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next306-309 ' . $name] = $callback;
}

return $tests;
