<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next446-461 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next446-461.php';

        $expectedStatuses = [];
        for ($next = 446; $next <= 461; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next446-461', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next446Handoff']));
        $t->same('next442-445', $result['next446AfterReadyRange']);
        $t->same(64, strlen($result['next447SourceAudit']));
        $t->same(true, $result['next447PreservesCurrentSource']);
        $t->same(64, strlen($result['next448Preflight']));
        $t->same(true, $result['next448KeepsThroughputHigh']);
        $t->same(64, strlen($result['next449Final']));
        $t->same(true, $result['next449Ready']);
        $t->same(64, strlen($result['next450Handoff']));
        $t->same('next446-449', $result['next450AfterReadyRange']);
        $t->same(true, $result['next453Ready']);
        $t->same(true, $result['next457Ready']);
        $t->same(64, strlen($result['next461Final']));
        $t->same(true, $result['next461Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next446-461 ' . $name] = $callback;
}

return $tests;
