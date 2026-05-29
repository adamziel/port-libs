<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current current-source handoff audit seal' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-after-current-handoff-audit.php';

        $t->same('rowvalue-update-delete-returning-window-after-current-handoff-audit', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next294',
            'rowvalue-update-delete-returning-window-current-source-next295',
            'rowvalue-update-delete-returning-window-current-source-next296',
            'rowvalue-update-delete-returning-window-current-source-next297',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next294Handoff']));
        $t->same(64, strlen($result['next295WindowAudit']));
        $t->same(true, $result['next295RetryWindowRows'] > 0);
        $t->same(64, strlen($result['next296SourceAudit']));
        $t->same(true, $result['next296CurrentEqualsNext']);
        $t->same(64, strlen($result['next297Final']));
        $t->same(true, $result['next297Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window after current handoff audit ' . $name] = $callback;
}

return $tests;
