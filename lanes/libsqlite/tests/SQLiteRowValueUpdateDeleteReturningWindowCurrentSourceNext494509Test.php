<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next494-509 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next494-509.php';

        $expectedStatuses = [];
        for ($next = 494; $next <= 509; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next494-509', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next494Handoff']));
        $t->same('next490-493', $result['next494AfterReadyRange']);
        $t->same(64, strlen($result['next495SourceAudit']));
        $t->same(true, $result['next495PreservesCurrentSource']);
        $t->same(64, strlen($result['next496Preflight']));
        $t->same(true, $result['next496KeepsThroughputHigh']);
        $t->same(64, strlen($result['next497Final']));
        $t->same(true, $result['next497Ready']);
        $t->same(64, strlen($result['next498Handoff']));
        $t->same('next494-497', $result['next498AfterReadyRange']);
        $t->same(true, $result['next501Ready']);
        $t->same(true, $result['next505Ready']);
        $t->same(64, strlen($result['next509Final']));
        $t->same(true, $result['next509Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next494-509 ' . $name] = $callback;
}

return $tests;
