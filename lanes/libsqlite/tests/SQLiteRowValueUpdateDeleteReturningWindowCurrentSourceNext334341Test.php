<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next334-341 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next334-341.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next334-341', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next334',
            'rowvalue-update-delete-returning-window-current-source-next335',
            'rowvalue-update-delete-returning-window-current-source-next336',
            'rowvalue-update-delete-returning-window-current-source-next337',
            'rowvalue-update-delete-returning-window-current-source-next338',
            'rowvalue-update-delete-returning-window-current-source-next339',
            'rowvalue-update-delete-returning-window-current-source-next340',
            'rowvalue-update-delete-returning-window-current-source-next341',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next334Handoff']));
        $t->same('next326-333', $result['next334AfterReadyRange']);
        $t->same(64, strlen($result['next335SourceAudit']));
        $t->same(true, $result['next335PreservesCurrentSource']);
        $t->same(64, strlen($result['next336Preflight']));
        $t->same(true, $result['next336KeepsThroughputHigh']);
        $t->same(64, strlen($result['next337Final']));
        $t->same(true, $result['next337Ready']);
        $t->same(64, strlen($result['next338Handoff']));
        $t->same('next334-337', $result['next338AfterReadyRange']);
        $t->same(64, strlen($result['next339SourceAudit']));
        $t->same(true, $result['next339PreservesCurrentSource']);
        $t->same(64, strlen($result['next340Preflight']));
        $t->same(true, $result['next340KeepsThroughputHigh']);
        $t->same(64, strlen($result['next341Final']));
        $t->same(true, $result['next341Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next334-341 ' . $name] = $callback;
}

return $tests;
