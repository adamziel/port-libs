<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1054-1069 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1054-1069.php';

        $expectedStatuses = [];
        for ($next = 1054; $next <= 1069; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1054-1069', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1054Handoff']));
        $t->same('next1050-1053', $result['next1054AfterReadyRange']);
        $t->same(true, $result['next1054ConsumesNext1053Ready']);
        $t->same(64, strlen($result['next1055SourceAudit']));
        $t->same(true, $result['next1055PreservesCurrentSource']);
        $t->same(64, strlen($result['next1056Preflight']));
        $t->same(true, $result['next1056KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1057Final']));
        $t->same(true, $result['next1057Ready']);
        $t->same(64, strlen($result['next1058Handoff']));
        $t->same('next1054-1057', $result['next1058AfterReadyRange']);
        $t->same(true, $result['next1061Ready']);
        $t->same(true, $result['next1065Ready']);
        $t->same(64, strlen($result['next1069Final']));
        $t->same(true, $result['next1069Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1054-1069 ' . $name] = $callback;
}

return $tests;
