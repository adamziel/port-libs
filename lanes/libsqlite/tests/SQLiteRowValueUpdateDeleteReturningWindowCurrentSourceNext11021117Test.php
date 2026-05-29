<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1102-1117 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1102-1117.php';

        $expectedStatuses = [];
        for ($next = 1102; $next <= 1117; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1102-1117', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1102Handoff']));
        $t->same('next1098-1101', $result['next1102AfterReadyRange']);
        $t->same(true, $result['next1102ConsumesNext1101Ready']);
        $t->same(64, strlen($result['next1103SourceAudit']));
        $t->same(true, $result['next1103PreservesCurrentSource']);
        $t->same(64, strlen($result['next1104Preflight']));
        $t->same(true, $result['next1104KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1105Final']));
        $t->same(true, $result['next1105Ready']);
        $t->same(64, strlen($result['next1106Handoff']));
        $t->same('next1102-1105', $result['next1106AfterReadyRange']);
        $t->same(true, $result['next1109Ready']);
        $t->same(true, $result['next1113Ready']);
        $t->same(64, strlen($result['next1117Final']));
        $t->same(true, $result['next1117Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1102-1117 ' . $name] = $callback;
}

return $tests;
