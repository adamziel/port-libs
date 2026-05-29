<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1022-1037 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1022-1037.php';

        $expectedStatuses = [];
        for ($next = 1022; $next <= 1037; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1022-1037', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1022Handoff']));
        $t->same('next1018-1021', $result['next1022AfterReadyRange']);
        $t->same(true, $result['next1022ConsumesNext1021Ready']);
        $t->same(64, strlen($result['next1023SourceAudit']));
        $t->same(true, $result['next1023PreservesCurrentSource']);
        $t->same(64, strlen($result['next1024Preflight']));
        $t->same(true, $result['next1024KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1025Final']));
        $t->same(true, $result['next1025Ready']);
        $t->same(64, strlen($result['next1026Handoff']));
        $t->same('next1022-1025', $result['next1026AfterReadyRange']);
        $t->same(true, $result['next1029Ready']);
        $t->same(true, $result['next1033Ready']);
        $t->same(64, strlen($result['next1037Final']));
        $t->same(true, $result['next1037Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1022-1037 ' . $name] = $callback;
}

return $tests;
