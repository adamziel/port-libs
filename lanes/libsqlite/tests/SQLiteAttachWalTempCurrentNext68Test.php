<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempCurrentNextPlan;

$tests = [];

$schemas = [
    'main' => ['journal_mode' => 'wal', 'page_count' => 5, 'change_counter' => 100, 'wal_frame_count' => 17, 'tables' => ['wp_options', 'wp_posts'], 'file' => '/srv/wp/main.sqlite'],
    'temp' => ['journal_mode' => 'delete', 'page_count' => 2, 'change_counter' => 4, 'tables' => ['wp_options_stage'], 'file' => ''],
    'archive' => ['journal_mode' => 'wal', 'page_count' => 3, 'change_counter' => 44, 'wal_frame_count' => 8, 'tables' => ['wp_options_archive'], 'file' => '/srv/wp/archive.sqlite'],
    'analytics' => ['journal_mode' => 'persist', 'page_count' => 6, 'change_counter' => 9, 'tables' => ['wp_counts']],
    'readonly' => ['journal_mode' => 'wal', 'page_count' => 1, 'change_counter' => 0, 'read_only' => true, 'tables' => ['wp_locked']],
];

$writes = [
    ['table' => 'wp_options_stage', 'page' => 2, 'bytes' => 100],
    ['table' => 'wp_options', 'page' => 7, 'bytes' => 512],
    ['schema' => 'archive', 'table' => 'wp_options_archive', 'page' => 4, 'bytes' => 256],
    ['schema' => 'archive', 'table' => 'wp_options_archive', 'page' => 6, 'bytes' => 128],
    ['schema' => 'analytics', 'table' => 'wp_counts', 'page' => 5, 'bytes' => 64],
    ['schema' => 'main', 'table' => 'wp_posts', 'page' => 1, 'bytes' => 200, 'ddl' => true],
];

$plan = static fn (): array => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, $writes, 'main');
$memoryPlan = static fn (): array => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['table' => 'wp_options_stage', 'page' => 3, 'bytes' => 10]], 'temp', true);
$readPlan = static fn (): array => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [], 'archive');

