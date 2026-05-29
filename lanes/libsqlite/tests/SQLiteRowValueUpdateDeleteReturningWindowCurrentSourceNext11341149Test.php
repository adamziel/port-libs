<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1134-1149 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1134-1149.php';

        $expectedStatuses = [];
        for ($next = 1134; $next <= 1149; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1134-1149', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1134Handoff']));
        $t->same('next1130-1133', $result['next1134AfterReadyRange']);
        $t->same(true, $result['next1134ConsumesNext1133Ready']);
        $t->same(64, strlen($result['next1135SourceAudit']));
        $t->same(true, $result['next1135PreservesCurrentSource']);
        $t->same(64, strlen($result['next1136Preflight']));
        $t->same(true, $result['next1136KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1137Final']));
        $t->same(true, $result['next1137Ready']);
        $t->same(64, strlen($result['next1138Handoff']));
        $t->same('next1134-1137', $result['next1138AfterReadyRange']);
        $t->same(true, $result['next1141Ready']);
        $t->same(true, $result['next1145Ready']);
        $t->same(64, strlen($result['next1149Final']));
        $t->same(true, $result['next1149Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1134-1149 ' . $name] = $callback;
}

return $tests;
