<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next322-325 current-source preflight seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next322-325.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next322-325', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next322',
            'rowvalue-update-delete-returning-window-current-source-next323',
            'rowvalue-update-delete-returning-window-current-source-next324',
            'rowvalue-update-delete-returning-window-current-source-next325',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next322Handoff']));
        $t->same('next318-321', $result['next322AfterReadyRange']);
        $t->same(64, strlen($result['next323SourceAudit']));
        $t->same(true, $result['next323PreservesCurrentSource']);
        $t->same(64, strlen($result['next324Preflight']));
        $t->same(true, $result['next324KeepsThroughputHigh']);
        $t->same(64, strlen($result['next325Final']));
        $t->same(true, $result['next325Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next322-325 ' . $name] = $callback;
}

return $tests;
