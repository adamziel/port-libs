<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next398-413 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next398-413.php';

        $expectedStatuses = [];
        for ($next = 398; $next <= 413; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next398-413', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next398Handoff']));
        $t->same('next394-397', $result['next398AfterReadyRange']);
        $t->same(64, strlen($result['next399SourceAudit']));
        $t->same(true, $result['next399PreservesCurrentSource']);
        $t->same(64, strlen($result['next400Preflight']));
        $t->same(true, $result['next400KeepsThroughputHigh']);
        $t->same(64, strlen($result['next401Final']));
        $t->same(true, $result['next401Ready']);
        $t->same(64, strlen($result['next402Handoff']));
        $t->same('next398-401', $result['next402AfterReadyRange']);
        $t->same(true, $result['next405Ready']);
        $t->same(true, $result['next409Ready']);
        $t->same(64, strlen($result['next413Final']));
        $t->same(true, $result['next413Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next398-413 ' . $name] = $callback;
}

return $tests;
