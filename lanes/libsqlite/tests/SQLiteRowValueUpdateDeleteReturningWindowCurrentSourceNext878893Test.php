<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next878-893 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next878-893.php';

        $expectedStatuses = [];
        for ($next = 878; $next <= 893; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next878-893', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next878Handoff']));
        $t->same('next874-877', $result['next878AfterReadyRange']);
        $t->same(true, $result['next878ConsumesNext877Ready']);
        $t->same(64, strlen($result['next879SourceAudit']));
        $t->same(true, $result['next879PreservesCurrentSource']);
        $t->same(64, strlen($result['next880Preflight']));
        $t->same(true, $result['next880KeepsThroughputHigh']);
        $t->same(64, strlen($result['next881Final']));
        $t->same(true, $result['next881Ready']);
        $t->same(64, strlen($result['next882Handoff']));
        $t->same('next878-881', $result['next882AfterReadyRange']);
        $t->same(true, $result['next885Ready']);
        $t->same(true, $result['next889Ready']);
        $t->same(64, strlen($result['next893Final']));
        $t->same(true, $result['next893Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next878-893 ' . $name] = $callback;
}

return $tests;
