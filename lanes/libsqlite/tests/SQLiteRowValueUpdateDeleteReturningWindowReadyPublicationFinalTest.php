<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$tests = [];

$tests['rowvalue update delete returning window ready publication final consolidated range'] = static function (TestRunner $t) use ($examplesDir): void {
    $result = require $examplesDir . '/wordpress-rowvalue-returning-window-ready-publication-final.php';

    $expectedStatuses = [];
    $rangeStarts = [1006, 1022, 1038, 1054, 1070, 1102, 1118, 1134];
    $rangeEnds = [1021, 1037, 1053, 1069, 1085, 1117, 1133, 1149];
    foreach ($rangeStarts as $rangeIndex => $firstStep) {
        for ($step = $firstStep; $step <= $rangeEnds[$rangeIndex]; $step++) {
            $expectedStatuses[] = 'rowvalue-update-delete-returning-window-current-source-next' . $step;
        }
    }

    $t->same('rowvalue-update-delete-returning-window-ready-publication-final', $result['status']);
    $t->same(128, $result['candidateCount']);
    $t->same($expectedStatuses, $result['candidateStatuses']);
    $t->same('rowvalue-update-delete-returning-window-current-source-next1006', $result['firstStatus']);
    $t->same('rowvalue-update-delete-returning-window-current-source-next1149', $result['lastStatus']);
    $t->same($rangeStarts, $result['rangeStarts']);
    $t->same('next1002-1005', $result['handoffs'][1006]['afterReadyRange']);
    $t->same('next1018-1021', $result['handoffs'][1022]['afterReadyRange']);
    $t->same('next1034-1037', $result['handoffs'][1038]['afterReadyRange']);
    $t->same('next1050-1053', $result['handoffs'][1054]['afterReadyRange']);
    $t->same('next1066-1069', $result['handoffs'][1070]['afterReadyRange']);
    $t->same('next1098-1101', $result['handoffs'][1102]['afterReadyRange']);
    $t->same('next1114-1117', $result['handoffs'][1118]['afterReadyRange']);
    $t->same('next1130-1133', $result['handoffs'][1134]['afterReadyRange']);
    foreach ($result['handoffs'] as $handoff) {
        $t->same(64, strlen($handoff['hash']));
        $t->same(true, $handoff['previousReady']);
    }
    foreach ($result['ready'] as $ready) {
        $t->same(true, $ready);
    }
    $t->same(64, strlen($result['finalSeal']));
    $t->same(true, $result['finalReady']);
    $t->contains('without keeping numbered caller files', $result['wordpressUse']);
    $t->contains('no new support component needed', $result['dependencyClosure']);
};

return $tests;
