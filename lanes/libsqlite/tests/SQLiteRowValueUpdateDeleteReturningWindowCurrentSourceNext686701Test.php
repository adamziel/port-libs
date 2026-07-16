<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next686-701 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next686-701.php';

        $expectedStatuses = [];
        for ($next = 686; $next <= 701; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next686-701', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next686Handoff']));
        $t->same('next682-685', $result['next686AfterReadyRange']);
        $t->same(true, $result['next686ConsumesNext685Ready']);
        $t->same(64, strlen($result['next687SourceAudit']));
        $t->same(true, $result['next687PreservesCurrentSource']);
        $t->same(64, strlen($result['next688Preflight']));
        $t->same(true, $result['next688KeepsThroughputHigh']);
        $t->same(64, strlen($result['next689Final']));
        $t->same(true, $result['next689Ready']);
        $t->same(64, strlen($result['next690Handoff']));
        $t->same('next686-689', $result['next690AfterReadyRange']);
        $t->same(true, $result['next693Ready']);
        $t->same(true, $result['next697Ready']);
        $t->same(64, strlen($result['next701Final']));
        $t->same(true, $result['next701Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next686-701 ' . $name] = $callback;
}

return $tests;
