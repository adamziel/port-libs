<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan;

$rows254 = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 20, 'option_value' => 'https://one.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 19, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 2, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 25, 'option_value' => 'https://two.test'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'rewrite', 'status' => 'queued', 'bytes' => 9, 'option_value' => 'rules'],
    ['option_id' => 8, 'blog_id' => 3, 'option_name' => 'orphaned_cache', 'autoload' => 'yes', 'status' => 'orphaned', 'bytes' => 5, 'option_value' => 'cache'],
    ['option_id' => 9, 'blog_id' => 4, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 10, 'blog_id' => 4, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bytes' => 31, 'option_value' => 'https://four-home.test'],
];
$tables254 = ['wp_options' => $rows254];
$unique254 = [['blog_id', 'option_name']];

$yieldUpdate254 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield254', option_value || ':yield254', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete254 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate254 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt254', option_value || ':attempt254', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete254 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate254 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry254', option_value || ':retry254', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete254 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan254 = static fn (?array $receipts = null, ?string $resume = null, ?array $ack = null, bool $requireNext = true): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext254(
    $tables254,
    [$yieldUpdate254, $yieldDelete254],
    [$attemptUpdate254, $attemptDelete254],
    [$retryUpdate254, $retryDelete254],
    $unique254,
    'wp_options_rowvalue_window_current_next254',
    'option_id',
    $ack,
    $resume,
    'wp-current-source-254',
    'wp-next-source-254',
    null,
    null,
    $receipts,
    $requireNext,
);

$receipts254 = static fn (): array => $plan254()['expected_row_receipts_next254'];
$tickets254 = static fn (): array => $plan254()['admitted_tickets_next254'];
$lastTicket254 = static fn (): string => $tickets254()[7];
$firstRetryTicket254 = static fn (): string => $plan254()['admitted_next_tickets_next254'][0];
$missingNextReceipt254 = static function () use ($receipts254): array {
    $receipts = $receipts254();
    unset($receipts[3]);
    return array_values($receipts);
};
$badFrameReceipt254 = static function () use ($receipts254): array {
    $receipts = $receipts254();
    $receipts[4]['frame_token'] = 'bad-frame-next254';
    return $receipts;
};
$badRunningReceipt254 = static function () use ($receipts254): array {
    $receipts = $receipts254();
    $receipts[5]['running_bytes']++;
    return $receipts;
};
$badRowidReceipt254 = static function () use ($receipts254): array {
    $receipts = $receipts254();
    $receipts[6]['option_id'] = 999;
    return $receipts;
};
$badEpochReceipt254 = static function () use ($receipts254): array {
    $receipts = $receipts254();
    $receipts[7]['source_epoch'] = 'wrong-next-source-254';
    return $receipts;
};

