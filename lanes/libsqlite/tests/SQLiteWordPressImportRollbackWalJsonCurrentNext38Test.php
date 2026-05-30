<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteJsonImportRollbackWalPlan;

$pageSize = 512;

$databaseBytes = static fn (): string => str_pad('sqlite-page-1', $pageSize, "\0")
    . str_pad('plugin-before', $pageSize, "\0")
    . str_pad('theme-before', $pageSize, "\0")
    . str_pad('broken-before', $pageSize, "\0");

$walBytes = static function (int $frames = 4) use ($pageSize): string {
    $bytes = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, 0x61, 0x62, 0, 0);
    for ($index = 1; $index <= $frames; $index++) {
        $bytes .= pack('N*', $index + 1, 0, 0x61, 0x62, 0, 0)
            . str_pad("wal-frame-{$index}", $pageSize, "\0");
    }

    return $bytes;
};

$rows = static fn (): array => [
    [
        'option_id' => 1,
        'option_name' => 'plugin_settings',
        'option_value' => '{"enabled":false,"version":1}',
        'autoload' => 'yes',
        'page_number' => 2,
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_mods_twentyfive',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['palette' => ['accent' => 'blue']])),
        'autoload' => 'yes',
        'page_number' => 3,
    ],
    [
        'option_id' => 3,
        'option_name' => 'broken_import_payload',
        'option_value' => '{"enabled":',
        'autoload' => 'no',
        'page_number' => 4,
    ],
];

$mutations = static fn (): array => [
    [
        'statement' => 'enable_plugin',
        'option_name' => 'plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 1,
    ],
    [
        'statement' => 'theme_palette',
        'option_name' => 'theme_mods_twentyfive',
        'function' => 'jsonb_set',
        'path' => '$.palette.accent',
        'value' => new SQLiteJsonSubtypeValue('{"slug":"green","contrast":7}'),
        'wal_frame_index' => 2,
    ],
    [
        'statement' => 'broken_payload',
        'option_name' => 'broken_import_payload',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 3,
    ],
];

$plan = static fn (array $options = [], ?array $planRows = null, ?array $planMutations = null): array => SQLiteJsonImportRollbackWalPlan::plan(
    $planRows ?? $rows(),
    $planMutations ?? $mutations(),
    array_replace([
        'database_bytes' => $databaseBytes(),
        'page_size' => $pageSize,
        'wal_bytes' => $walBytes(),
    ], $options)
);

$validMutations = [
    [
        'statement' => 'enable_plugin',
        'option_name' => 'plugin_settings',
        'function' => 'json_set',
        'path' => '$.enabled',
        'value' => true,
        'wal_frame_index' => 1,
    ],
    [
        'statement' => 'theme_palette',
        'option_name' => 'theme_mods_twentyfive',
        'function' => 'jsonb_set',
        'path' => '$.palette.accent',
        'value' => new SQLiteJsonSubtypeValue('{"slug":"green","contrast":7}'),
        'wal_frame_index' => 2,
    ],
];

