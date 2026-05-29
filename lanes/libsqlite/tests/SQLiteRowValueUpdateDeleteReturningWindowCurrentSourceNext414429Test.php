<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next414-429 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next414-429.php';

        $expectedStatuses = [];
        for ($next = 414; $next <= 429; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next414-429', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next414Handoff']));
        $t->same('next410-413', $result['next414AfterReadyRange']);
        $t->same(64, strlen($result['next415SourceAudit']));
        $t->same(true, $result['next415PreservesCurrentSource']);
        $t->same(64, strlen($result['next416Preflight']));
        $t->same(true, $result['next416KeepsThroughputHigh']);
        $t->same(64, strlen($result['next417Final']));
        $t->same(true, $result['next417Ready']);
        $t->same(64, strlen($result['next418Handoff']));
        $t->same('next414-417', $result['next418AfterReadyRange']);
        $t->same(true, $result['next421Ready']);
        $t->same(true, $result['next425Ready']);
        $t->same(64, strlen($result['next429Final']));
        $t->same(true, $result['next429Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next414-429 ' . $name] = $callback;
}

return $tests;
