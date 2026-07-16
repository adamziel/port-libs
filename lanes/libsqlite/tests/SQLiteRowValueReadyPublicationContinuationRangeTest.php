<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined current-source ready-publication handoff seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-ready-publication-continuation-range.php';

        $expectedStatuses = [];
        for ($next = 1086; $next <= 1101; $next++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $next;
        }

        $t->same('rowvalue-ready-publication-continuation-range', $result['status']);
        $t->same($expectedStatuses, $result['candidateStatuses']);
        $t->same(64, strlen($result['next1086Handoff']));
        $t->same('next1082-1085', $result['next1086AfterReadyRange']);
        $t->same(true, $result['next1086ConsumesNext1085Ready']);
        $t->same(64, strlen($result['next1087SourceAudit']));
        $t->same(true, $result['next1087PreservesCurrentSource']);
        $t->same(64, strlen($result['next1088Preflight']));
        $t->same(true, $result['next1088KeepsThroughputHigh']);
        $t->same(64, strlen($result['next1089Final']));
        $t->same(true, $result['next1089Ready']);
        $t->same(64, strlen($result['next1090Handoff']));
        $t->same('next1086-1089', $result['next1090AfterReadyRange']);
        $t->same(true, $result['next1093Ready']);
        $t->same(true, $result['next1097Ready']);
        $t->same(64, strlen($result['next1101Final']));
        $t->same(true, $result['next1101Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue ready publication continuation range ' . $name] = $callback;
}

return $tests;