$cases = [
    'failed json import rolls back current batch status' => static fn (): mixed => $plan()['status'],
    'rollback is required by default on failed statement' => static fn (): mixed => $plan()['rollback_required'],
    'transaction name is forwarded' => static fn (): mixed => $plan()['transaction'],
    'savepoint name is forwarded' => static fn (): mixed => $plan()['savepoint'],
    'page size is forwarded' => static fn (): mixed => $plan()['page_size'],
    'applied statement count includes successful json writes' => static fn (): mixed => $plan()['applied_statement_count'],
    'failed statement count records malformed json write' => static fn (): mixed => $plan()['failed_statement_count'],
    'failed statement name list is preserved' => static fn (): mixed => $plan()['failed_statements'],
    'database changed before rollback' => static fn (): mixed => $plan()['database_changed_before_rollback'],
    'database is restored to pre batch image' => static fn (): mixed => $plan()['database_restored_to_before'],
    'restored database bytes equal original' => static fn (): mixed => $plan()['restored_database_bytes'] === $databaseBytes(),
    'after import database differs from original' => static fn (): mixed => $plan()['database_bytes_after_import'] !== $databaseBytes(),
    'wal frame count before rollback is four' => static fn (): mixed => $plan()['wal_frame_count_before'],
    'wal frame count after rollback returns to savepoint frame' => static fn (): mixed => $plan()['wal_frame_count_after'],
    'wal truncate byte offset is header only' => static fn (): mixed => $plan()['wal_truncate_to_bytes'],
    'wal bytes are truncated on rollback' => static fn (): mixed => $plan()['wal_truncated'],
    'wal after rollback keeps only header' => static fn (): mixed => strlen($plan()['wal_bytes_after']),
    'discarded wal frame count includes all current batch frames' => static fn (): mixed => $plan()['discarded_wal_frame_count'],
    'rollback image plan restores mutated pages' => static fn (): mixed => $plan()['rollback_to_savepoint']['restored_page_numbers'],
    'rollback image plan reports no missing images' => static fn (): mixed => $plan()['rollback_to_savepoint']['missing_page_numbers'],
    'wal rollback plan points to savepoint frame zero' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['rollback_to_frame'],
    'wal rollback plan lists applied frame one' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['discarded_wal_frames'][0]['frame_index'],
    'wal rollback plan lists applied frame two' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['discarded_wal_frames'][1]['frame_index'],
    'wal rollback plan records page two' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['discarded_wal_frames'][0]['page_number'],
    'wal rollback plan records page three' => static fn (): mixed => $plan()['wal_rollback_to_savepoint']['discarded_wal_frames'][1]['page_number'],
    'import plan is exposed for diagnostics' => static fn (): mixed => $plan()['import_plan']['status'],
    'import failed rollback restored broken page only at statement level' => static fn (): mixed => $plan()['import_plan']['failed'][0]['rollback']['restored_page_numbers'],
    'import failed rollback discarded failed frame only at statement level' => static fn (): mixed => array_column($plan()['import_plan']['failed'][0]['rollback']['discarded_wal_frames'], 'frame_index'),
    'import final rows still show applied text before outer rollback' => static fn (): mixed => json_decode((string) $plan()['import_plan']['final_rows'][0]['option_value'], true)['enabled'],
    'dependency includes json import savepoint' => static fn (): mixed => in_array('sqlite-application-json-import-savepoint-current', $plan()['dependencies'], true),
    'dependency includes wal rollback' => static fn (): mixed => in_array('sqlite-savepoint-wal-rollback-current', $plan()['dependencies'], true),
    'dependency includes byte truncation' => static fn (): mixed => in_array('sqlite-wal-current-batch-byte-truncation', $plan()['dependencies'], true),
    'all valid json import stays ready' => static fn (): mixed => $plan([], $rows(), $validMutations)['status'],
    'all valid import does not require rollback' => static fn (): mixed => $plan([], $rows(), $validMutations)['rollback_required'],
    'all valid import preserves original wal bytes' => static fn (): mixed => $plan([], $rows(), $validMutations)['wal_bytes_after'] === $walBytes(),
    'all valid import leaves wal frame count unchanged' => static fn (): mixed => $plan([], $rows(), $validMutations)['wal_frame_count_after'],
    'all valid import returns changed database bytes' => static fn (): mixed => $plan([], $rows(), $validMutations)['database_restored_to_before'],
    'explicit rollback_on_error false keeps partial status' => static fn (): mixed => $plan(['rollback_on_error' => false])['status'],
    'explicit rollback_on_error false does not truncate wal' => static fn (): mixed => $plan(['rollback_on_error' => false])['wal_truncated'],
    'custom transaction is forwarded' => static fn (): mixed => $plan(['transaction' => 'wp_current_import'])['transaction'],
    'custom savepoint is forwarded' => static fn (): mixed => $plan(['savepoint' => 'json_current_38'])['savepoint'],
    'short wal header is rejected' => static function () use ($plan): mixed {
        try {
            $plan(['wal_bytes' => 'short']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'partial wal frame tail is rejected' => static function () use ($plan, $walBytes): mixed {
        try {
            $plan(['wal_bytes' => $walBytes() . 'tail']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'unaligned database bytes are rejected' => static function () use ($plan): mixed {
        try {
            $plan(['database_bytes' => 'not-page-aligned']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'invalid page size is rejected' => static function () use ($plan): mixed {
        try {
            $plan(['page_size' => 513]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'non string wal bytes are rejected' => static function () use ($plan): mixed {
        try {
            $plan(['wal_bytes' => []]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'missing option still triggers current batch rollback' => static fn (): mixed => $plan([], $rows(), [
        ['statement' => 'missing_option', 'option_name' => 'missing', 'path' => '$.enabled', 'value' => true],
    ])['failed_statements'],
    'missing option rollback truncates all wal frames' => static fn (): mixed => $plan([], $rows(), [
        ['statement' => 'missing_option', 'option_name' => 'missing', 'path' => '$.enabled', 'value' => true],
    ])['discarded_wal_frame_count'],
    'empty wal defaults to header only' => static fn (): mixed => strlen($plan(['wal_bytes' => null])['wal_bytes_before']),
    'empty wal default has zero frames before rollback' => static fn (): mixed => $plan(['wal_bytes' => null])['wal_frame_count_before'],
];

$expected = [
    'failed json import rolls back current batch status' => 'rolled_back_current_json_batch',
    'rollback is required by default on failed statement' => true,
    'transaction name is forwarded' => 'application_json_import',
    'savepoint name is forwarded' => 'current_json_batch',
    'page size is forwarded' => 512,
    'applied statement count includes successful json writes' => 2,
    'failed statement count records malformed json write' => 1,
    'failed statement name list is preserved' => ['broken_payload'],
    'database changed before rollback' => true,
    'database is restored to pre batch image' => true,
    'restored database bytes equal original' => true,
    'after import database differs from original' => true,
    'wal frame count before rollback is four' => 4,
    'wal frame count after rollback returns to savepoint frame' => 0,
    'wal truncate byte offset is header only' => 32,
    'wal bytes are truncated on rollback' => true,
    'wal after rollback keeps only header' => 32,
    'discarded wal frame count includes all current batch frames' => 4,
    'rollback image plan restores mutated pages' => [2, 3],
    'rollback image plan reports no missing images' => [],
    'wal rollback plan points to savepoint frame zero' => 0,
    'wal rollback plan lists applied frame one' => 1,
    'wal rollback plan lists applied frame two' => 2,
    'wal rollback plan records page two' => 2,
    'wal rollback plan records page three' => 3,
    'import plan is exposed for diagnostics' => 'partial_rollback',
    'import failed rollback restored broken page only at statement level' => [4],
    'import failed rollback discarded failed frame only at statement level' => [3],
    'import final rows still show applied text before outer rollback' => true,
    'dependency includes json import savepoint' => true,
    'dependency includes wal rollback' => true,
    'dependency includes byte truncation' => true,
    'all valid json import stays ready' => 'ready',
    'all valid import does not require rollback' => false,
    'all valid import preserves original wal bytes' => true,
    'all valid import leaves wal frame count unchanged' => 4,
    'all valid import returns changed database bytes' => false,
    'explicit rollback_on_error false keeps partial status' => 'partial_rollback',
    'explicit rollback_on_error false does not truncate wal' => false,
    'custom transaction is forwarded' => 'wp_current_import',
    'custom savepoint is forwarded' => 'json_current_38',
    'short wal header is rejected' => 'rejected',
    'partial wal frame tail is rejected' => 'rejected',
    'unaligned database bytes are rejected' => 'rejected',
    'invalid page size is rejected' => 'rejected',
    'non string wal bytes are rejected' => 'rejected',
    'missing option still triggers current batch rollback' => ['missing_option'],
    'missing option rollback truncates all wal frames' => 4,
    'empty wal defaults to header only' => 32,
    'empty wal default has zero frames before rollback' => 0,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application import rollback wal json current next38 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
