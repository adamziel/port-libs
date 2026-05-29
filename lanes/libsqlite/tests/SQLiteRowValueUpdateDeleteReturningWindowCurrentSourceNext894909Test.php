<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next894-909 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next894-909.php';

        $expectedStatuses = [];
        for ($next = 894; $next <= 909; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next894-909', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next894Handoff']));
        $t->same('next890-893', $result['next894AfterReadyRange']);
        $t->same(true, $result['next894ConsumesNext893Ready']);
        $t->same(64, strlen($result['next895SourceAudit']));
        $t->same(true, $result['next895PreservesCurrentSource']);
        $t->same(64, strlen($result['next896Preflight']));
        $t->same(true, $result['next896KeepsThroughputHigh']);
        $t->same(64, strlen($result['next897Final']));
        $t->same(true, $result['next897Ready']);
        $t->same(64, strlen($result['next898Handoff']));
        $t->same('next894-897', $result['next898AfterReadyRange']);
        $t->same(true, $result['next901Ready']);
        $t->same(true, $result['next905Ready']);
        $t->same(64, strlen($result['next909Final']));
        $t->same(true, $result['next909Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next894-909 ' . $name] = $callback;
}

return $tests;
