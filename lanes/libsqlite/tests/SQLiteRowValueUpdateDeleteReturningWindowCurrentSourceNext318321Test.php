<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next318-321 current-source preflight seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next318-321.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next318-321', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next318',
            'rowvalue-update-delete-returning-window-current-source-next319',
            'rowvalue-update-delete-returning-window-current-source-next320',
            'rowvalue-update-delete-returning-window-current-source-next321',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next318Handoff']));
        $t->same('next314-317', $result['next318AfterReadyRange']);
        $t->same(64, strlen($result['next319SourceAudit']));
        $t->same(true, $result['next319PreservesCurrentSource']);
        $t->same(64, strlen($result['next320Preflight']));
        $t->same(true, $result['next320KeepsThroughputHigh']);
        $t->same(64, strlen($result['next321Final']));
        $t->same(true, $result['next321Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next318-321 ' . $name] = $callback;
}

return $tests;
