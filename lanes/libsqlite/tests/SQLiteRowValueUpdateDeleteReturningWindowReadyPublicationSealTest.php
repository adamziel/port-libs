<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'ready publication handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-ready-publication-seal.php';

        $expectedStatuses = [];
        for ($next = 990; $next <= 1005; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-ready-publication-seal', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next990Handoff']));
        $t->same('next986-989', $result['next990AfterReadyRange']);
        $t->same(true, $result['next990ConsumesNext989Ready']);
        $t->same(64, strlen($result['next991SourceAudit']));
        $t->same(true, $result['next991PreservesCurrentSource']);
        $t->same(64, strlen($result['next992Preflight']));
        $t->same(true, $result['next992KeepsThroughputHigh']);
        $t->same(64, strlen($result['next993Final']));
        $t->same(true, $result['next993Ready']);
        $t->same(64, strlen($result['next994Handoff']));
        $t->same('next990-993', $result['next994AfterReadyRange']);
        $t->same(true, $result['next997Ready']);
        $t->same(true, $result['next1001Ready']);
        $t->same(64, strlen($result['next1005Final']));
        $t->same(true, $result['next1005Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window ready publication seal ' . $name] = $callback;
}

return $tests;
