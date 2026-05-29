<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next526-541 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-publication-seal-continuation.php';

        $expectedStatuses = [];
        for ($next = 526; $next <= 541; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next526-541', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next526Handoff']));
        $t->same('next522-525', $result['next526AfterReadyRange']);
        $t->same(64, strlen($result['next527SourceAudit']));
        $t->same(true, $result['next527PreservesCurrentSource']);
        $t->same(64, strlen($result['next528Preflight']));
        $t->same(true, $result['next528KeepsThroughputHigh']);
        $t->same(64, strlen($result['next529Final']));
        $t->same(true, $result['next529Ready']);
        $t->same(64, strlen($result['next530Handoff']));
        $t->same('next526-529', $result['next530AfterReadyRange']);
        $t->same(true, $result['next533Ready']);
        $t->same(true, $result['next537Ready']);
        $t->same(64, strlen($result['next541Final']));
        $t->same(true, $result['next541Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next526-541 ' . $name] = $callback;
}

return $tests;
