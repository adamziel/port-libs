<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next798-813 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next798-813.php';

        $expectedStatuses = [];
        for ($next = 798; $next <= 813; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next798-813', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next798Handoff']));
        $t->same('next794-797', $result['next798AfterReadyRange']);
        $t->same(true, $result['next798ConsumesNext797Ready']);
        $t->same(64, strlen($result['next799SourceAudit']));
        $t->same(true, $result['next799PreservesCurrentSource']);
        $t->same(64, strlen($result['next800Preflight']));
        $t->same(true, $result['next800KeepsThroughputHigh']);
        $t->same(64, strlen($result['next801Final']));
        $t->same(true, $result['next801Ready']);
        $t->same(64, strlen($result['next802Handoff']));
        $t->same('next798-801', $result['next802AfterReadyRange']);
        $t->same(true, $result['next805Ready']);
        $t->same(true, $result['next809Ready']);
        $t->same(64, strlen($result['next813Final']));
        $t->same(true, $result['next813Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next798-813 ' . $name] = $callback;
}

return $tests;
