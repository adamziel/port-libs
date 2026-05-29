<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1006-1021 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1006-1021.php';

        $expectedStatuses = [];
        for ($next = 1006; $next <= 1021; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1006-1021', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1006Handoff']));
        $t->same('next1002-1005', $result['next1006AfterReadyRange']);
        $t->same(true, $result['next1006ConsumesNext1005Ready']);
        $t->same(64, strlen($result['next1007SourceAudit']));
        $t->same(true, $result['next1007PreservesCurrentSource']);
        $t->same(64, strlen($result['next1008Preflight']));
        $t->same(true, $result['next1008KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1009Final']));
        $t->same(true, $result['next1009Ready']);
        $t->same(64, strlen($result['next1010Handoff']));
        $t->same('next1006-1009', $result['next1010AfterReadyRange']);
        $t->same(true, $result['next1013Ready']);
        $t->same(true, $result['next1017Ready']);
        $t->same(64, strlen($result['next1021Final']));
        $t->same(true, $result['next1021Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1006-1021 ' . $name] = $callback;
}

return $tests;
