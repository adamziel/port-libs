<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next590-605 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next590-605.php';

        $expectedStatuses = [];
        for ($next = 590; $next <= 605; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next590-605', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next590Handoff']));
        $t->same('next586-589', $result['next590AfterReadyRange']);
        $t->same(64, strlen($result['next591SourceAudit']));
        $t->same(true, $result['next591PreservesCurrentSource']);
        $t->same(64, strlen($result['next592Preflight']));
        $t->same(true, $result['next592KeepsThroughputHigh']);
        $t->same(64, strlen($result['next593Final']));
        $t->same(true, $result['next593Ready']);
        $t->same(64, strlen($result['next594Handoff']));
        $t->same('next590-593', $result['next594AfterReadyRange']);
        $t->same(true, $result['next597Ready']);
        $t->same(true, $result['next601Ready']);
        $t->same(64, strlen($result['next605Final']));
        $t->same(true, $result['next605Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next590-605 ' . $name] = $callback;
}

return $tests;
