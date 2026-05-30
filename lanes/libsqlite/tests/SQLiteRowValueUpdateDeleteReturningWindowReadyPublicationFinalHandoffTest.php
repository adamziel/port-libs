<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined final handoff current-source publication seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-ready-publication-final-handoff.php';

        $expectedStatuses = [];
        for ($next = 1150; $next <= 1165; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-ready-publication-final-handoff', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['initialHandoffToken']));
        $t->same('next1146-1149', $result['initialAfterReadyRange']);
        $t->same(true, $result['initialConsumesPriorReady']);
        $t->same(64, strlen($result['initialSourceAuditToken']));
        $t->same(true, $result['initialPreservesCurrentSource']);
        $t->same(64, strlen($result['initialPreflightToken']));
        $t->same(true, $result['initialKeepsThroughputHigh']);
        $t->same(64, strlen($result['initialFinalToken']));
        $t->same(true, $result['initialReady']);
        $t->same(64, strlen($result['secondHandoffToken']));
        $t->same('next1150-1153', $result['secondAfterReadyRange']);
        $t->same(true, $result['secondSealReady']);
        $t->same(true, $result['thirdSealReady']);
        $t->same(64, strlen($result['finalSealToken']));
        $t->same(true, $result['finalSealReady']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window ready publication final handoff ' . $name] = $callback;
}

return $tests;
