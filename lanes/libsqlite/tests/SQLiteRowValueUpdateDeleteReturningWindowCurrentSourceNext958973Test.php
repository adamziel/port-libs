<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next958-973 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next958-973.php';

        $expectedStatuses = [];
        for ($next = 958; $next <= 973; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next958-973', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next958Handoff']));
        $t->same('next954-957', $result['next958AfterReadyRange']);
        $t->same(true, $result['next958ConsumesNext957Ready']);
        $t->same(64, strlen($result['next959SourceAudit']));
        $t->same(true, $result['next959PreservesCurrentSource']);
        $t->same(64, strlen($result['next960Preflight']));
        $t->same(true, $result['next960KeepsThroughputHigh']);
        $t->same(64, strlen($result['next961Final']));
        $t->same(true, $result['next961Ready']);
        $t->same(64, strlen($result['next962Handoff']));
        $t->same('next958-961', $result['next962AfterReadyRange']);
        $t->same(true, $result['next965Ready']);
        $t->same(true, $result['next969Ready']);
        $t->same(64, strlen($result['next973Final']));
        $t->same(true, $result['next973Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next958-973 ' . $name] = $callback;
}

return $tests;
