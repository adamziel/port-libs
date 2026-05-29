<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next622-637 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next622-637.php';

        $expectedStatuses = [];
        for ($next = 622; $next <= 637; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next622-637', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next622Handoff']));
        $t->same('next618-621', $result['next622AfterReadyRange']);
        $t->same(64, strlen($result['next623SourceAudit']));
        $t->same(true, $result['next623PreservesCurrentSource']);
        $t->same(64, strlen($result['next624Preflight']));
        $t->same(true, $result['next624KeepsThroughputHigh']);
        $t->same(64, strlen($result['next625Final']));
        $t->same(true, $result['next625Ready']);
        $t->same(64, strlen($result['next626Handoff']));
        $t->same('next622-625', $result['next626AfterReadyRange']);
        $t->same(true, $result['next629Ready']);
        $t->same(true, $result['next633Ready']);
        $t->same(64, strlen($result['next637Final']));
        $t->same(true, $result['next637Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next622-637 ' . $name] = $callback;
}

return $tests;
