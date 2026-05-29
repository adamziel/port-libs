<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerDeferredReturningViewCurrentSourceNextPlan;

$record127 = static fn (string $type, string $name, string $table, ?int $root, ?string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord(
    $type,
    $name,
    $table,
    $root,
    $sql,
    $rowid,
);

$catalog127 = static function () use ($record127): SQLiteAttachedSchemaCatalog {
    return new SQLiteAttachedSchemaCatalog([
        $record127('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)', 1),
        $record127('table', 'wp_optionmeta', 'wp_optionmeta', 3, 'CREATE TABLE wp_optionmeta(meta_id integer primary key, option_id integer, meta_key text)', 2),
        $record127('table', 'wp_option_audit', 'wp_option_audit', 4, 'CREATE TABLE wp_option_audit(option_id integer, label text, option_name text)', 3),
        $record127('view', 'wp_option_import_view', 'wp_option_import_view', 0, "CREATE VIEW wp_option_import_view AS SELECT option_id, option_name, option_value, autoload FROM wp_options WHERE autoload = 'yes'", 4),
        $record127('trigger', 'wp_option_import_view_insert', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'view-import', new.option_name); SELECT new.option_id, new.option_name; END", 5),
        $record127('trigger', 'wp_option_import_view_insert_rollback', 'wp_option_import_view', 0, "CREATE TRIGGER wp_option_import_view_insert_rollback INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.option_id, new.option_name, new.option_value, new.autoload); INSERT INTO wp_option_audit(option_id, label, option_name) VALUES(new.option_id, 'rollback-current-savepoint', new.option_name); SELECT new.option_id, new.option_name; END", 6),
    ]);
};

$tables127 = [
    'main.wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ],
    'main.wp_optionmeta' => [
        ['meta_id' => 10, 'option_id' => 1, 'meta_key' => '_seed'],
        ['meta_id' => 20, 'option_id' => 2, 'meta_key' => '_current'],
        ['meta_id' => 30, 'option_id' => 4, 'meta_key' => '_next'],
    ],
    'main.wp_option_audit' => [
        ['option_id' => 1, 'label' => 'seed', 'option_name' => 'siteurl'],
    ],
];
$currentRows127 = [
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
];
$nextRows127 = [
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'rewrite_rules', 'option_value' => 'cached', 'autoload' => 'no'],
];
$fk127 = [
    'parent_table' => 'main.wp_options',
    'child_table' => 'main.wp_optionmeta',
    'parent_key' => 'option_id',
    'child_key' => 'option_id',
    'deferred' => true,
];
$returning127 = ['option_id', 'option_name', 'value' => 'option_value'];
$options127 = [
    'current_source' => 'main@view-cookie-127',
    'next_source' => 'main@view-cookie-128',
    'page_size' => 512,
    'savepoint_page_images' => [2 => str_repeat('a', 512), 3 => str_repeat('b', 512)],
    'dirty_pages' => [2 => str_repeat('c', 512), 3 => str_repeat('d', 512)],
    'wal_start_frame' => 11,
    'wal_frames' => [
        ['frame_index' => 12, 'page_number' => 2],
        ['frame_index' => 13, 'page_number' => 3, 'commit_frame' => true],
    ],
];

$run127 = static fn (array $tables = null, array $current = null, array $next = null, array $extra = [], array $projection = null, array $fk = null): array => SQLiteTriggerDeferredReturningViewCurrentSourceNextPlan::execute(
    $catalog127(),
    'wp_option_import_view_insert',
    $tables ?? $tables127,
    $current ?? $currentRows127,
    $next ?? $nextRows127,
    $fk ?? $fk127,
    'wp_view_import_127',
    $projection ?? $returning127,
    $extra + $options127,
);

$committed127 = static fn (): array => $run127();
$violatingTables127 = $tables127;
$violatingTables127['main.wp_optionmeta'][] = ['meta_id' => 40, 'option_id' => 99, 'meta_key' => '_orphan'];
$rolled127 = static fn (): array => $run127($violatingTables127);
$blocked127 = static fn (): array => $run127($violatingTables127, null, null, ['rollback_on_deferred_violation' => false]);
$phaseRollback127 = static fn (): array => $run127($tables127, null, null, ['current_trigger_name' => 'wp_option_import_view_insert_rollback']);
$star127 = static fn (): array => $run127(null, [
    ['option_id' => 6, 'option_name' => 'star_current', 'option_value' => 'star', 'autoload' => 'yes'],
], [
    ['option_id' => 7, 'option_name' => 'star_next', 'option_value' => 'next', 'autoload' => 'yes'],
], [], ['*']);

