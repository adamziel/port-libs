<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next606-621 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next606-621.php';

        $expectedStatuses = [];
        for ($next = 606; $next <= 621; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next606-621', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next606Handoff']));
        $t->same('next602-605', $result['next606AfterReadyRange']);
        $t->same(64, strlen($result['next607SourceAudit']));
        $t->same(true, $result['next607PreservesCurrentSource']);
        $t->same(64, strlen($result['next608Preflight']));
        $t->same(true, $result['next608KeepsThroughputHigh']);
        $t->same(64, strlen($result['next609Final']));
        $t->same(true, $result['next609Ready']);
        $t->same(64, strlen($result['next610Handoff']));
        $t->same('next606-609', $result['next610AfterReadyRange']);
        $t->same(true, $result['next613Ready']);
        $t->same(true, $result['next617Ready']);
        $t->same(64, strlen($result['next621Final']));
        $t->same(true, $result['next621Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next606-621 ' . $name] = $callback;
}

return $tests;
