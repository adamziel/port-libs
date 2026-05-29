<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next358-365 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next358-365.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next358-365', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next358',
            'rowvalue-update-delete-returning-window-current-source-next359',
            'rowvalue-update-delete-returning-window-current-source-next360',
            'rowvalue-update-delete-returning-window-current-source-next361',
            'rowvalue-update-delete-returning-window-current-source-next362',
            'rowvalue-update-delete-returning-window-current-source-next363',
            'rowvalue-update-delete-returning-window-current-source-next364',
            'rowvalue-update-delete-returning-window-current-source-next365',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next358Handoff']));
        $t->same('next350-357', $result['next358AfterReadyRange']);
        $t->same(64, strlen($result['next359SourceAudit']));
        $t->same(true, $result['next359PreservesCurrentSource']);
        $t->same(64, strlen($result['next360Preflight']));
        $t->same(true, $result['next360KeepsThroughputHigh']);
        $t->same(64, strlen($result['next361Final']));
        $t->same(true, $result['next361Ready']);
        $t->same(64, strlen($result['next362Handoff']));
        $t->same('next358-361', $result['next362AfterReadyRange']);
        $t->same(64, strlen($result['next363SourceAudit']));
        $t->same(true, $result['next363PreservesCurrentSource']);
        $t->same(64, strlen($result['next364Preflight']));
        $t->same(true, $result['next364KeepsThroughputHigh']);
        $t->same(64, strlen($result['next365Final']));
        $t->same(true, $result['next365Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next358-365 ' . $name] = $callback;
}

return $tests;
