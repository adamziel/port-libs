<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined final continuation seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-final-continuation-seal.php';

        $t->same('rowvalue-update-delete-returning-window-final-continuation-seal', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next342',
            'rowvalue-update-delete-returning-window-current-source-next343',
            'rowvalue-update-delete-returning-window-current-source-next344',
            'rowvalue-update-delete-returning-window-current-source-next345',
            'rowvalue-update-delete-returning-window-current-source-next346',
            'rowvalue-update-delete-returning-window-current-source-next347',
            'rowvalue-update-delete-returning-window-current-source-next348',
            'rowvalue-update-delete-returning-window-current-source-next349',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next342Handoff']));
        $t->same('next334-341', $result['next342AfterReadyRange']);
        $t->same(64, strlen($result['next343SourceAudit']));
        $t->same(true, $result['next343PreservesCurrentSource']);
        $t->same(64, strlen($result['next344Preflight']));
        $t->same(true, $result['next344KeepsThroughputHigh']);
        $t->same(64, strlen($result['next345Final']));
        $t->same(true, $result['next345Ready']);
        $t->same(64, strlen($result['next346Handoff']));
        $t->same('next342-345', $result['next346AfterReadyRange']);
        $t->same(64, strlen($result['next347SourceAudit']));
        $t->same(true, $result['next347PreservesCurrentSource']);
        $t->same(64, strlen($result['next348Preflight']));
        $t->same(true, $result['next348KeepsThroughputHigh']);
        $t->same(64, strlen($result['next349Final']));
        $t->same(true, $result['next349Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window final continuation seal ' . $name] = $callback;
}

return $tests;
