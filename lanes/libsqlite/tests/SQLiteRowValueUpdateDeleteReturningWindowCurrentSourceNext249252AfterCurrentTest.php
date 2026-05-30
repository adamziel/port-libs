<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'next249 chunked yield candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-chunked-yield-resume-window.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next249', $result['status']);
        $t->same(2, $result['yieldChunks']);
        $t->same([5, 6, 4, 3, 2], $result['retryIds']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next250 exclude ties candidate' => static function (TestRunner $t) use ($examplesDir): void {
        ob_start();
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next250.php';
        $output = ob_get_clean();
        $decoded = json_decode((string) $output, true, 512, JSON_THROW_ON_ERROR);

        $t->same(1, $result);
        $t->same('rowvalue-update-delete-returning-window-current-source-next250', $decoded['status']);
        $t->same(9, $decoded['excludeTiesCount']);
        $t->same([8, 9], $decoded['replayed']);
    },
    'next251 source handoff candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next251.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next251', $result['status']);
        $t->same('current-source-drained-next-source-digest-ready-next251', $result['handoffState']);
        $t->same(['wp-current-source-251', 'wp-current-source-251', 'wp-current-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251'], $result['sourceEpochs']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next252 high water fence candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/application-rowvalue-returning-window-current-source-next252.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next252', $result['status']);
        $t->same(4, $result['nextSourceFirstOrdinal']);
        $t->same(true, $result['windowFence']['retry_after_current_high_water']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window current source next249-252 after current ' . $name] = $callback;
}

return $tests;
