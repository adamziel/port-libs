<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1118-1133 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1118-1133.php';

        $expectedStatuses = [];
        for ($next = 1118; $next <= 1133; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1118-1133', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1118Handoff']));
        $t->same('next1114-1117', $result['next1118AfterReadyRange']);
        $t->same(true, $result['next1118ConsumesNext1117Ready']);
        $t->same(64, strlen($result['next1119SourceAudit']));
        $t->same(true, $result['next1119PreservesCurrentSource']);
        $t->same(64, strlen($result['next1120Preflight']));
        $t->same(true, $result['next1120KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1121Final']));
        $t->same(true, $result['next1121Ready']);
        $t->same(64, strlen($result['next1122Handoff']));
        $t->same('next1118-1121', $result['next1122AfterReadyRange']);
        $t->same(true, $result['next1125Ready']);
        $t->same(true, $result['next1129Ready']);
        $t->same(64, strlen($result['next1133Final']));
        $t->same(true, $result['next1133Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1118-1133 ' . $name] = $callback;
}

return $tests;
