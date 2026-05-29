<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next558-573 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next558-573.php';

        $expectedStatuses = [];
        for ($next = 558; $next <= 573; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next558-573', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next558Handoff']));
        $t->same('next554-557', $result['next558AfterReadyRange']);
        $t->same(64, strlen($result['next559SourceAudit']));
        $t->same(true, $result['next559PreservesCurrentSource']);
        $t->same(64, strlen($result['next560Preflight']));
        $t->same(true, $result['next560KeepsThroughputHigh']);
        $t->same(64, strlen($result['next561Final']));
        $t->same(true, $result['next561Ready']);
        $t->same(64, strlen($result['next562Handoff']));
        $t->same('next558-561', $result['next562AfterReadyRange']);
        $t->same(true, $result['next565Ready']);
        $t->same(true, $result['next569Ready']);
        $t->same(64, strlen($result['next573Final']));
        $t->same(true, $result['next573Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next558-573 ' . $name] = $callback;
}

return $tests;
