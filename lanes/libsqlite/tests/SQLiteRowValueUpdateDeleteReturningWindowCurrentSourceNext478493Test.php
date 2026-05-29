<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next478-493 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next478-493.php';

        $expectedStatuses = [];
        for ($next = 478; $next <= 493; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next478-493', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next478Handoff']));
        $t->same('next474-477', $result['next478AfterReadyRange']);
        $t->same(64, strlen($result['next479SourceAudit']));
        $t->same(true, $result['next479PreservesCurrentSource']);
        $t->same(64, strlen($result['next480Preflight']));
        $t->same(true, $result['next480KeepsThroughputHigh']);
        $t->same(64, strlen($result['next481Final']));
        $t->same(true, $result['next481Ready']);
        $t->same(64, strlen($result['next482Handoff']));
        $t->same('next478-481', $result['next482AfterReadyRange']);
        $t->same(true, $result['next485Ready']);
        $t->same(true, $result['next489Ready']);
        $t->same(64, strlen($result['next493Final']));
        $t->same(true, $result['next493Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next478-493 ' . $name] = $callback;
}

return $tests;