$cases254 = [
    'status' => [static fn (): mixed => $plan254()['status'], 'rowvalue-update-delete-returning-window-current-source-next254'],
    'inherits next251 state' => [static fn (): mixed => $plan254()['source_handoff_state_next251'], 'current-source-drained-next-source-digest-ready-next251'],
    'admission state ready' => [static fn (): mixed => $plan254()['admission_state_next254'], 'current-source-next254-window-receipts-admitted'],
    'barrier savepoint' => [static fn (): mixed => $plan254()['admission_barrier_next254']['savepoint'], 'wp_options_rowvalue_window_current_next254'],
    'barrier rowid column' => [static fn (): mixed => $plan254()['admission_barrier_next254']['rowid_column'], 'option_id'],
    'barrier current epoch' => [static fn (): mixed => $plan254()['admission_barrier_next254']['current_source_epoch'], 'wp-current-source-254'],
    'barrier next epoch' => [static fn (): mixed => $plan254()['admission_barrier_next254']['next_source_epoch'], 'wp-next-source-254'],
    'source handoff ready' => [static fn (): mixed => $plan254()['admission_barrier_next254']['source_handoff_ready'], true],
    'expected receipt count' => [static fn (): mixed => $plan254()['admission_barrier_next254']['expected_receipt_count'], 8],
    'provided receipt count' => [static fn (): mixed => $plan254()['admission_barrier_next254']['provided_receipt_count'], 8],
    'admitted row count' => [static fn (): mixed => $plan254()['admission_barrier_next254']['admitted_row_count'], 8],
    'admitted current count' => [static fn (): mixed => $plan254()['admission_barrier_next254']['admitted_current_row_count'], 3],
    'admitted next count' => [static fn (): mixed => $plan254()['admission_barrier_next254']['admitted_next_row_count'], 5],
    'no blocked reasons' => [static fn (): mixed => $plan254()['admission_barrier_next254']['blocked_reasons'], []],
    'admission token sha256' => [static fn (): mixed => strlen($plan254()['admission_barrier_next254']['admission_token']), 64],
    'expected first receipt token sha256' => [static fn (): mixed => strlen($receipts254()[0]['receipt_token']), 64],
    'expected receipt tickets' => [static fn (): mixed => array_column($receipts254(), 'ticket'), $tickets254()],
    'expected receipt epochs' => [static fn (): mixed => array_count_values(array_column($receipts254(), 'source_epoch')), ['wp-current-source-254' => 3, 'wp-next-source-254' => 5]],
    'admitted ids include yield then retry' => [static fn (): mixed => array_column($plan254()['admitted_rows_next254'], 'option_id'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'admitted tickets stable' => [static fn (): mixed => $plan254()['admitted_tickets_next254'], $plan254()['source_handoff_tickets_next251']],
    'admitted ordinals' => [static fn (): mixed => array_column($plan254()['admitted_rows_next254'], 'admission_ordinal_next254'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'row admission flags' => [static fn (): mixed => array_unique(array_column($plan254()['admission_rows_next254'], 'admitted_next254')), [true]],
    'row reasons empty' => [static fn (): mixed => array_unique(array_map('count', array_column($plan254()['admission_rows_next254'], 'admission_reasons_next254'))), [0]],
    'row token sha256' => [static fn (): mixed => strlen($plan254()['admission_rows_next254'][0]['admission_token_next254']), 64],
    'next ids' => [static fn (): mixed => array_column($plan254()['admitted_next_rows_next254'], 'option_id'), [9, 10, 7, 5, 4]],
    'current ids' => [static fn (): mixed => array_column($plan254()['admitted_current_rows_next254'], 'option_id'), [7, 5, 3]],
    'resume start count' => [static fn (): mixed => $plan254()['admission_resume_next254']['remaining_count'], 8],
    'resume first retry count' => [static fn (): mixed => $plan254(null, $firstRetryTicket254())['admission_resume_next254']['remaining_count'], 4],
    'resume last exhausted' => [static fn (): mixed => $plan254(null, $lastTicket254())['admission_resume_next254']['exhausted'], true],
    'resume last tickets empty' => [static fn (): mixed => $plan254(null, $lastTicket254())['admission_resume_tickets_next254'], []],
    'missing receipt state held' => [static fn (): mixed => $plan254($missingNextReceipt254())['admission_state_next254'], 'current-source-next254-window-receipts-held'],
    'missing receipt reason' => [static fn (): mixed => $plan254($missingNextReceipt254())['admission_barrier_next254']['blocked_reasons'], ['missing-row-receipt-next254']],
    'missing receipt admitted count' => [static fn (): mixed => $plan254($missingNextReceipt254())['admission_barrier_next254']['admitted_row_count'], 7],
    'missing receipt next ids omit first retry' => [static fn (): mixed => array_column($plan254($missingNextReceipt254())['admitted_next_rows_next254'], 'option_id'), [10, 7, 5, 4]],
    'bad frame reason includes token and frame' => [static fn (): mixed => $plan254($badFrameReceipt254())['admission_rows_next254'][4]['admission_reasons_next254'], ['row-receipt-token-mismatch-next254', 'row-receipt-window-frame-mismatch-next254']],
    'bad running reason includes token and bytes' => [static fn (): mixed => $plan254($badRunningReceipt254())['admission_rows_next254'][5]['admission_reasons_next254'], ['row-receipt-token-mismatch-next254', 'row-receipt-running-bytes-mismatch-next254']],
    'bad rowid reason includes token and rowid' => [static fn (): mixed => $plan254($badRowidReceipt254())['admission_rows_next254'][6]['admission_reasons_next254'], ['row-receipt-token-mismatch-next254', 'row-receipt-rowid-mismatch-next254']],
    'bad epoch reason includes epoch' => [static fn (): mixed => $plan254($badEpochReceipt254())['admission_rows_next254'][7]['admission_reasons_next254'], ['row-receipt-token-mismatch-next254', 'row-receipt-source-epoch-mismatch-next254']],
    'bad frame blocks barrier' => [static fn (): mixed => $plan254($badFrameReceipt254())['admission_barrier_next254']['blocked_reasons'], ['row-receipt-token-mismatch-next254', 'row-receipt-window-frame-mismatch-next254']],
    'source handoff not ready blocks' => [static fn (): mixed => $plan254(null, null, array_slice($plan254()['required_yield_tickets_next245'], 0, 1))['admission_barrier_next254']['blocked_reasons'], ['source-handoff-not-ready-next254']],
    'source handoff not ready state held' => [static fn (): mixed => $plan254(null, null, array_slice($plan254()['required_yield_tickets_next245'], 0, 1))['admission_state_next254'], 'current-source-next254-window-receipts-held'],
    'optional next receipts records reason' => [static fn (): mixed => $plan254($missingNextReceipt254(), null, null, false)['admission_barrier_next254']['blocked_reasons'], ['next-source-receipts-not-required-next254']],
    'optional next receipts admits missing row' => [static fn (): mixed => $plan254($missingNextReceipt254(), null, null, false)['admission_barrier_next254']['admitted_row_count'], 8],
    'dependency include row receipt' => [static fn (): mixed => in_array('sqlite-returning-window-row-receipt-admission-next254', $plan254()['dependencies_next254'], true), true],
    'dependency include wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-current-source-next254', $plan254()['dependencies_next254'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan254()['dependency_closure_next254'], 'no new support component needed'), true],
    'non overlap mentions next251' => [static fn (): mixed => str_contains($plan254()['non_overlap_next254'], 'next251'), true],
    'non overlap mentions next248' => [static fn (): mixed => str_contains($plan254()['non_overlap_next254'], 'next248'), true],
    'bad resume rejected' => [static fn (): mixed => $plan254(null, 'missing-ticket-next254'), InvalidArgumentException::class],
    'duplicate receipt ticket keeps last receipt' => [static function () use ($receipts254, $plan254): mixed {
        $receipts = $receipts254();
        $receipts[] = $receipts[0];
        return $plan254($receipts)['admission_barrier_next254']['provided_receipt_count'];
    }, 9],
    'empty receipt ticket rejected' => [static function () use ($receipts254): mixed {
        $receipts = $receipts254();
        $receipts[0]['ticket'] = '';
        return SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext254($GLOBALS['tables254'], [$GLOBALS['yieldUpdate254'], $GLOBALS['yieldDelete254']], [$GLOBALS['attemptUpdate254'], $GLOBALS['attemptDelete254']], [$GLOBALS['retryUpdate254'], $GLOBALS['retryDelete254']], $GLOBALS['unique254'], 'wp_options_rowvalue_window_current_next254', 'option_id', null, null, 'wp-current-source-254', 'wp-next-source-254', null, null, $receipts);
    }, InvalidArgumentException::class],
    'bad rowid column rejected' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executeNext254($tables254, [$yieldUpdate254], [$attemptUpdate254], [$retryUpdate254], $unique254, 'wp_options_rowvalue_window_current_next254', 'missing_id'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases254 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next254 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
