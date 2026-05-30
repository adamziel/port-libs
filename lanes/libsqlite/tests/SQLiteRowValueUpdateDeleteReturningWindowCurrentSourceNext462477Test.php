<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next462-477 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next462-477.php';

        $expectedStatuses = [];
        for ($next = 462; $next <= 477; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next462-477', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next462Handoff']));
        $t->same('next458-461', $result['next462AfterReadyRange']);
        $t->same(64, strlen($result['next463SourceAudit']));
        $t->same(true, $result['next463PreservesCurrentSource']);
        $t->same(64, strlen($result['next464Preflight']));
        $t->same(true, $result['next464KeepsThroughputHigh']);
        $t->same(64, strlen($result['next465Final']));
        $t->same(true, $result['next465Ready']);
        $t->same(64, strlen($result['next466Handoff']));
        $t->same('next462-465', $result['next466AfterReadyRange']);
        $t->same(true, $result['next469Ready']);
        $t->same(true, $result['next473Ready']);
        $t->same(64, strlen($result['next477Final']));
        $t->same(true, $result['next477Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next462-477 ' . $name] = $callback;
}

return $tests;
