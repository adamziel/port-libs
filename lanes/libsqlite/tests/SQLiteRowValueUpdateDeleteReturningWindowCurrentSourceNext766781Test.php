<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next766-781 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next766-781.php';

        $expectedStatuses = [];
        for ($next = 766; $next <= 781; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next766-781', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next766Handoff']));
        $t->same('next762-765', $result['next766AfterReadyRange']);
        $t->same(true, $result['next766ConsumesNext765Ready']);
        $t->same(64, strlen($result['next767SourceAudit']));
        $t->same(true, $result['next767PreservesCurrentSource']);
        $t->same(64, strlen($result['next768Preflight']));
        $t->same(true, $result['next768KeepsThroughputHigh']);
        $t->same(64, strlen($result['next769Final']));
        $t->same(true, $result['next769Ready']);
        $t->same(64, strlen($result['next770Handoff']));
        $t->same('next766-769', $result['next770AfterReadyRange']);
        $t->same(true, $result['next773Ready']);
        $t->same(true, $result['next777Ready']);
        $t->same(64, strlen($result['next781Final']));
        $t->same(true, $result['next781Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next766-781 ' . $name] = $callback;
}

return $tests;
