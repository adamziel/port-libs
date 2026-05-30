<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next574-589 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next574-589.php';

        $expectedStatuses = [];
        for ($next = 574; $next <= 589; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next574-589', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next574Handoff']));
        $t->same('next570-573', $result['next574AfterReadyRange']);
        $t->same(64, strlen($result['next575SourceAudit']));
        $t->same(true, $result['next575PreservesCurrentSource']);
        $t->same(64, strlen($result['next576Preflight']));
        $t->same(true, $result['next576KeepsThroughputHigh']);
        $t->same(64, strlen($result['next577Final']));
        $t->same(true, $result['next577Ready']);
        $t->same(64, strlen($result['next578Handoff']));
        $t->same('next574-577', $result['next578AfterReadyRange']);
        $t->same(true, $result['next581Ready']);
        $t->same(true, $result['next585Ready']);
        $t->same(64, strlen($result['next589Final']));
        $t->same(true, $result['next589Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next574-589 ' . $name] = $callback;
}

return $tests;
