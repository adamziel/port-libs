<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next374-381 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next374-381.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next374-381', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next374',
            'rowvalue-update-delete-returning-window-current-source-next375',
            'rowvalue-update-delete-returning-window-current-source-next376',
            'rowvalue-update-delete-returning-window-current-source-next377',
            'rowvalue-update-delete-returning-window-current-source-next378',
            'rowvalue-update-delete-returning-window-current-source-next379',
            'rowvalue-update-delete-returning-window-current-source-next380',
            'rowvalue-update-delete-returning-window-current-source-next381',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next374Handoff']));
        $t->same('next370-373', $result['next374AfterReadyRange']);
        $t->same(64, strlen($result['next375SourceAudit']));
        $t->same(true, $result['next375PreservesCurrentSource']);
        $t->same(64, strlen($result['next376Preflight']));
        $t->same(true, $result['next376KeepsThroughputHigh']);
        $t->same(64, strlen($result['next377Final']));
        $t->same(true, $result['next377Ready']);
        $t->same(64, strlen($result['next378Handoff']));
        $t->same('next374-377', $result['next378AfterReadyRange']);
        $t->same(64, strlen($result['next379SourceAudit']));
        $t->same(true, $result['next379PreservesCurrentSource']);
        $t->same(64, strlen($result['next380Preflight']));
        $t->same(true, $result['next380KeepsThroughputHigh']);
        $t->same(64, strlen($result['next381Final']));
        $t->same(true, $result['next381Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next374-381 ' . $name] = $callback;
}

return $tests;
