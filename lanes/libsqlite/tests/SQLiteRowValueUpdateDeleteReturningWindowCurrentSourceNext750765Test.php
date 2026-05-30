<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next750-765 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next750-765.php';

        $expectedStatuses = [];
        for ($next = 750; $next <= 765; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next750-765', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next750Handoff']));
        $t->same('next746-749', $result['next750AfterReadyRange']);
        $t->same(true, $result['next750ConsumesNext749Ready']);
        $t->same(64, strlen($result['next751SourceAudit']));
        $t->same(true, $result['next751PreservesCurrentSource']);
        $t->same(64, strlen($result['next752Preflight']));
        $t->same(true, $result['next752KeepsThroughputHigh']);
        $t->same(64, strlen($result['next753Final']));
        $t->same(true, $result['next753Ready']);
        $t->same(64, strlen($result['next754Handoff']));
        $t->same('next750-753', $result['next754AfterReadyRange']);
        $t->same(true, $result['next757Ready']);
        $t->same(true, $result['next761Ready']);
        $t->same(64, strlen($result['next765Final']));
        $t->same(true, $result['next765Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next750-765 ' . $name] = $callback;
}

return $tests;
