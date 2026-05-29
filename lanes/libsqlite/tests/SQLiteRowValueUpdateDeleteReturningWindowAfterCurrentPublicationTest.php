<?php

declare(strict_types=1);

$examplesDir = __DIR__ . '/../examples';

$cases = [
    'next257 tombstone publication candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-delete-retry-publication.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next257', $result['status']);
        $t->same(true, $result['tombstoneGate']['next_source_retry_tombstones_exposed']);
        $t->same([1, 6, 2, 6, 5, 6, 4, 3, 2], $result['publicationRowids']);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next258 transition token admission candidate' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-publication-transition-admission.php';

        $t->same('rowvalue-update-delete-returning-window-current-source-next258', $result['status']);
        $t->same(['rewrite_rules', 'pending_theme', '_transient_feed'], $result['heldRows']);
        $t->same(['rewrite_rules', 'pending_theme', '_transient_feed', 'plugin_batch', 'home', 'rewrite_rules', 'pending_theme', '_transient_timeout_feed'], $result['admittedRows']);
        $t->true(strlen((string) $result['transitionToken']) === 64);
        $t->true(str_contains($result['dependencyClosure'], 'no new support component needed'));
    },
    'next259 current row frame acknowledgement candidate' => static function (TestRunner $t) use ($examplesDir): void {
        ob_start();
        $argv = [];
        require $examplesDir . '/wordpress-rowvalue-update-delete-returning-window-current-row-frame-admission.php';
        $output = trim((string) ob_get_clean());
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);

        $t->same('rowvalue-update-delete-returning-window-current-source-next259', $result['status']);
        $t->same(8, $result['ready_count']);
        $t->same(0, $result['blocked_count']);
        $t->same(1, $result['transition_count']);
        $t->same([7, 5, 3, 9, 10, 7, 5, 4], $result['ready_rowids']);
        $t->true(str_contains($result['dependency_closure'], 'no new support component needed'));
    },
    'next260 mixed boundary release candidate' => static function (TestRunner $t) use ($examplesDir): void {
        ob_start();
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-frame-boundary-admission.php';
        $output = trim((string) ob_get_clean());

        $t->same(1, $result);
        $t->same('wordpress-rowvalue-returning-window-frame-boundary-admission self-test passed', $output);
    },
    'combined after-current handoff' => static function (TestRunner $t) use ($examplesDir): void {
        $result = require $examplesDir . '/wordpress-rowvalue-returning-window-after-current-publication.php';

        $t->same('rowvalue-update-delete-returning-window-after-current-publication', $result['status']);
        $t->same([
            'rowvalue-update-delete-returning-window-current-source-next257',
            'rowvalue-update-delete-returning-window-current-source-next258',
            'rowvalue-update-delete-returning-window-current-source-next259',
            'wordpress-rowvalue-returning-window-frame-boundary-admission self-test passed',
        ], $result['candidateStatuses']);
        $t->same([1, 6, 2, 6, 5, 6, 4, 3, 2], $result['next257PublicationRowids']);
        $t->same(8, $result['next259ReadyCount']);
        $t->same('wordpress-rowvalue-returning-window-frame-boundary-admission self-test passed', $result['next260SelfTestOutput']);
    },
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['rowvalue update delete returning window after current publication ' . $name] = $callback;
}

return $tests;
