<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next942-957 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next942-957.php';

        $expectedStatuses = [];
        for ($next = 942; $next <= 957; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next942-957', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next942Handoff']));
        $t->same('next938-941', $result['next942AfterReadyRange']);
        $t->same(true, $result['next942ConsumesNext941Ready']);
        $t->same(64, strlen($result['next943SourceAudit']));
        $t->same(true, $result['next943PreservesCurrentSource']);
        $t->same(64, strlen($result['next944Preflight']));
        $t->same(true, $result['next944KeepsThroughputHigh']);
        $t->same(64, strlen($result['next945Final']));
        $t->same(true, $result['next945Ready']);
        $t->same(64, strlen($result['next946Handoff']));
        $t->same('next942-945', $result['next946AfterReadyRange']);
        $t->same(true, $result['next949Ready']);
        $t->same(true, $result['next953Ready']);
        $t->same(64, strlen($result['next957Final']));
        $t->same(true, $result['next957Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next942-957 ' . $name] = $callback;
}

return $tests;
