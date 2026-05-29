<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'combined after-current handoff' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-current-source-next273-276-after-current.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next273-276-after-current', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next273',
            'rowvalue-update-delete-returning-window-current-source-next274',
            'rowvalue-update-delete-returning-window-current-source-next275',
            'rowvalue-update-delete-returning-window-current-source-next276',
        ], $result['candidateStatuses']);
        $t->same(64, strlen($result['next273Admission']));
        $t->same(64, strlen($result['next274Balance']));
        $t->same(64, strlen($result['next275Package']));
        $t->same(64, strlen($result['next276Handoff']));
        $t->same(true, $result['next276Ready']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next273-276 after current ' . $name] = $callback;
}

return $tests;
