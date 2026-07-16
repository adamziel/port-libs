<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next702-717 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next702-717.php';

        $expectedStatuses = [];
        for ($next = 702; $next <= 717; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next702-717', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next702Handoff']));
        $t->same('next698-701', $result['next702AfterReadyRange']);
        $t->same(true, $result['next702ConsumesNext701Ready']);
        $t->same(64, strlen($result['next703SourceAudit']));
        $t->same(true, $result['next703PreservesCurrentSource']);
        $t->same(64, strlen($result['next704Preflight']));
        $t->same(true, $result['next704KeepsThroughputHigh']);
        $t->same(64, strlen($result['next705Final']));
        $t->same(true, $result['next705Ready']);
        $t->same(64, strlen($result['next706Handoff']));
        $t->same('next702-705', $result['next706AfterReadyRange']);
        $t->same(true, $result['next709Ready']);
        $t->same(true, $result['next713Ready']);
        $t->same(64, strlen($result['next717Final']));
        $t->same(true, $result['next717Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next702-717 ' . $name] = $callback;
}

return $tests;
