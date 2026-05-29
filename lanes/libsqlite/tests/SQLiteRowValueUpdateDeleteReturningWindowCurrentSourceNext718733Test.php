<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next718-733 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next718-733.php';

        $expectedStatuses = [];
        for ($next = 718; $next <= 733; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next718-733', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next718Handoff']));
        $t->same('next714-717', $result['next718AfterReadyRange']);
        $t->same(true, $result['next718ConsumesNext717Ready']);
        $t->same(64, strlen($result['next719SourceAudit']));
        $t->same(true, $result['next719PreservesCurrentSource']);
        $t->same(64, strlen($result['next720Preflight']));
        $t->same(true, $result['next720KeepsThroughputHigh']);
        $t->same(64, strlen($result['next721Final']));
        $t->same(true, $result['next721Ready']);
        $t->same(64, strlen($result['next722Handoff']));
        $t->same('next718-721', $result['next722AfterReadyRange']);
        $t->same(true, $result['next725Ready']);
        $t->same(true, $result['next729Ready']);
        $t->same(64, strlen($result['next733Final']));
        $t->same(true, $result['next733Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next718-733 ' . $name] = $callback;
}

return $tests;
