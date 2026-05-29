<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next366-373 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next366-373.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next366-373', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next366',
            'rowvalue-update-delete-returning-window-current-source-next367',
            'rowvalue-update-delete-returning-window-current-source-next368',
            'rowvalue-update-delete-returning-window-current-source-next369',
            'rowvalue-update-delete-returning-window-current-source-next370',
            'rowvalue-update-delete-returning-window-current-source-next371',
            'rowvalue-update-delete-returning-window-current-source-next372',
            'rowvalue-update-delete-returning-window-current-source-next373',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next366Handoff']));
        $t->same('next358-365', $result['next366AfterReadyRange']);
        $t->same(64, strlen($result['next367SourceAudit']));
        $t->same(true, $result['next367PreservesCurrentSource']);
        $t->same(64, strlen($result['next368Preflight']));
        $t->same(true, $result['next368KeepsThroughputHigh']);
        $t->same(64, strlen($result['next369Final']));
        $t->same(true, $result['next369Ready']);
        $t->same(64, strlen($result['next370Handoff']));
        $t->same('next366-369', $result['next370AfterReadyRange']);
        $t->same(64, strlen($result['next371SourceAudit']));
        $t->same(true, $result['next371PreservesCurrentSource']);
        $t->same(64, strlen($result['next372Preflight']));
        $t->same(true, $result['next372KeepsThroughputHigh']);
        $t->same(64, strlen($result['next373Final']));
        $t->same(true, $result['next373Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next366-373 ' . $name] = $callback;
}

return $tests;
