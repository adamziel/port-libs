<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next782-797 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next782-797.php';

        $expectedStatuses = [];
        for ($next = 782; $next <= 797; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next782-797', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next782Handoff']));
        $t->same('next778-781', $result['next782AfterReadyRange']);
        $t->same(true, $result['next782ConsumesNext781Ready']);
        $t->same(64, strlen($result['next783SourceAudit']));
        $t->same(true, $result['next783PreservesCurrentSource']);
        $t->same(64, strlen($result['next784Preflight']));
        $t->same(true, $result['next784KeepsThroughputHigh']);
        $t->same(64, strlen($result['next785Final']));
        $t->same(true, $result['next785Ready']);
        $t->same(64, strlen($result['next786Handoff']));
        $t->same('next782-785', $result['next786AfterReadyRange']);
        $t->same(true, $result['next789Ready']);
        $t->same(true, $result['next793Ready']);
        $t->same(64, strlen($result['next797Final']));
        $t->same(true, $result['next797Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next782-797 ' . $name] = $callback;
}

return $tests;