$cases = [
    'rollback status' => [static fn (): mixed => $plan()['status'], 'rolled_back'],
    'quoted current schema normalizes' => [static fn (): mixed => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [], '"ARCHIVE"')['current_schema'], 'archive'],
    'search order begins temp main' => [static fn (): mixed => array_slice($plan()['search_order'], 0, 2), ['temp', 'main']],
    'attached schemas sorted' => [static fn (): mixed => array_slice($plan()['search_order'], 2), ['analytics', 'archive', 'readonly']],
    'write count preserves input' => [static fn (): mixed => $plan()['write_count'], 6],
    'operation count groups schemas' => [static fn (): mixed => $plan()['operation_count'], 4],
    'wal schemas list' => [static fn (): mixed => $plan()['wal_schemas'], ['main', 'archive']],
    'rollback schemas list' => [static fn (): mixed => $plan()['rollback_schemas'], ['temp', 'analytics']],
    'memory schemas empty' => [static fn (): mixed => $plan()['memory_schemas'], []],
    'readonly skipped without writes' => [static fn (): mixed => $plan()['skipped_schemas'], ['readonly']],
    'dependency attach current next' => [static fn (): mixed => in_array('sqlite-attach-wal-temp-current-next', $plan()['dependencies'], true), true],
    'dependency rollback routing' => [static fn (): mixed => in_array('sqlite-attached-transaction-rollback-routing', $plan()['dependencies'], true), true],
    'temp unqualified write resolves first' => [static fn (): mixed => $plan()['current']['writes'][0]['schema'], 'temp'],
    'main unqualified write resolves after temp miss' => [static fn (): mixed => $plan()['current']['writes'][1]['schema'], 'main'],
    'archive explicit write resolves' => [static fn (): mixed => $plan()['current']['writes'][2]['schema'], 'archive'],
    'archive duplicate table second page resolves' => [static fn (): mixed => $plan()['current']['writes'][3]['page'], 6],
    'analytics explicit write resolves' => [static fn (): mixed => $plan()['current']['writes'][4]['schema'], 'analytics'],
    'ddl flag preserved' => [static fn (): mixed => $plan()['current']['writes'][5]['ddl'], true],
    'next main page count restored' => [static fn (): mixed => $plan()['next']['schemas']['main']['page_count'], 5],
    'next main change counter restored' => [static fn (): mixed => $plan()['next']['schemas']['main']['change_counter'], 100],
    'next main wal frame count restored' => [static fn (): mixed => $plan()['next']['schemas']['main']['wal_frame_count'], 17],
    'next temp page count restored' => [static fn (): mixed => $plan()['next']['schemas']['temp']['page_count'], 2],
    'next temp change counter restored' => [static fn (): mixed => $plan()['next']['schemas']['temp']['change_counter'], 4],
    'next archive page count restored' => [static fn (): mixed => $plan()['next']['schemas']['archive']['page_count'], 3],
    'next archive wal frame count restored' => [static fn (): mixed => $plan()['next']['schemas']['archive']['wal_frame_count'], 8],
    'next analytics page count restored' => [static fn (): mixed => $plan()['next']['schemas']['analytics']['page_count'], 6],
    'uncommitted main page count grew' => [static fn (): mixed => $plan()['uncommitted']['schemas']['main']['page_count'], 7],
    'uncommitted main change counter advanced' => [static fn (): mixed => $plan()['uncommitted']['schemas']['main']['change_counter'], 101],
    'uncommitted main wal frames include two pages' => [static fn (): mixed => $plan()['uncommitted']['schemas']['main']['wal_frame_count'], 19],
    'uncommitted archive page count grew' => [static fn (): mixed => $plan()['uncommitted']['schemas']['archive']['page_count'], 6],
    'uncommitted archive wal frames include two pages' => [static fn (): mixed => $plan()['uncommitted']['schemas']['archive']['wal_frame_count'], 10],
    'uncommitted temp change counter advanced' => [static fn (): mixed => $plan()['uncommitted']['schemas']['temp']['change_counter'], 5],
    'uncommitted analytics change counter advanced' => [static fn (): mixed => $plan()['uncommitted']['schemas']['analytics']['change_counter'], 10],
    'temp rollback operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'restore_temp_rollback_journal_pages'],
    'temp rollback pages' => [static fn (): mixed => $plan()['operations'][0]['pages'], [2]],
    'temp rollback reason' => [static fn (): mixed => $plan()['operations'][0]['reason'], 'temp_rollback_journal_abort'],
    'main wal truncate operation' => [static fn (): mixed => $plan()['operations'][1]['op'], 'truncate_uncommitted_wal_frames'],
    'main wal truncate from frame' => [static fn (): mixed => $plan()['operations'][1]['from_frame'], 18],
    'main wal truncate to frame' => [static fn (): mixed => $plan()['operations'][1]['to_frame'], 19],
    'main restore frame count' => [static fn (): mixed => $plan()['operations'][1]['restore_frame_count'], 17],
    'main wal dirty pages sorted' => [static fn (): mixed => $plan()['operations'][1]['pages'], [1, 7]],
    'main wal bytes summed' => [static fn (): mixed => $plan()['operations'][1]['bytes'], 712],
    'analytics rollback operation' => [static fn (): mixed => $plan()['operations'][2]['op'], 'restore_attached_rollback_journal_pages'],
    'analytics rollback reason' => [static fn (): mixed => $plan()['operations'][2]['reason'], 'attached_rollback_journal_abort'],
    'archive wal truncate operation' => [static fn (): mixed => $plan()['operations'][3]['op'], 'truncate_uncommitted_wal_frames'],
    'archive wal truncate from frame' => [static fn (): mixed => $plan()['operations'][3]['from_frame'], 9],
    'archive wal truncate to frame' => [static fn (): mixed => $plan()['operations'][3]['to_frame'], 10],
    'archive restore frame count' => [static fn (): mixed => $plan()['operations'][3]['restore_frame_count'], 8],
    'archive dirty pages sorted' => [static fn (): mixed => $plan()['operations'][3]['pages'], [4, 6]],
    'archive dirty bytes summed' => [static fn (): mixed => $plan()['operations'][3]['bytes'], 384],
    'cache invalidated true from schema page' => [static fn (): mixed => $plan()['cache_invalidated'], true],
    'cache invalidated schemas only main' => [static fn (): mixed => $plan()['cache_invalidated_schemas'], ['main']],
    'read transaction status' => [static fn (): mixed => $readPlan()['status'], 'read_transaction_closed'],
    'read transaction operations empty' => [static fn (): mixed => $readPlan()['operations'], []],
    'read transaction current schema attached' => [static fn (): mixed => $readPlan()['current_schema'], 'archive'],
    'memory temp status' => [static fn (): mixed => $memoryPlan()['status'], 'rolled_back'],
    'memory temp schema list' => [static fn (): mixed => $memoryPlan()['memory_schemas'], ['temp']],
    'memory temp rollback schemas empty' => [static fn (): mixed => $memoryPlan()['rollback_schemas'], []],
    'memory temp operation' => [static fn (): mixed => $memoryPlan()['operations'][0]['op'], 'discard_temp_memory_statement_pages'],
    'memory temp page restored' => [static fn (): mixed => $memoryPlan()['next']['schemas']['temp']['page_count'], 2],
    'memory temp uncommitted page count grew' => [static fn (): mixed => $memoryPlan()['uncommitted']['schemas']['temp']['page_count'], 3],
    'unresolved write defaults main' => [static fn (): mixed => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['table' => 'wp_missing', 'page' => 1]])['current']['writes'][0]['schema'], 'main'],
    'duplicate pages produce one wal frame' => [static fn (): mixed => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['table' => 'wp_options', 'page' => 3], ['table' => 'wp_options', 'page' => 3]])['operations'][0]['to_frame'], 18],
    'table names resolve case insensitive' => [static fn (): mixed => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['main' => ['tables' => ['WP_OPTIONS']], 'temp' => ['tables' => []]], [['table' => 'wp_options', 'page' => 1]])['current']['writes'][0]['schema'], 'main'],
    'quoted explicit schema resolves' => [static fn (): mixed => SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['schema' => '[Archive]', 'table' => 'wp_options_archive', 'page' => 2]])['current']['writes'][0]['schema'], 'archive'],
    'schema files preserved' => [static fn (): mixed => $plan()['next']['schemas']['main']['file'], '/srv/wp/main.sqlite'],
    'bad empty schemas rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan([], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad missing temp rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['main' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad missing main rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad current schema rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [], 'missing'); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad journal mode rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['main' => ['journal_mode' => 'bad'], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad page count rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['main' => ['page_count' => 0], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad change counter rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['main' => ['change_counter' => -1], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad wal frame count rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['main' => ['wal_frame_count' => -1], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad table name rejected' => [static function (): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan(['main' => ['tables' => ['']], 'temp' => []], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write table rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['table' => '', 'page' => 1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write page rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['table' => 'wp_options', 'page' => 0]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad write bytes rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['table' => 'wp_options', 'page' => 1, 'bytes' => -1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'bad explicit schema rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['schema' => 'missing', 'table' => 'wp_options', 'page' => 1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'read only write rejected' => [static function () use ($schemas): mixed { try { SQLiteAttachWalTempCurrentNextPlan::rollbackPlan($schemas, [['table' => 'wp_locked', 'page' => 1]]); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['attach wal temp current next ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
