<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1150-1165 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1150-1165.php';

        $expectedStatuses = [];
        for ($next = 1150; $next <= 1165; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1150-1165', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1150Handoff']));
        $t->same('next1146-1149', $result['next1150AfterReadyRange']);
        $t->same(true, $result['next1150ConsumesNext1149Ready']);
        $t->same(64, strlen($result['next1151SourceAudit']));
        $t->same(true, $result['next1151PreservesCurrentSource']);
        $t->same(64, strlen($result['next1152Preflight']));
        $t->same(true, $result['next1152KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1153Final']));
        $t->same(true, $result['next1153Ready']);
        $t->same(64, strlen($result['next1154Handoff']));
        $t->same('next1150-1153', $result['next1154AfterReadyRange']);
        $t->same(true, $result['next1157Ready']);
        $t->same(true, $result['next1161Ready']);
        $t->same(64, strlen($result['next1165Final']));
        $t->same(true, $result['next1165Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1150-1165 ' . $name] = $callback;
}

return $tests;
