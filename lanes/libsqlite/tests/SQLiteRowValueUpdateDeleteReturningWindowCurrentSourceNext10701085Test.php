<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1070-1085 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1070-1085.php';

        $expectedStatuses = [];
        for ($next = 1070; $next <= 1085; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1070-1085', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1070Handoff']));
        $t->same('next1066-1069', $result['next1070AfterReadyRange']);
        $t->same(true, $result['next1070ConsumesNext1069Ready']);
        $t->same(64, strlen($result['next1071SourceAudit']));
        $t->same(true, $result['next1071PreservesCurrentSource']);
        $t->same(64, strlen($result['next1072Preflight']));
        $t->same(true, $result['next1072KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1073Final']));
        $t->same(true, $result['next1073Ready']);
        $t->same(64, strlen($result['next1074Handoff']));
        $t->same('next1070-1073', $result['next1074AfterReadyRange']);
        $t->same(true, $result['next1077Ready']);
        $t->same(true, $result['next1081Ready']);
        $t->same(64, strlen($result['next1085Final']));
        $t->same(true, $result['next1085Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1070-1085 ' . $name] = $callback;
}

return $tests;
