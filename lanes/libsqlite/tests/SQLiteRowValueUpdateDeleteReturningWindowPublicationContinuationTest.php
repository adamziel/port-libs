<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined publication current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-publication-continuation.php';

        $t->same('rowvalue-update-delete-returning-window-publication-continuation', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next350',
            'rowvalue-update-delete-returning-window-current-source-next351',
            'rowvalue-update-delete-returning-window-current-source-next352',
            'rowvalue-update-delete-returning-window-current-source-next353',
            'rowvalue-update-delete-returning-window-current-source-next354',
            'rowvalue-update-delete-returning-window-current-source-next355',
            'rowvalue-update-delete-returning-window-current-source-next356',
            'rowvalue-update-delete-returning-window-current-source-next357',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next350Handoff']));
        $t->same('next342-349', $result['next350AfterReadyRange']);
        $t->same(64, strlen($result['next351SourceAudit']));
        $t->same(true, $result['next351PreservesCurrentSource']);
        $t->same(64, strlen($result['next352Preflight']));
        $t->same(true, $result['next352KeepsThroughputHigh']);
        $t->same(64, strlen($result['next353Final']));
        $t->same(true, $result['next353Ready']);
        $t->same(64, strlen($result['next354Handoff']));
        $t->same('next350-353', $result['next354AfterReadyRange']);
        $t->same(64, strlen($result['next355SourceAudit']));
        $t->same(true, $result['next355PreservesCurrentSource']);
        $t->same(64, strlen($result['next356Preflight']));
        $t->same(true, $result['next356KeepsThroughputHigh']);
        $t->same(64, strlen($result['next357Final']));
        $t->same(true, $result['next357Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window publication continuation ' . $name] = $callback;
}

return $tests;
