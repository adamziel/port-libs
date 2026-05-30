<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql;

$rows = [
    ['option_id' => 1, 'blog_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 20, 'option_value' => 'https://old.test'],
    ['option_id' => 2, 'blog_id' => 1, 'option_name' => 'home', 'autoload' => 'yes', 'status' => 'live', 'bucket' => 'core', 'bytes' => 21, 'option_value' => 'https://home.test'],
    ['option_id' => 3, 'blog_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 12, 'option_value' => 'feed'],
    ['option_id' => 4, 'blog_id' => 1, 'option_name' => '_transient_timeout_feed', 'autoload' => 'no', 'status' => 'stale', 'bucket' => 'cache', 'bytes' => 13, 'option_value' => 'timeout'],
    ['option_id' => 5, 'blog_id' => 2, 'option_name' => 'pending_theme', 'autoload' => 'no', 'status' => null, 'bucket' => 'theme', 'bytes' => 7, 'option_value' => 'theme'],
    ['option_id' => 6, 'blog_id' => 3, 'option_name' => 'plugin_batch', 'autoload' => 'no', 'status' => 'queued', 'bucket' => 'plugin', 'bytes' => 11, 'option_value' => 'plugin'],
    ['option_id' => 7, 'blog_id' => 3, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'status' => 'queued', 'bucket' => 'rewrite', 'bytes' => 9, 'option_value' => 'rules'],
];

$tables = ['wp_options' => $rows];
$unique = [['blog_id', 'option_name']];
$savepointImage = $tables;

$updateSql = "UPDATE wp_options SET (status, option_value, bytes) = ('review', option_value || ':review', bytes + 5) WHERE (blog_id, option_name) IN ((1, '_transient_feed'), (2, 'pending_theme')) OR (status, bucket) BETWEEN ('queued', 'plugin') AND ('queued', 'rewrite') AND autoload = 'no' RETURNING option_id, option_name, status, option_value, bytes, (status, bucket) IS ('review', bucket) AS reviewed ORDER BY option_id";
$deleteSql = "DELETE FROM wp_options WHERE (status, option_name) IN (('review', '_transient_feed'), ('review', 'plugin_batch')) OR option_name = 'home' RETURNING option_id, option_name, status, option_value, (blog_id, option_name) IN ((1, 'home'), (1, '_transient_feed'), (3, 'plugin_batch')) AS cleanup_match ORDER BY option_id";
$literalOrSql = "UPDATE wp_options SET status = 'literal OR kept' WHERE option_name = 'no OR split' OR option_id = 7 RETURNING option_id, status ORDER BY option_id";
$unknownOrSql = "DELETE FROM wp_options WHERE status = NULL OR (blog_id, option_name) = (1, '_transient_timeout_feed') RETURNING option_id, option_name ORDER BY option_id";

$parsedUpdate = static fn (): array => SQLiteUpdateDeleteReturningSql::parse($updateSql);
$update = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables, 'option_id', $unique);
$deleteAfterUpdate = static function () use ($updateSql, $deleteSql, $tables, $unique): array {
    $updated = SQLiteUpdateDeleteReturningSql::execute($updateSql, $tables, 'option_id', $unique);

    return SQLiteUpdateDeleteReturningSql::execute($deleteSql, $updated['tables'], 'option_id', $unique);
};
$literalOr = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($literalOrSql, $tables, 'option_id', $unique);
$unknownOr = static fn (): array => SQLiteUpdateDeleteReturningSql::execute($unknownOrSql, $tables, 'option_id', $unique);
$releasedSavepoint = static function () use ($updateSql, $deleteSql, $savepointImage, $unique): array {
    $update = SQLiteUpdateDeleteReturningSql::execute($updateSql, $savepointImage, 'option_id', $unique);
    $delete = SQLiteUpdateDeleteReturningSql::execute($deleteSql, $update['tables'], 'option_id', $unique);

    return [
        'savepoint' => 'app_settings_rowvalue_or_predicate_next171',
        'status' => 'released-after-rowvalue-or-predicate-cleanup',
        'savepoint_image_tables' => $savepointImage,
        'current_source_tables' => $delete['tables'],
        'next_source_tables' => $delete['tables'],
        'yielded_returning' => [
            ['ordinal' => 0, 'action' => $update['action'], 'rows' => $update['returning']],
            ['ordinal' => 1, 'action' => $delete['action'], 'rows' => $delete['returning']],
        ],
        'yielded_returning_count' => count($update['returning']) + count($delete['returning']),
        'changed_tables' => $savepointImage === $delete['tables'] ? [] : ['wp_options'],
        'dependencies' => [
            'sqlite-update-delete-returning-rowvalue-where-or-current-source-next171',
            'sqlite-where-or-honors-and-precedence-for-rowvalue-predicates',
            'sqlite-savepoint-release-keeps-or-cleanup-current-source',
        ],
    ];
};

$cases = [
    'parser keeps top level OR in where clause' => [static fn (): mixed => str_contains($parsedUpdate()['where'], ') OR ('), true],
    'parser returning expression parsed after OR where' => [static fn (): mixed => str_contains($parsedUpdate()['returning'], 'reviewed'), true],
    'parser order by survives OR where' => [static fn (): mixed => $parsedUpdate()['order_by'][0]['column'], 'option_id'],
    'update OR predicate selected ids' => [static fn (): mixed => $update()['plan']->selectedIds, [3, 5, 6]],
    'update OR predicate mutation ids' => [static fn (): mixed => $update()['plan']->mutationIds, [3, 5, 6]],
    'update OR returning ids' => [static fn (): mixed => array_column($update()['returning'], 'option_id'), [3, 5, 6]],
    'update OR returning option names' => [static fn (): mixed => array_column($update()['returning'], 'option_name'), ['_transient_feed', 'pending_theme', 'plugin_batch']],
    'update OR returning status set' => [static fn (): mixed => array_column($update()['returning'], 'status'), ['review', 'review', 'review']],
    'update OR returning reviewed tuple true' => [static fn (): mixed => array_column($update()['returning'], 'reviewed'), [1, 1, 1]],
    'update OR increments bytes' => [static fn (): mixed => array_column($update()['returning'], 'bytes'), [17, 12, 16]],
    'update OR row three current value' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'option_value', 'option_id')[3], 'feed:review'],
    'update OR row five null status now review' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'status', 'option_id')[5], 'review'],
    'update OR row six current value' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'option_value', 'option_id')[6], 'plugin:review'],
    'update OR honors AND precedence by excluding autoload yes rewrite' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'update OR leaves timeout row stale' => [static fn (): mixed => array_column($update()['tables']['wp_options'], 'status', 'option_id')[4], 'stale'],
    'update OR no unique conflicts' => [static fn (): mixed => $update()['conflicts'], []],
    'delete after OR update selected ids' => [static fn (): mixed => $deleteAfterUpdate()['plan']->selectedIds, [2, 3, 6]],
    'delete after OR update mutation ids' => [static fn (): mixed => $deleteAfterUpdate()['plan']->mutationIds, [2, 3, 6]],
    'delete after OR update returning ids' => [static fn (): mixed => array_column($deleteAfterUpdate()['returning'], 'option_id'), [2, 3, 6]],
    'delete after OR update returning old statuses' => [static fn (): mixed => array_column($deleteAfterUpdate()['returning'], 'status'), ['live', 'review', 'review']],
    'delete after OR update returning old values' => [static fn (): mixed => array_column($deleteAfterUpdate()['returning'], 'option_value'), ['https://home.test', 'feed:review', 'plugin:review']],
    'delete after OR update row-value returning expression' => [static fn (): mixed => array_column($deleteAfterUpdate()['returning'], 'cleanup_match'), [1, 1, 1]],
    'delete after OR update final ids' => [static fn (): mixed => array_column($deleteAfterUpdate()['tables']['wp_options'], 'option_id'), [1, 4, 5, 7]],
    'delete after OR update keeps pending theme reviewed' => [static fn (): mixed => array_column($deleteAfterUpdate()['tables']['wp_options'], 'status', 'option_id')[5], 'review'],
    'delete after OR update keeps rewrite queued' => [static fn (): mixed => array_column($deleteAfterUpdate()['tables']['wp_options'], 'status', 'option_id')[7], 'queued'],
    'literal OR string does not split where' => [static fn (): mixed => $literalOr()['plan']->selectedIds, [7]],
    'literal OR string returning status' => [static fn (): mixed => $literalOr()['returning'][0]['status'], 'literal OR kept'],
    'unknown OR permits true right side' => [static fn (): mixed => $unknownOr()['plan']->selectedIds, [4]],
    'unknown OR deletes only true right side' => [static fn (): mixed => array_column($unknownOr()['tables']['wp_options'], 'option_id'), [1, 2, 3, 5, 6, 7]],
    'savepoint released status' => [static fn (): mixed => $releasedSavepoint()['status'], 'released-after-rowvalue-or-predicate-cleanup'],
    'savepoint image preserved row three stale' => [static fn (): mixed => array_column($releasedSavepoint()['savepoint_image_tables']['wp_options'], 'status', 'option_id')[3], 'stale'],
    'savepoint current source final ids' => [static fn (): mixed => array_column($releasedSavepoint()['current_source_tables']['wp_options'], 'option_id'), [1, 4, 5, 7]],
    'savepoint next source equals current' => [static fn (): mixed => $releasedSavepoint()['next_source_tables'], $releasedSavepoint()['current_source_tables']],
    'savepoint yielded update then delete' => [static fn (): mixed => array_column($releasedSavepoint()['yielded_returning'], 'action'), ['update', 'delete']],
    'savepoint yielded count six' => [static fn (): mixed => $releasedSavepoint()['yielded_returning_count'], 6],
    'savepoint first stream ids' => [static fn (): mixed => array_column($releasedSavepoint()['yielded_returning'][0]['rows'], 'option_id'), [3, 5, 6]],
    'savepoint second stream ids' => [static fn (): mixed => array_column($releasedSavepoint()['yielded_returning'][1]['rows'], 'option_id'), [2, 3, 6]],
    'savepoint changed table' => [static fn (): mixed => $releasedSavepoint()['changed_tables'], ['wp_options']],
    'savepoint dependency records OR current source' => [static fn (): mixed => in_array('sqlite-update-delete-returning-rowvalue-where-or-current-source-next171', $releasedSavepoint()['dependencies'], true), true],
    'malformed empty SQL still rejected' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute('', $tables, 'option_id', $unique), InvalidArgumentException::class],
    'supported parenthesized OR term selects tuple and scalar ids' => [static fn (): mixed => SQLiteUpdateDeleteReturningSql::execute("DELETE FROM wp_options WHERE ((blog_id, option_name) = (1, 'home')) OR option_id = 7 RETURNING option_id", $tables, 'option_id', $unique)['plan']->selectedIds, [2, 7]],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['rowvalue update delete returning savepoint current source next171 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
