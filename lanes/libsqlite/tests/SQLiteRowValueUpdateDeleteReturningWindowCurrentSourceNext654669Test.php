<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next654-669 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next654-669.php';

        $expectedStatuses = [];
        for ($next = 654; $next <= 669; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next654-669', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next654Handoff']));
        $t->same('next650-653', $result['next654AfterReadyRange']);
        $t->same(64, strlen($result['next655SourceAudit']));
        $t->same(true, $result['next655PreservesCurrentSource']);
        $t->same(64, strlen($result['next656Preflight']));
        $t->same(true, $result['next656KeepsThroughputHigh']);
        $t->same(64, strlen($result['next657Final']));
        $t->same(true, $result['next657Ready']);
        $t->same(64, strlen($result['next658Handoff']));
        $t->same('next654-657', $result['next658AfterReadyRange']);
        $t->same(true, $result['next661Ready']);
        $t->same(true, $result['next665Ready']);
        $t->same(64, strlen($result['next669Final']));
        $t->same(true, $result['next669Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next654-669 ' . $name] = $callback;
}

return $tests;
