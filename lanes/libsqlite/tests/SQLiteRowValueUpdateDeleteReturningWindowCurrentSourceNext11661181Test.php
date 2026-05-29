<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1166-1181 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1166-1181.php';

        $expectedStatuses = [];
        for ($next = 1166; $next <= 1181; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1166-1181', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1166Handoff']));
        $t->same('next1162-1165', $result['next1166AfterReadyRange']);
        $t->same(true, $result['next1166ConsumesNext1165Ready']);
        $t->same(64, strlen($result['next1167SourceAudit']));
        $t->same(true, $result['next1167PreservesCurrentSource']);
        $t->same(64, strlen($result['next1168Preflight']));
        $t->same(true, $result['next1168KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1169Final']));
        $t->same(true, $result['next1169Ready']);
        $t->same(64, strlen($result['next1170Handoff']));
        $t->same('next1166-1169', $result['next1170AfterReadyRange']);
        $t->same(true, $result['next1173Ready']);
        $t->same(true, $result['next1177Ready']);
        $t->same(64, strlen($result['next1181Final']));
        $t->same(true, $result['next1181Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1166-1181 ' . $name] = $callback;
}

return $tests;
