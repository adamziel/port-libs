<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next326-333 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next326-333.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next326-333', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next326',
            'rowvalue-update-delete-returning-window-current-source-next327',
            'rowvalue-update-delete-returning-window-current-source-next328',
            'rowvalue-update-delete-returning-window-current-source-next329',
            'rowvalue-update-delete-returning-window-current-source-next330',
            'rowvalue-update-delete-returning-window-current-source-next331',
            'rowvalue-update-delete-returning-window-current-source-next332',
            'rowvalue-update-delete-returning-window-current-source-next333',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next326Handoff']));
        $t->same('next322-325', $result['next326AfterReadyRange']);
        $t->same(64, strlen($result['next327SourceAudit']));
        $t->same(true, $result['next327PreservesCurrentSource']);
        $t->same(64, strlen($result['next328Preflight']));
        $t->same(true, $result['next328KeepsThroughputHigh']);
        $t->same(64, strlen($result['next329Final']));
        $t->same(true, $result['next329Ready']);
        $t->same(64, strlen($result['next330Handoff']));
        $t->same('next326-329', $result['next330AfterReadyRange']);
        $t->same(64, strlen($result['next331SourceAudit']));
        $t->same(true, $result['next331PreservesCurrentSource']);
        $t->same(64, strlen($result['next332Preflight']));
        $t->same(true, $result['next332KeepsThroughputHigh']);
        $t->same(64, strlen($result['next333Final']));
        $t->same(true, $result['next333Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next326-333 ' . $name] = $callback;
}

return $tests;
