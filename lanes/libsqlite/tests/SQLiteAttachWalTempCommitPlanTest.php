<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempPlan;

$tests = [];

$schemas = [
    'main' => ['journal_mode' => 'wal', 'page_count' => 4, 'change_counter' => 12, 'wal_frame_count' => 8, 'tables' => ['wp_options', 'wp_posts']],
    'temp' => ['journal_mode' => 'delete', 'page_count' => 2, 'change_counter' => 3, 'tables' => ['wp_options_temp']],
    'archive' => ['journal_mode' => 'wal', 'page_count' => 3, 'change_counter' => 90, 'wal_frame_count' => 20, 'tables' => ['wp_options_archive']],
    'analytics' => ['journal_mode' => 'truncate', 'page_count' => 6, 'change_counter' => 5, 'tables' => ['wp_counts']],
    'readonly' => ['journal_mode' => 'wal', 'page_count' => 1, 'change_counter' => 0, 'read_only' => true, 'tables' => ['wp_locked']],
];

$writes = [
    ['table' => 'wp_options_temp', 'page' => 2, 'bytes' => 256],
    ['table' => 'wp_options', 'page' => 5, 'bytes' => 512],
    ['schema' => 'archive', 'table' => 'wp_options_archive', 'page' => 4, 'bytes' => 512],
    ['schema' => 'analytics', 'table' => 'wp_counts', 'page' => 6, 'bytes' => 128],
    ['schema' => 'main', 'table' => 'wp_posts', 'page' => 3, 'bytes' => 64],
    ['schema' => 'archive', 'table' => 'wp_options_archive', 'page' => 4, 'bytes' => 32],
];

$plan = static fn (): array => SQLiteAttachWalTempPlan::plan($schemas, $writes, 'main');
$memoryPlan = static fn (): array => SQLiteAttachWalTempPlan::plan($schemas, [['table' => 'wp_options_temp', 'page' => 3]], 'temp', true);
$readPlan = static fn (): array => SQLiteAttachWalTempPlan::plan($schemas, [], 'archive');

