<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSavepointFileHandlePlan;
use PortLibs\LibSqlite\SQLiteVfsFileHandle;

$tests = [];

$page = static fn (string $label, int $pageSize = 64): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$withDatabase = static function (callable $callback) use ($page): mixed {
    $root = sys_get_temp_dir() . '/port-libsqlite-pager-cache-savepoint-' . bin2hex(random_bytes(4));
    mkdir($root, 0777, true);
    file_put_contents($root . '/wp-options.sqlite', $page('page1-original') . $page('page2-original') . $page('page3-original'));

    try {
        return $callback($root, 'wp-options.sqlite');
    } finally {
        foreach (glob($root . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($root)) {
            rmdir($root);
        }
    }
};

$plan = static fn (): array => $withDatabase(static fn (string $root, string $path): array => SQLitePagerCacheSavepointFileHandlePlan::currentNext(
    $root,
    $path,
    64,
    'plugin_settings',
    [
        2 => $page('page2-current-dirty'),
        3 => $page('page3-current-dirty'),
    ],
    [
        2 => $page('page2-next-retry'),
        4 => $page('page4-next-append'),
    ],
));

$finalBytes = static fn (): string => $withDatabase(static function (string $root, string $path) use ($page): string {
    SQLitePagerCacheSavepointFileHandlePlan::currentNext(
        $root,
        $path,
        64,
        'plugin_settings',
        [2 => $page('page2-current-dirty'), 3 => $page('page3-current-dirty')],
        [2 => $page('page2-next-retry'), 4 => $page('page4-next-append')],
    );

    return (new SQLiteVfsFileHandle($root, $path))->readAt(0, 64 * 4)['zero_filled_data'];
});

$cases = [
    'status is applied' => [static fn (): mixed => $plan()['status'], 'applied'],
    'path is preserved' => [static fn (): mixed => $plan()['path'], 'wp-options.sqlite'],
    'page size is preserved' => [static fn (): mixed => $plan()['page_size'], 64],
    'savepoint is preserved' => [static fn (): mixed => $plan()['savepoint'], 'plugin_settings'],
    'current page numbers sorted by insertion' => [static fn (): mixed => $plan()['current']['written_page_numbers'], [2, 3]],
    'current captures first page before image' => [static fn (): mixed => $plan()['current']['captured_pages'][0]['page_number'], 2],
    'current captures first page size' => [static fn (): mixed => $plan()['current']['captured_pages'][0]['captured_bytes'], 64],
    'current first page is not short read' => [static fn (): mixed => $plan()['current']['captured_pages'][0]['zero_filled_short_read'], false],
    'current writes first page size' => [static fn (): mixed => $plan()['current']['captured_pages'][0]['written_bytes'], 64],
    'current captures second page' => [static fn (): mixed => $plan()['current']['captured_pages'][1]['page_number'], 3],
    'current records database bytes before rollback' => [static fn (): mixed => $plan()['current']['database_bytes'], 192],
    'current pending pages are restore pages' => [static fn (): mixed => $plan()['current']['pending_page_numbers'], [2, 3]],
    'rollback restores current dirty pages' => [static fn (): mixed => $plan()['rollback']['restored_page_numbers'], [2, 3]],
    'rollback has no missing pages' => [static fn (): mixed => $plan()['rollback']['missing_page_numbers'], []],
    'rollback keeps aligned database image' => [static fn (): mixed => $plan()['rollback']['database_bytes'], 192],
    'rollback keeps transaction active' => [static fn (): mixed => $plan()['rollback']['transaction_active_after'], true],
    'next page numbers include retry and append' => [static fn (): mixed => $plan()['next']['written_page_numbers'], [2, 4]],
    'next captures retry page' => [static fn (): mixed => $plan()['next']['captured_pages'][0]['page_number'], 2],
    'next retry capture matches rollback image' => [static fn (): mixed => $plan()['next']['captured_pages'][0]['captured_matches_rollback'], true],
    'next captures appended page' => [static fn (): mixed => $plan()['next']['captured_pages'][1]['page_number'], 4],
    'next appended page captures zero filled before image' => [static fn (): mixed => $plan()['next']['captured_pages'][1]['zero_filled_short_read'], true],
    'next pending pages include retry and append' => [static fn (): mixed => $plan()['next']['pending_page_numbers'], [2, 4]],
    'next expands database image' => [static fn (): mixed => $plan()['next']['database_bytes'], 256],
    'operation count includes capture write restore capture write' => [static fn (): mixed => count($plan()['operations']), 10],
    'operation 0 captures before image' => [static fn (): mixed => $plan()['operations'][0]['op'], 'capture_before_image'],
    'operation 1 writes current page' => [static fn (): mixed => $plan()['operations'][1]['op'], 'write_current_page'],
    'operation 2 captures second before image' => [static fn (): mixed => $plan()['operations'][2]['page_number'], 3],
    'operation 3 writes second current page' => [static fn (): mixed => $plan()['operations'][3]['reason'], 'apply_current_dirty_page_to_file_handle'],
    'operation 4 restores first page' => [static fn (): mixed => $plan()['operations'][4]['op'], 'restore_savepoint_page'],
    'operation 4 restore page number' => [static fn (): mixed => $plan()['operations'][4]['page_number'], 2],
    'operation 4 source frame is target savepoint' => [static fn (): mixed => $plan()['operations'][4]['source_frame'], 'plugin_settings'],
    'operation 5 restores second page' => [static fn (): mixed => $plan()['operations'][5]['page_number'], 3],
    'operation 6 captures retry page after rollback' => [static fn (): mixed => $plan()['operations'][6]['op'], 'capture_next_before_image'],
    'operation 7 writes retry page' => [static fn (): mixed => $plan()['operations'][7]['op'], 'write_next_page'],
    'operation 8 captures append page' => [static fn (): mixed => $plan()['operations'][8]['page_number'], 4],
    'operation 9 writes append page' => [static fn (): mixed => $plan()['operations'][9]['offset'], 192],
    'dependencies include slice marker' => [static fn (): mixed => in_array('sqlite-pager-cache-savepoint-file-handle-current-next76', $plan()['dependencies'], true), true],
    'dependencies include vfs handle' => [static fn (): mixed => in_array('vfs-file-handle-primitive', $plan()['dependencies'], true), true],
    'dependencies include savepoint rollback' => [static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true), true],
    'final page 1 is untouched' => [static fn (): mixed => rtrim(substr($finalBytes(), 0, 64), '.'), 'page1-original'],
    'final retry page uses next bytes' => [static fn (): mixed => rtrim(substr($finalBytes(), 64, 64), '.'), 'page2-next-retry'],
    'final page 3 was rolled back' => [static fn (): mixed => rtrim(substr($finalBytes(), 128, 64), '.'), 'page3-original'],
    'final append page exists' => [static fn (): mixed => rtrim(substr($finalBytes(), 192, 64), '.'), 'page4-next-append'],
    'bad page size rejected' => [static function (): mixed { try { SQLitePagerCacheSavepointFileHandlePlan::currentNext('/tmp', 'x', 0, 's', [1 => 'x'], [1 => 'x']); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'empty savepoint rejected' => [static function (): mixed { try { SQLitePagerCacheSavepointFileHandlePlan::currentNext('/tmp', 'x', 1, '', [1 => 'x'], [1 => 'x']); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'empty current write set rejected' => [static function (): mixed { try { SQLitePagerCacheSavepointFileHandlePlan::currentNext('/tmp', 'x', 1, 's', [], [1 => 'x']); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'empty next write set rejected' => [static function (): mixed { try { SQLitePagerCacheSavepointFileHandlePlan::currentNext('/tmp', 'x', 1, 's', [1 => 'x'], []); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'zero page rejected' => [static function (): mixed { try { SQLitePagerCacheSavepointFileHandlePlan::currentNext('/tmp', 'x', 1, 's', [0 => 'x'], [1 => 'x']); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'short page rejected' => [static function (): mixed { try { SQLitePagerCacheSavepointFileHandlePlan::currentNext('/tmp', 'x', 4, 's', [1 => 'xx'], [1 => 'xxxx']); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
    'short next page rejected' => [static function (): mixed { try { SQLitePagerCacheSavepointFileHandlePlan::currentNext('/tmp', 'x', 4, 's', [1 => 'xxxx'], [1 => 'xx']); } catch (InvalidArgumentException) { return 'rejected'; } return 'accepted'; }, 'rejected'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache savepoint file handle current next76 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
