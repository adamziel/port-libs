<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Plan;

$rows251 = [
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
$tables251 = ['wp_options' => $rows251];
$unique251 = [['blog_id', 'option_name']];

$yieldUpdate251 = "UPDATE wp_options SET (status, option_value, bytes) = ('yield251', option_value || ':yield251', bytes + 30) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$yieldDelete251 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_feed')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptUpdate251 = "UPDATE wp_options SET (status, option_value, bytes) = ('attempt251', option_value || ':attempt251', bytes + 5) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$attemptDelete251 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((3, 'orphaned_cache')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryUpdate251 = "UPDATE wp_options SET (status, option_value, bytes) = ('retry251', option_value || ':retry251', bytes + 20) WHERE (blog_id, option_name) IN ((2, 'pending_theme'), (3, 'rewrite_rules'), (4, 'plugin_batch')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";
$retryDelete251 = "DELETE FROM wp_options WHERE (blog_id, option_name) IN ((1, '_transient_timeout_feed'), (4, 'home')) RETURNING option_id, blog_id, option_name, status, bytes ORDER BY option_id";

$plan251 = static fn (
    ?array $ack = null,
    ?string $resume = null,
    string $currentEpoch = 'wp-current-source-251',
    string $nextEpoch = 'wp-next-source-251',
    ?string $expectedCurrent = null,
    ?string $expectedNext = null,
): array => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Plan::execute(
    $tables251,
    [$yieldUpdate251, $yieldDelete251],
    [$attemptUpdate251, $attemptDelete251],
    [$retryUpdate251, $retryDelete251],
    $unique251,
    'wp_options_rowvalue_window_current_next251',
    'option_id',
    $ack,
    $resume,
    $currentEpoch,
    $nextEpoch,
    $expectedCurrent,
    $expectedNext,
);

$required251 = static fn (): array => $plan251()['required_yield_tickets_next245'];
$firstYield251 = static fn (): string => $required251()[0];
$lastYield251 = static fn (): string => $required251()[2];
$firstRetry251 = static fn (): string => $plan251()['source_handoff_retry_tickets_next251'][0];
$lastRetry251 = static fn (): string => $plan251()['source_handoff_retry_tickets_next251'][4];
$missingAck251 = static fn (): array => array_slice($required251(), 0, 2);
$currentDigest251 = static fn (): string => $plan251()['source_handoff_barrier_next251']['current_source_digest'];
$nextDigest251 = static fn (): string => $plan251()['source_handoff_barrier_next251']['next_source_digest'];

$cases251 = [
    'status' => [static fn (): mixed => $plan251()['status'], 'rowvalue-update-delete-returning-window-current-source-next251'],
    'inherits next248 status' => [static fn (): mixed => $plan251()['publication_state_next248'], 'current-source-yield-complete-next-source-resumable-next248'],
    'handoff state ready' => [static fn (): mixed => $plan251()['source_handoff_state_next251'], 'current-source-drained-next-source-digest-ready-next251'],
    'barrier savepoint' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['savepoint'], 'wp_options_rowvalue_window_current_next251'],
    'barrier current epoch' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['current_source_epoch'], 'wp-current-source-251'],
    'barrier next epoch' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['next_source_epoch'], 'wp-next-source-251'],
    'barrier current complete' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['current_source_complete'], true],
    'barrier publication exposed' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['next_source_exposed_by_publication'], true],
    'barrier next ready' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['next_source_ready'], true],
    'barrier no blocked reasons' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['blocked_reasons'], []],
    'current digest is sha256' => [static fn (): mixed => strlen($currentDigest251()), 64],
    'next digest is sha256' => [static fn (): mixed => strlen($nextDigest251()), 64],
    'handoff token is sha256' => [static fn (): mixed => strlen($plan251()['source_handoff_barrier_next251']['handoff_token']), 64],
    'handoff token changes by epoch' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['handoff_token'] === $plan251(null, null, 'wp-current-source-251b')['source_handoff_barrier_next251']['handoff_token'], false],
    'handoff row count' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['handoff_row_count'], 8],
    'retry visible count' => [static fn (): mixed => $plan251()['source_handoff_barrier_next251']['retry_visible_count'], 5],
    'handoff ids include yield then retry' => [static fn (): mixed => array_column($plan251()['source_handoff_rows_next251'], 'option_id'), [7, 5, 3, 9, 10, 7, 5, 4]],
    'handoff tickets include yield then retry' => [static fn (): mixed => $plan251()['source_handoff_tickets_next251'], [...$required251(), ...$plan251()['source_handoff_retry_tickets_next251']]],
    'handoff ordinals monotonic' => [static fn (): mixed => array_column($plan251()['source_handoff_rows_next251'], 'handoff_ordinal_next251'), [1, 2, 3, 4, 5, 6, 7, 8]],
    'handoff source epochs' => [static fn (): mixed => array_column($plan251()['source_handoff_rows_next251'], 'source_epoch_next251'), ['wp-current-source-251', 'wp-current-source-251', 'wp-current-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251', 'wp-next-source-251']],
    'handoff visible flags' => [static fn (): mixed => array_unique(array_column($plan251()['source_handoff_rows_next251'], 'handoff_visible_next251')), [true]],
    'handoff token length first row' => [static fn (): mixed => strlen($plan251()['source_handoff_rows_next251'][0]['source_handoff_token_next251']), 64],
    'retry ids' => [static fn (): mixed => array_column($plan251()['source_handoff_retry_rows_next251'], 'option_id'), [9, 10, 7, 5, 4]],
    'retry epochs all next' => [static fn (): mixed => array_unique(array_column($plan251()['source_handoff_retry_rows_next251'], 'source_epoch_next251')), ['wp-next-source-251']],
    'resume from start count' => [static fn (): mixed => $plan251()['source_handoff_resume_next251']['remaining_count'], 8],
    'resume from start offset' => [static fn (): mixed => $plan251()['source_handoff_resume_next251']['resume_offset'], 0],
    'resume from first yield count' => [static fn (): mixed => $plan251(null, $firstYield251())['source_handoff_resume_next251']['remaining_count'], 7],
    'resume from first yield starts on second yield' => [static fn (): mixed => $plan251(null, $firstYield251())['source_handoff_resume_tickets_next251'][0], $required251()[1]],
    'resume from last yield starts first retry' => [static fn (): mixed => $plan251(null, $lastYield251())['source_handoff_resume_tickets_next251'][0], $firstRetry251()],
    'resume from first retry starts second retry' => [static fn (): mixed => $plan251(null, $firstRetry251())['source_handoff_resume_next251']['rows'][0]['option_id'], 10],
    'resume from last retry exhausted' => [static fn (): mixed => $plan251(null, $lastRetry251())['source_handoff_resume_next251']['exhausted'], true],
    'resume from last retry count zero' => [static fn (): mixed => $plan251(null, $lastRetry251())['source_handoff_resume_next251']['remaining_count'], 0],
    'held state blocks retry handoff' => [static fn (): mixed => $plan251($missingAck251())['source_handoff_state_next251'], 'current-source-or-digest-fence-holds-next-source-next251'],
    'held next ready false' => [static fn (): mixed => $plan251($missingAck251())['source_handoff_barrier_next251']['next_source_ready'], false],
    'held handoff row count only current' => [static fn (): mixed => $plan251($missingAck251())['source_handoff_barrier_next251']['handoff_row_count'], 3],
    'held retry visible count zero' => [static fn (): mixed => $plan251($missingAck251())['source_handoff_barrier_next251']['retry_visible_count'], 0],
    'held ids omit retry' => [static fn (): mixed => array_column($plan251($missingAck251())['source_handoff_rows_next251'], 'option_id'), [7, 5, 3]],
    'held blocked reason inherited' => [static fn (): mixed => $plan251($missingAck251())['source_handoff_barrier_next251']['blocked_reasons'], ['missing-current-source-yield-ticket-next248']],
    'held resume from last current exhausted' => [static fn (): mixed => $plan251($missingAck251(), $lastYield251())['source_handoff_resume_next251']['exhausted'], true],
    'expected digest passes' => [static fn (): mixed => $plan251(null, null, 'wp-current-source-251', 'wp-next-source-251', $currentDigest251(), $nextDigest251())['source_handoff_barrier_next251']['next_source_ready'], true],
    'current digest mismatch blocks' => [static fn (): mixed => $plan251(null, null, 'wp-current-source-251', 'wp-next-source-251', 'bad-current', $nextDigest251())['source_handoff_barrier_next251']['blocked_reasons'], ['current-source-digest-mismatch-next251']],
    'next digest mismatch blocks' => [static fn (): mixed => $plan251(null, null, 'wp-current-source-251', 'wp-next-source-251', $currentDigest251(), 'bad-next')['source_handoff_barrier_next251']['blocked_reasons'], ['next-source-digest-mismatch-next251']],
    'both digest mismatches block' => [static fn (): mixed => $plan251(null, null, 'wp-current-source-251', 'wp-next-source-251', 'bad-current', 'bad-next')['source_handoff_barrier_next251']['blocked_reasons'], ['current-source-digest-mismatch-next251', 'next-source-digest-mismatch-next251']],
    'digest mismatch omits retry rows' => [static fn (): mixed => array_column($plan251(null, null, 'wp-current-source-251', 'wp-next-source-251', $currentDigest251(), 'bad-next')['source_handoff_rows_next251'], 'option_id'), [7, 5, 3]],
    'digest mismatch retry count zero' => [static fn (): mixed => $plan251(null, null, 'wp-current-source-251', 'wp-next-source-251', $currentDigest251(), 'bad-next')['source_handoff_barrier_next251']['retry_visible_count'], 0],
    'digest mismatch state held' => [static fn (): mixed => $plan251(null, null, 'wp-current-source-251', 'wp-next-source-251', $currentDigest251(), 'bad-next')['source_handoff_state_next251'], 'current-source-or-digest-fence-holds-next-source-next251'],
    'dependencies include handoff' => [static fn (): mixed => in_array('sqlite-rowvalue-update-delete-returning-window-source-handoff-next251', $plan251()['dependencies_next251'], true), true],
    'dependencies include wordpress' => [static fn (): mixed => in_array('wordpress-rowvalue-returning-window-current-next-source-handoff-next251', $plan251()['dependencies_next251'], true), true],
    'dependency closure no new support' => [static fn (): mixed => str_contains($plan251()['dependency_closure_next251'], 'no new support component needed'), true],
    'non overlap mentions next248' => [static fn (): mixed => str_contains($plan251()['non_overlap_next251'], 'next248'), true],
    'non overlap mentions next245' => [static fn (): mixed => str_contains($plan251()['non_overlap_next251'], 'next245'), true],
    'bad resume ticket rejected' => [static fn (): mixed => $plan251(null, 'missing-ticket-next251'), InvalidArgumentException::class],
    'empty current epoch rejected' => [static fn (): mixed => $plan251(null, null, ''), InvalidArgumentException::class],
    'empty next epoch rejected' => [static fn (): mixed => $plan251(null, null, 'wp-current-source-251', ''), InvalidArgumentException::class],
    'same epochs rejected' => [static fn (): mixed => $plan251(null, null, 'same-source-251', 'same-source-251'), InvalidArgumentException::class],
    'bad savepoint rejected by base' => [static fn (): mixed => SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext251Plan::execute($tables251, [$yieldUpdate251], [$attemptUpdate251], [$retryUpdate251], $unique251, 'bad-name'), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases251 as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning window current source next251 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
