<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next974-989 current-source handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next974-989.php';

        $expectedStatuses = [];
        for ($next = 974; $next <= 989; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next974-989', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next974Handoff']));
        $t->same('next970-973', $result['next974AfterReadyRange']);
        $t->same(true, $result['next974ConsumesNext973Ready']);
        $t->same(64, strlen($result['next975SourceAudit']));
        $t->same(true, $result['next975PreservesCurrentSource']);
        $t->same(64, strlen($result['next976Preflight']));
        $t->same(true, $result['next976KeepsThroughputHigh']);
        $t->same(64, strlen($result['next977Final']));
        $t->same(true, $result['next977Ready']);
        $t->same(64, strlen($result['next978Handoff']));
        $t->same('next974-977', $result['next978AfterReadyRange']);
        $t->same(true, $result['next981Ready']);
        $t->same(true, $result['next985Ready']);
        $t->same(64, strlen($result['next989Final']));
        $t->same(true, $result['next989Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next974-989 ' . $name] = $callback;
}

return $tests;
