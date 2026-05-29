<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next638-653 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next638-653.php';

        $expectedStatuses = [];
        for ($next = 638; $next <= 653; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next638-653', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next638Handoff']));
        $t->same('next634-637', $result['next638AfterReadyRange']);
        $t->same(64, strlen($result['next639SourceAudit']));
        $t->same(true, $result['next639PreservesCurrentSource']);
        $t->same(64, strlen($result['next640Preflight']));
        $t->same(true, $result['next640KeepsThroughputHigh']);
        $t->same(64, strlen($result['next641Final']));
        $t->same(true, $result['next641Ready']);
        $t->same(64, strlen($result['next642Handoff']));
        $t->same('next638-641', $result['next642AfterReadyRange']);
        $t->same(true, $result['next645Ready']);
        $t->same(true, $result['next649Ready']);
        $t->same(64, strlen($result['next653Final']));
        $t->same(true, $result['next653Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next638-653 ' . $name] = $callback;
}

return $tests;
