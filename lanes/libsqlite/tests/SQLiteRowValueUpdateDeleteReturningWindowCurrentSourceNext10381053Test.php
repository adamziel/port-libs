<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next1038-1053 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next1038-1053.php';

        $expectedStatuses = [];
        for ($next = 1038; $next <= 1053; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next1038-1053', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1038Handoff']));
        $t->same('next1034-1037', $result['next1038AfterReadyRange']);
        $t->same(true, $result['next1038ConsumesNext1037Ready']);
        $t->same(64, strlen($result['next1039SourceAudit']));
        $t->same(true, $result['next1039PreservesCurrentSource']);
        $t->same(64, strlen($result['next1040Preflight']));
        $t->same(true, $result['next1040KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1041Final']));
        $t->same(true, $result['next1041Ready']);
        $t->same(64, strlen($result['next1042Handoff']));
        $t->same('next1038-1041', $result['next1042AfterReadyRange']);
        $t->same(true, $result['next1045Ready']);
        $t->same(true, $result['next1049Ready']);
        $t->same(64, strlen($result['next1053Final']));
        $t->same(true, $result['next1053Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next1038-1053 ' . $name] = $callback;
}

return $tests;