$cases127 = [
    'commit status inherited from view plan' => [static fn (): mixed => $committed127()['status'], 'current-next-view-trigger-returning-applied'],
    'commit release status is released' => [static fn (): mixed => $committed127()['release_status'], 'released'],
    'commit deferred barrier open' => [static fn (): mixed => $committed127()['deferred_barrier_open'], true],
    'commit barrier reason' => [static fn (): mixed => $committed127()['deferred_barrier_reason'], 'no-deferred-violations'],
    'commit source current token' => [static fn (): mixed => $committed127()['source_transition']['current'], 'main@view-cookie-127'],
    'commit source next token' => [static fn (): mixed => $committed127()['source_transition']['next'], 'main@view-cookie-128'],
    'commit source visible next' => [static fn (): mixed => $committed127()['source_transition']['visible'], 'main@view-cookie-128'],
    'commit source barrier admits next' => [static fn (): mixed => $committed127()['source_transition']['barrier'], 'commit-admits-next-source'],
    'commit current stream count' => [static fn (): mixed => count($committed127()['current_source_stream']), 2],
    'commit next stream count' => [static fn (): mixed => count($committed127()['next_source_stream']), 2],
    'commit current stream sources' => [static fn (): mixed => array_column($committed127()['current_source_stream'], 'source'), ['main@view-cookie-127', 'main@view-cookie-127']],
    'commit next stream sources' => [static fn (): mixed => array_column($committed127()['next_source_stream'], 'source'), ['main@view-cookie-128', 'main@view-cookie-128']],
    'commit current admitted flags' => [static fn (): mixed => array_column($committed127()['current_source_stream'], 'admitted'), [true, true]],
    'commit next admitted flags' => [static fn (): mixed => array_column($committed127()['next_source_stream'], 'admitted'), [true, true]],
    'commit admitted next rows count' => [static fn (): mixed => count($committed127()['admitted_next_source_stream']), 2],
    'commit suppressed next empty' => [static fn (): mixed => $committed127()['suppressed_next_source_stream'], []],
    'commit returning rows count' => [static fn (): mixed => count($committed127()['returning_rows']), 4],
    'commit returning row phase order' => [static fn (): mixed => array_column($committed127()['returning_rows'], 'phase'), ['current', 'current', 'next', 'next']],
    'commit returning option names' => [static fn (): mixed => array_column(array_column($committed127()['returning_rows'], 'returning'), 'option_name'), ['home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'commit final table includes all view inserts' => [static fn (): mixed => array_column($committed127()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'commit audit table includes all view writes' => [static fn (): mixed => array_column($committed127()['tables']['main.wp_option_audit'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'commit deferred violations empty' => [static fn (): mixed => $committed127()['deferred_violations'], []],
    'commit deferred violation count zero' => [static fn (): mixed => $committed127()['deferred_violation_count'], 0],
    'commit attempted stream count' => [static fn (): mixed => count($committed127()['attempted_source_stream']), 4],
    'commit attempted source ordinals' => [static fn (): mixed => array_column($committed127()['attempted_source_stream'], 'source_ordinal'), [0, 1, 0, 1]],
    'commit dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-deferred-returning-view-current-source-next127', $committed127()['dependencies'], true), true],
    'commit release barrier dependency marker' => [static fn (): mixed => in_array('sqlite-view-trigger-returning-deferred-fk-release-barrier', $committed127()['dependencies'], true), true],

    'rollback status' => [static fn (): mixed => $rolled127()['status'], 'deferred-view-returning-rolled-back-to-current-source'],
    'rollback release status failed' => [static fn (): mixed => $rolled127()['release_status'], 'deferred-foreign-key-failed'],
    'rollback source visible current' => [static fn (): mixed => $rolled127()['source_transition']['visible'], 'main@view-cookie-127'],
    'rollback barrier name' => [static fn (): mixed => $rolled127()['source_transition']['barrier'], 'rollback-to-current-source'],
    'rollback deferred barrier closed' => [static fn (): mixed => $rolled127()['deferred_barrier_open'], false],
    'rollback barrier reason' => [static fn (): mixed => $rolled127()['deferred_barrier_reason'], 'rollback-on-deferred-violation'],
    'rollback violation count' => [static fn (): mixed => $rolled127()['deferred_violation_count'], 1],
    'rollback violation child key' => [static fn (): mixed => $rolled127()['deferred_violations'][0]['child_key'], 99],
    'rollback violation phase' => [static fn (): mixed => $rolled127()['deferred_violations'][0]['phase'], 'deferred-release'],
    'rollback final table restored to seed only' => [static fn (): mixed => array_column($rolled127()['tables']['main.wp_options'], 'option_name'), ['siteurl']],
    'rollback returning rows suppressed' => [static fn (): mixed => $rolled127()['returning_rows'], []],
    'rollback current attempted rows retained' => [static fn (): mixed => array_column(array_column(array_slice($rolled127()['attempted_source_stream'], 0, 2), 'returning'), 'option_name'), ['home', 'blogname']],
    'rollback next attempted rows retained' => [static fn (): mixed => array_column(array_column(array_slice($rolled127()['attempted_source_stream'], 2), 'returning'), 'option_name'), ['active_plugins', 'rewrite_rules']],
    'rollback admitted next empty' => [static fn (): mixed => $rolled127()['admitted_next_source_stream'], []],
    'rollback suppressed next count' => [static fn (): mixed => count($rolled127()['suppressed_next_source_stream']), 2],
    'rollback next stream admitted false' => [static fn (): mixed => array_column($rolled127()['next_source_stream'], 'admitted'), [false, false]],
    'rollback flag true' => [static fn (): mixed => $rolled127()['rolled_back_to_current_source'], true],

    'blocked status' => [static fn (): mixed => $blocked127()['status'], 'deferred-view-returning-blocked-before-next-source'],
    'blocked release status failed' => [static fn (): mixed => $blocked127()['release_status'], 'deferred-foreign-key-failed'],
    'blocked source visible next' => [static fn (): mixed => $blocked127()['source_transition']['visible'], 'main@view-cookie-128'],
    'blocked barrier name' => [static fn (): mixed => $blocked127()['source_transition']['barrier'], 'deferred-blocked-before-next-source'],
    'blocked keeps final table writes' => [static fn (): mixed => array_column($blocked127()['tables']['main.wp_options'], 'option_name'), ['siteurl', 'home', 'blogname', 'active_plugins', 'rewrite_rules']],
    'blocked returning rows keep current phase only' => [static fn (): mixed => array_column(array_column($blocked127()['returning_rows'], 'returning'), 'option_name'), ['home', 'blogname']],
    'blocked next suppressed count' => [static fn (): mixed => count($blocked127()['suppressed_next_source_stream']), 2],
    'blocked rollback flag false' => [static fn (): mixed => $blocked127()['rolled_back_to_current_source'], false],

    'phase rollback release status' => [static fn (): mixed => $phaseRollback127()['release_status'], 'phase-savepoint-rolled-back'],
    'phase rollback reason' => [static fn (): mixed => $phaseRollback127()['deferred_barrier_reason'], 'view-trigger-savepoint-rollback'],
    'phase rollback phases' => [static fn (): mixed => $phaseRollback127()['rolled_back_phases'], ['current']],
    'phase rollback still records final deferred violation' => [static fn (): mixed => $phaseRollback127()['deferred_violations'][0]['child_key'], 2],

    'star projection current row visible' => [static fn (): mixed => $star127()['current_source_stream'][0]['returning']['option_name'], 'star_current'],
    'star projection next row visible' => [static fn (): mixed => $star127()['next_source_stream'][0]['returning']['option_value'], 'next'],

    'missing parent table rejected' => [static fn (): mixed => $run127(['main.wp_optionmeta' => []]), InvalidArgumentException::class],
    'missing child key rejected' => [static fn (): mixed => $run127(['main.wp_options' => [['option_id' => 1]], 'main.wp_optionmeta' => [['meta_id' => 1]]]), InvalidArgumentException::class],
    'malformed foreign key rejected' => [static fn (): mixed => $run127(null, null, null, [], null, ['parent_table' => 'wp_options']), InvalidArgumentException::class],
    'bad source token falls back to trigger token' => [static fn (): mixed => $run127(null, null, null, ['current_source' => 'bad source token'])['source_transition']['current'], 'current@current'],
];

foreach ($cases127 as $name => [$callback, $expected]) {
    $tests['trigger deferred returning view current source next127 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
