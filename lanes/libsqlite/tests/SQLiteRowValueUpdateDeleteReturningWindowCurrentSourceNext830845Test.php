<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next830-845 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next830-845.php';

        $expectedStatuses = [];
        for ($next = 830; $next <= 845; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next830-845', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next830Handoff']));
        $t->same('next826-829', $result['next830AfterReadyRange']);
        $t->same(true, $result['next830ConsumesNext829Ready']);
        $t->same(64, strlen($result['next831SourceAudit']));
        $t->same(true, $result['next831PreservesCurrentSource']);
        $t->same(64, strlen($result['next832Preflight']));
        $t->same(true, $result['next832KeepsThroughputHigh']);
        $t->same(64, strlen($result['next833Final']));
        $t->same(true, $result['next833Ready']);
        $t->same(64, strlen($result['next834Handoff']));
        $t->same('next830-833', $result['next834AfterReadyRange']);
        $t->same(true, $result['next837Ready']);
        $t->same(true, $result['next841Ready']);
        $t->same(64, strlen($result['next845Final']));
        $t->same(true, $result['next845Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next830-845 ' . $name] = $callback;
}

return $tests;
