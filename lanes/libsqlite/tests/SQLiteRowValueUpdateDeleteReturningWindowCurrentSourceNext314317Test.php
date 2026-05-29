<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next314-317 current-source preflight seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next314-317.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next314-317', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next314',
            'rowvalue-update-delete-returning-window-current-source-next315',
            'rowvalue-update-delete-returning-window-current-source-next316',
            'rowvalue-update-delete-returning-window-current-source-next317',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next314Handoff']));
        $t->same('next310-313', $result['next314AfterReadyRange']);
        $t->same(64, strlen($result['next315SourceAudit']));
        $t->same(true, $result['next315PreservesCurrentSource']);
        $t->same(64, strlen($result['next316Preflight']));
        $t->same(true, $result['next316KeepsThroughputHigh']);
        $t->same(64, strlen($result['next317Final']));
        $t->same(true, $result['next317Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next314-317 ' . $name] = $callback;
}

return $tests;
