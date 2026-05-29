<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next846-861 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next846-861.php';

        $expectedStatuses = [];
        for ($next = 846; $next <= 861; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next846-861', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next846Handoff']));
        $t->same('next842-845', $result['next846AfterReadyRange']);
        $t->same(true, $result['next846ConsumesNext845Ready']);
        $t->same(64, strlen($result['next847SourceAudit']));
        $t->same(true, $result['next847PreservesCurrentSource']);
        $t->same(64, strlen($result['next848Preflight']));
        $t->same(true, $result['next848KeepsThroughputHigh']);
        $t->same(64, strlen($result['next849Final']));
        $t->same(true, $result['next849Ready']);
        $t->same(64, strlen($result['next850Handoff']));
        $t->same('next846-849', $result['next850AfterReadyRange']);
        $t->same(true, $result['next853Ready']);
        $t->same(true, $result['next857Ready']);
        $t->same(64, strlen($result['next861Final']));
        $t->same(true, $result['next861Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next846-861 ' . $name] = $callback;
}

return $tests;
