<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined ready-publication final continuation seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-ready-publication-final-continuation.php';

        $t->same('rowvalue-update-delete-returning-window-ready-publication-final-continuation', $result['status']);
        $t->same([
            'final-continuation-handoff',
            'final-continuation-source-audit',
            'final-continuation-throughput-preflight',
            'final-continuation-ready-seal',
        ], $result['candidatePhases']);
        $t->same(64, strlen($result['handoffDigest']));
        $t->same('next970-973', $result['handoffAfterReadyRange']);
        $t->same(true, $result['handoffConsumesPreviousReady']);
        $t->same(64, strlen($result['sourceAuditDigest']));
        $t->same(true, $result['sourceAuditPreservesCurrentSource']);
        $t->same(64, strlen($result['preflightDigest']));
        $t->same(true, $result['preflightKeepsThroughputHigh']);
        $t->same(64, strlen($result['firstSealDigest']));
        $t->same(true, $result['firstSealReady']);
        $t->same(64, strlen($result['secondHandoffDigest']));
        $t->same('next974-977', $result['secondHandoffAfterReadyRange']);
        $t->same(true, $result['middleSealReady']);
        $t->same(true, $result['lateSealReady']);
        $t->same(64, strlen($result['finalSealDigest']));
        $t->same(true, $result['finalSealReady']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window ready-publication final continuation ' . $name] = $callback;
}

return $tests;
