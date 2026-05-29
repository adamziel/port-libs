<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next814-829 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next814-829.php';

        $expectedStatuses = [];
        for ($next = 814; $next <= 829; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next814-829', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next814Handoff']));
        $t->same('next810-813', $result['next814AfterReadyRange']);
        $t->same(true, $result['next814ConsumesNext813Ready']);
        $t->same(64, strlen($result['next815SourceAudit']));
        $t->same(true, $result['next815PreservesCurrentSource']);
        $t->same(64, strlen($result['next816Preflight']));
        $t->same(true, $result['next816KeepsThroughputHigh']);
        $t->same(64, strlen($result['next817Final']));
        $t->same(true, $result['next817Ready']);
        $t->same(64, strlen($result['next818Handoff']));
        $t->same('next814-817', $result['next818AfterReadyRange']);
        $t->same(true, $result['next821Ready']);
        $t->same(true, $result['next825Ready']);
        $t->same(64, strlen($result['next829Final']));
        $t->same(true, $result['next829Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next814-829 ' . $name] = $callback;
}

return $tests;
