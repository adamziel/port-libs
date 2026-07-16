<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined current-source follow-on seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-continuation.php';

        $expectedStatuses = [];
        for ($step = 446; $step <= 461; $step++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $step;
        }

        $t->same('rowvalue-update-delete-returning-window-current-source-continuation', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['handoffDigest']));
        $t->same('next442-445', $result['handoffAfterReadyRange']);
        $t->same(64, strlen($result['sourceAuditDigest']));
        $t->same(true, $result['preservesCurrentSource']);
        $t->same(64, strlen($result['preflightDigest']));
        $t->same(true, $result['keepsThroughputHigh']);
        $t->same(64, strlen($result['firstReadyFinalDigest']));
        $t->same(true, $result['firstReady']);
        $t->same(64, strlen($result['secondHandoffDigest']));
        $t->same('next446-449', $result['secondHandoffAfterReadyRange']);
        $t->same(true, $result['midReady']);
        $t->same(true, $result['lateReady']);
        $t->same(64, strlen($result['finalDigest']));
        $t->same(true, $result['finalReady']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source continuation ' . $name] = $callback;
}

return $tests;
