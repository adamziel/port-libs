<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next734-749 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next734-749.php';

        $expectedStatuses = [];
        for ($next = 734; $next <= 749; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next734-749', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next734Handoff']));
        $t->same('next730-733', $result['next734AfterReadyRange']);
        $t->same(true, $result['next734ConsumesNext733Ready']);
        $t->same(64, strlen($result['next735SourceAudit']));
        $t->same(true, $result['next735PreservesCurrentSource']);
        $t->same(64, strlen($result['next736Preflight']));
        $t->same(true, $result['next736KeepsThroughputHigh']);
        $t->same(64, strlen($result['next737Final']));
        $t->same(true, $result['next737Ready']);
        $t->same(64, strlen($result['next738Handoff']));
        $t->same('next734-737', $result['next738AfterReadyRange']);
        $t->same(true, $result['next741Ready']);
        $t->same(true, $result['next745Ready']);
        $t->same(64, strlen($result['next749Final']));
        $t->same(true, $result['next749Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next734-749 ' . $name] = $callback;
}

return $tests;
