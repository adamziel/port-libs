<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next382-397 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next382-397.php';

        $expectedStatuses = [];
        for ($next = 382; $next <= 397; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next382-397', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next382Handoff']));
        $t->same('next378-381', $result['next382AfterReadyRange']);
        $t->same(64, strlen($result['next383SourceAudit']));
        $t->same(true, $result['next383PreservesCurrentSource']);
        $t->same(64, strlen($result['next384Preflight']));
        $t->same(true, $result['next384KeepsThroughputHigh']);
        $t->same(64, strlen($result['next385Final']));
        $t->same(true, $result['next385Ready']);
        $t->same(64, strlen($result['next386Handoff']));
        $t->same('next382-385', $result['next386AfterReadyRange']);
        $t->same(true, $result['next389Ready']);
        $t->same(true, $result['next393Ready']);
        $t->same(64, strlen($result['next397Final']));
        $t->same(true, $result['next397Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next382-397 ' . $name] = $callback;
}

return $tests;
