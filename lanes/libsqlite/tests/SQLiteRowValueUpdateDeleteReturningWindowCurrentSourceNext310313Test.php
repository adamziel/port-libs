<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next310-313 current-source preflight seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next310-313.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next310-313', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next310',
            'rowvalue-update-delete-returning-window-current-source-next311',
            'rowvalue-update-delete-returning-window-current-source-next312',
            'rowvalue-update-delete-returning-window-current-source-next313',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next310Handoff']));
        $t->same('next306-309', $result['next310AfterReadyRange']);
        $t->same(64, strlen($result['next311SourceAudit']));
        $t->same(true, $result['next311PreservesCurrentSource']);
        $t->same(64, strlen($result['next312Preflight']));
        $t->same(true, $result['next312KeepsThroughputHigh']);
        $t->same(64, strlen($result['next313Final']));
        $t->same(true, $result['next313Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next310-313 ' . $name] = $callback;
}

return $tests;
