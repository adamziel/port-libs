<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next430-445 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next430-445.php';

        $expectedStatuses = [];
        for ($next = 430; $next <= 445; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next430-445', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next430Handoff']));
        $t->same('next426-429', $result['next430AfterReadyRange']);
        $t->same(64, strlen($result['next431SourceAudit']));
        $t->same(true, $result['next431PreservesCurrentSource']);
        $t->same(64, strlen($result['next432Preflight']));
        $t->same(true, $result['next432KeepsThroughputHigh']);
        $t->same(64, strlen($result['next433Final']));
        $t->same(true, $result['next433Ready']);
        $t->same(64, strlen($result['next434Handoff']));
        $t->same('next430-433', $result['next434AfterReadyRange']);
        $t->same(true, $result['next437Ready']);
        $t->same(true, $result['next441Ready']);
        $t->same(64, strlen($result['next445Final']));
        $t->same(true, $result['next445Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next430-445 ' . $name] = $callback;
}

return $tests;
