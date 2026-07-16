<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next542-557 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next542-557.php';

        $expectedStatuses = [];
        for ($next = 542; $next <= 557; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next542-557', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next542Handoff']));
        $t->same('next538-541', $result['next542AfterReadyRange']);
        $t->same(64, strlen($result['next543SourceAudit']));
        $t->same(true, $result['next543PreservesCurrentSource']);
        $t->same(64, strlen($result['next544Preflight']));
        $t->same(true, $result['next544KeepsThroughputHigh']);
        $t->same(64, strlen($result['next545Final']));
        $t->same(true, $result['next545Ready']);
        $t->same(64, strlen($result['next546Handoff']));
        $t->same('next542-545', $result['next546AfterReadyRange']);
        $t->same(true, $result['next549Ready']);
        $t->same(true, $result['next553Ready']);
        $t->same(64, strlen($result['next557Final']));
        $t->same(true, $result['next557Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next542-557 ' . $name] = $callback;
}

return $tests;