$cases = [
    'commit status' => [static fn (): mixed => $plan()['status'], 'committed'],
    'current schema normalized' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan($schemas, $writes, '"MAIN"')['current_schema'], 'main'],
    'search order prioritizes temp' => [static fn (): mixed => array_slice($plan()['search_order'], 0, 2), ['temp', 'main']],
    'attached schemas sorted after main' => [static fn (): mixed => array_slice($plan()['search_order'], 2), ['analytics', 'archive', 'readonly']],
    'write count preserves input events' => [static fn (): mixed => $plan()['write_count'], 6],
    'operation count includes dirty wal frames and rollback schemas' => [static fn (): mixed => $plan()['operation_count'], 5],
    'wal schema list' => [static fn (): mixed => $plan()['wal_schemas'], ['main', 'archive']],
    'rollback schema list' => [static fn (): mixed => $plan()['rollback_schemas'], ['temp', 'analytics']],
    'memory schemas empty by default' => [static fn (): mixed => $plan()['memory_schemas'], []],
    'readonly schema skipped without writes' => [static fn (): mixed => in_array('readonly', $plan()['skipped_schemas'], true), true],
    'cache invalidation stays false for data transaction' => [static fn (): mixed => $plan()['cache_invalidated'], false],
    'dependency includes attach wal temp routing' => [static fn (): mixed => in_array('sqlite-attach-wal-temp-transaction-routing', $plan()['dependencies'], true), true],
    'dependency includes pager routing' => [static fn (): mixed => in_array('sqlite-attached-pager-transaction-routing', $plan()['dependencies'], true), true],
    'temp write resolved from unqualified table' => [static fn (): mixed => $plan()['current']['writes'][0]['schema'], 'temp'],
    'main write resolved from unqualified table' => [static fn (): mixed => $plan()['current']['writes'][1]['schema'], 'main'],
    'archive write keeps explicit schema' => [static fn (): mixed => $plan()['current']['writes'][2]['schema'], 'archive'],
    'analytics write keeps explicit schema' => [static fn (): mixed => $plan()['current']['writes'][3]['schema'], 'analytics'],
    'main explicit write keeps table' => [static fn (): mixed => $plan()['current']['writes'][4]['table'], 'wp_posts'],
    'write bytes preserved' => [static fn (): mixed => $plan()['current']['writes'][5]['bytes'], 32],
    'main page count grows to page five' => [static fn (): mixed => $plan()['next']['schemas']['main']['page_count'], 5],
    'main change counter increments once' => [static fn (): mixed => $plan()['next']['schemas']['main']['change_counter'], 13],
    'main wal frames increase by dirty pages' => [static fn (): mixed => $plan()['next']['schemas']['main']['wal_frame_count'], 10],
    'archive page count grows to page four' => [static fn (): mixed => $plan()['next']['schemas']['archive']['page_count'], 4],
    'archive change counter increments once despite duplicate page writes' => [static fn (): mixed => $plan()['next']['schemas']['archive']['change_counter'], 91],
    'archive wal frames increase once for duplicate page' => [static fn (): mixed => $plan()['next']['schemas']['archive']['wal_frame_count'], 21],
    'temp page count remains two' => [static fn (): mixed => $plan()['next']['schemas']['temp']['page_count'], 2],
    'temp change counter increments' => [static fn (): mixed => $plan()['next']['schemas']['temp']['change_counter'], 4],
    'temp wal frame count remains zero' => [static fn (): mixed => $plan()['next']['schemas']['temp']['wal_frame_count'], 0],
    'analytics page count remains six' => [static fn (): mixed => $plan()['next']['schemas']['analytics']['page_count'], 6],
    'analytics change counter increments' => [static fn (): mixed => $plan()['next']['schemas']['analytics']['change_counter'], 6],
    'readonly page count unchanged' => [static fn (): mixed => $plan()['next']['schemas']['readonly']['page_count'], 1],
    'first operation deletes temp rollback journal' => [static fn (): mixed => $plan()['operations'][0]['op'], 'delete_temp_rollback_journal'],
    'first operation reason' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'temp_journal_delete_on_commit'],
    'temp operation pages unique sorted' => [static fn (): mixed => $plan()['operations'][0]['pages'], [2]],
    'main first wal frame op' => [static fn (): mixed => $plan()['operations'][1]['op'], 'append_wal_frame'],
    'main first wal frame schema' => [static fn (): mixed => $plan()['operations'][1]['schema'], 'main'],
    'main first wal frame number' => [static fn (): mixed => $plan()['operations'][1]['frame'], 9],
    'main first wal frame page' => [static fn (): mixed => $plan()['operations'][1]['page'], 3],
    'main first wal frame is not commit' => [static fn (): mixed => $plan()['operations'][1]['commit'], false],
    'main second wal frame is commit' => [static fn (): mixed => $plan()['operations'][2]['commit'], true],
    'main second wal frame page' => [static fn (): mixed => $plan()['operations'][2]['page'], 5],
    'analytics operation finalizes rollback journal' => [static fn (): mixed => $plan()['operations'][3]['op'], 'finalize_rollback_journal'],
    'analytics operation reason' => [static fn (): mixed => $plan()['operations'][3]['reason'], 'rollback_journal_commit'],
    'archive wal frame op' => [static fn (): mixed => $plan()['operations'][4]['op'], 'append_wal_frame'],
    'archive wal frame schema' => [static fn (): mixed => $plan()['operations'][4]['schema'], 'archive'],
    'archive wal frame number advances in attached wal file' => [static fn (): mixed => $plan()['operations'][4]['frame'], 21],
    'archive wal frame is commit' => [static fn (): mixed => $plan()['operations'][4]['commit'], true],
    'read transaction status' => [static fn (): mixed => $readPlan()['status'], 'read_transaction_closed'],
    'read transaction operations empty' => [static fn (): mixed => $readPlan()['operations'], []],
    'read transaction current schema can be attached' => [static fn (): mixed => $readPlan()['current_schema'], 'archive'],
    'memory temp status' => [static fn (): mixed => $memoryPlan()['status'], 'committed'],
    'memory temp schema list' => [static fn (): mixed => $memoryPlan()['memory_schemas'], ['temp']],
    'memory temp rollback list empty' => [static fn (): mixed => $memoryPlan()['rollback_schemas'], []],
    'memory temp operation' => [static fn (): mixed => $memoryPlan()['operations'][0]['op'], 'discard_temp_memory_journal_after_commit'],
    'memory temp reason' => [static fn (): mixed => $memoryPlan()['operations'][0]['reason'], 'temp_store_memory_commit'],
    'memory temp page count grows' => [static fn (): mixed => $memoryPlan()['next']['schemas']['temp']['page_count'], 3],
    'memory temp change counter increments' => [static fn (): mixed => $memoryPlan()['next']['schemas']['temp']['change_counter'], 4],
    'default unresolved unqualified table routes to main' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan($schemas, [['table' => 'wp_missing', 'page' => 1]])['current']['writes'][0]['schema'], 'main'],
    'change counter wraps' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan(['main' => ['change_counter' => 0xffffffff, 'tables' => ['wp_options']], 'temp' => ['tables' => []]], [['table' => 'wp_options', 'page' => 1]])['next']['schemas']['main']['change_counter'], 0],
    'delete journal mode accepted' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan(['main' => ['journal_mode' => 'delete', 'tables' => ['wp_options']], 'temp' => ['tables' => []]], [['table' => 'wp_options', 'page' => 1]])['rollback_schemas'], ['main']],
    'memory journal mode uses rollback path for main' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan(['main' => ['journal_mode' => 'memory', 'tables' => ['wp_options']], 'temp' => ['tables' => []]], [['table' => 'wp_options', 'page' => 1]])['operations'][0]['op'], 'finalize_rollback_journal'],
    'quoted schema write normalizes' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan($schemas, [['schema' => '[Archive]', 'table' => 'wp_options_archive', 'page' => 2]])['current']['writes'][0]['schema'], 'archive'],
    'explicit temp schema write normalizes' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan($schemas, [['schema' => '`TEMP`', 'table' => 'wp_options_temp', 'page' => 2]])['current']['writes'][0]['schema'], 'temp'],
    'schema files preserved' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan(['main' => ['file' => '/tmp/wp.sqlite'], 'temp' => ['file' => '']], [])['current']['schemas']['main']['file'], '/tmp/wp.sqlite'],
    'table names normalize case for resolution' => [static fn (): mixed => SQLiteAttachWalTempPlan::plan(['main' => ['tables' => ['WP_OPTIONS']], 'temp' => ['tables' => []]], [['table' => 'wp_options', 'page' => 1]])['current']['writes'][0]['schema'], 'main'],
    'bad empty schemas rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan([], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad missing temp rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan(['main' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad missing main rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan(['temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad current schema rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempPlan::plan($schemas, [], 'missing'); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad journal mode rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan(['main' => ['journal_mode' => 'bad'], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad page count rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan(['main' => ['page_count' => 0], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad change counter rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan(['main' => ['change_counter' => -1], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad wal frame count rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan(['main' => ['wal_frame_count' => -1], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad table name rejected' => [static function (): mixed { try { SQLiteAttachWalTempPlan::plan(['main' => ['tables' => ['']], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write table rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempPlan::plan($schemas, [['table' => '', 'page' => 1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write page rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempPlan::plan($schemas, [['table' => 'wp_options', 'page' => 0]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write bytes rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempPlan::plan($schemas, [['table' => 'wp_options', 'page' => 1, 'bytes' => -1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad explicit schema rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempPlan::plan($schemas, [['schema' => 'missing', 'table' => 'wp_options', 'page' => 1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'read only write rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempPlan::plan($schemas, [['table' => 'wp_locked', 'page' => 1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['attach wal temp commit plan ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
