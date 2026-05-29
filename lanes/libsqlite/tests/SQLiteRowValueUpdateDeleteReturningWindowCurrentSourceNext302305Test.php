<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined next302-305 current-source continuation seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next302-305.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next302-305', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next302',
            'rowvalue-update-delete-returning-window-current-source-next303',
            'rowvalue-update-delete-returning-window-current-source-next304',
            'rowvalue-update-delete-returning-window-current-source-next305',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next302SourceWindow']));
        $t->same('next298-301', $result['next302AwaitedReadyRange']);
        $t->same(64, strlen($result['next303ThroughputAudit']));
        $t->same(true, $result['next303KeepsIndependentSlices']);
        $t->same(64, strlen($result['next304Isolation']));
        $t->same(64, strlen($result['next305Final']));
        $t->same(true, $result['next305Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next302-305 ' . $name] = $callback;
}

return $tests;
