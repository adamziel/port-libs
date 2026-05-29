<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next862-877 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next862-877.php';

        $expectedStatuses = [];
        for ($next = 862; $next <= 877; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next862-877', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next862Handoff']));
        $t->same('next858-861', $result['next862AfterReadyRange']);
        $t->same(true, $result['next862ConsumesNext861Ready']);
        $t->same(64, strlen($result['next863SourceAudit']));
        $t->same(true, $result['next863PreservesCurrentSource']);
        $t->same(64, strlen($result['next864Preflight']));
        $t->same(true, $result['next864KeepsThroughputHigh']);
        $t->same(64, strlen($result['next865Final']));
        $t->same(true, $result['next865Ready']);
        $t->same(64, strlen($result['next866Handoff']));
        $t->same('next862-865', $result['next866AfterReadyRange']);
        $t->same(true, $result['next869Ready']);
        $t->same(true, $result['next873Ready']);
        $t->same(64, strlen($result['next877Final']));
        $t->same(true, $result['next877Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next862-877 ' . $name] = $callback;
}

return $tests;
