<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'next245 yield gate candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next245.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next245', $result['status']);
        $t->same(true, $result['nextSourceExposed']);
        $t->same([5, 6, 4, 3, 2], $result['retryIds']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next246 filter release candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-filter-window-current-source-next246.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next246', $result['status']);
        $t->same([7, 8, 6, 5], $result['retryUpdateIds']);
        $t->same(true, $result['suppressedOnlyVisible']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next247 exclude group candidate' => static function (TestRunner $t) use ($examplesDir): void {
        ob_start();
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next247.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $result);
        $t->same('rowvalue-update-delete-returning-window-current-source-next247', $decoded['status']);
        $t->same(9, $decoded['excludeGroupCount']);
        $t->same([8, 9], $decoded['replayed']);
    },
    'next248 publication cursor candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next248.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next248', $result['status']);
        $t->same('current-source-yield-complete-next-source-resumable-next248', $result['publicationState']);
        $t->same([5, 6, 4, 3, 2], $result['retryIds']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next245-248 after current ' . $name] = $callback;
}

return $tests;
