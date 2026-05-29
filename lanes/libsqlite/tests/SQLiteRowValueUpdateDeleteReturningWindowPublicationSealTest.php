<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next510-525 current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-publication-seal.php';

        $expectedStatuses = [];
        for ($next = 510; $next <= 525; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-next510-525', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next510Handoff']));
        $t->same('next506-509', $result['next510AfterReadyRange']);
        $t->same(64, strlen($result['next511SourceAudit']));
        $t->same(true, $result['next511PreservesCurrentSource']);
        $t->same(64, strlen($result['next512Preflight']));
        $t->same(true, $result['next512KeepsThroughputHigh']);
        $t->same(64, strlen($result['next513Final']));
        $t->same(true, $result['next513Ready']);
        $t->same(64, strlen($result['next514Handoff']));
        $t->same('next510-513', $result['next514AfterReadyRange']);
        $t->same(true, $result['next517Ready']);
        $t->same(true, $result['next521Ready']);
        $t->same(64, strlen($result['next525Final']));
        $t->same(true, $result['next525Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next510-525 ' . $name] = $callback;
}

return $tests;
