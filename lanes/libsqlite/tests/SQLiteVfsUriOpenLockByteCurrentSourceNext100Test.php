<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsUriOpenLockByteCurrentSourceNext;

$run100 = static fn (array $operations, array $current = []): array => SQLiteVfsUriOpenLockByteCurrentSourceNext::plan($operations, $current);

$main100 = static fn (): array => $run100([
    ['kind' => 'open', 'source' => 'main', 'filename' => 'file://localhost/srv/www/wp-content/database/wp%20current.sqlite?mode=rw&cache=shared&vfs=unix&psow=1', 'connection' => 'wp-reader'],
    ['kind' => 'open', 'source' => 'main-alias', 'filename' => 'file:/srv/www/wp-content/database/wp%20current.sqlite?mode=rw&cache=private', 'connection' => 'wp-import'],
    ['kind' => 'lock', 'source' => 'main', 'level' => 'shared', 'connection' => 'wp-reader', 'shared_slot' => 7],
    ['kind' => 'lock', 'source' => 'main-alias', 'level' => 'reserved', 'connection' => 'wp-import', 'shared_slot' => 9],
    ['kind' => 'lock', 'source' => 'main-alias', 'level' => 'pending', 'connection' => 'wp-import'],
    ['kind' => 'lock', 'source' => 'main-alias', 'level' => 'exclusive', 'connection' => 'wp-import'],
    ['kind' => 'lock', 'source' => 'main', 'level' => 'none', 'connection' => 'wp-reader'],
    ['kind' => 'lock', 'source' => 'main-alias', 'level' => 'exclusive', 'connection' => 'wp-import'],
    ['kind' => 'close', 'source' => 'main', 'connection' => 'wp-reader'],
    ['kind' => 'close', 'source' => 'main-alias'],
]);

$reopen100 = static function () use ($run100, $main100): array {
    return $run100([
        ['kind' => 'open', 'source' => 'main-alias', 'filename' => 'file:/srv/www/wp-content/database/wp%20current.sqlite?mode=rw&cache=shared', 'connection' => 'wp-cron'],
        ['kind' => 'lock', 'source' => 'main-alias', 'level' => 'shared', 'connection' => 'wp-cron', 'shared_slot' => 11],
    ], $main100()['events'][7]['next']);
};

$readonly100 = static fn (): array => $run100([
    ['kind' => 'open', 'source' => 'archive', 'filename' => 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1', 'connection' => 'wp-report'],
    ['kind' => 'lock', 'source' => 'archive', 'level' => 'shared', 'connection' => 'wp-report', 'shared_slot' => 2],
    ['kind' => 'lock', 'source' => 'archive', 'level' => 'reserved', 'connection' => 'wp-report', 'shared_slot' => 2],
]);

$nolock100 = static fn (): array => $run100([
    ['kind' => 'open', 'source' => 'repair', 'filename' => 'file:/srv/www/wp-content/database/repair.sqlite?mode=rw&nolock=1', 'connection' => 'wp-repair'],
    ['kind' => 'lock', 'source' => 'repair', 'level' => 'shared', 'connection' => 'wp-repair', 'shared_slot' => 3],
]);

$busy100 = static fn (): array => $run100([
    ['kind' => 'open', 'source' => 'busy', 'filename' => 'file:/srv/www/wp-content/database/wp-current.sqlite?mode=rw', 'lock_available' => false, 'busy_timeout' => 15],
    ['kind' => 'lock', 'source' => 'busy', 'level' => 'shared', 'connection' => 'wp-import', 'shared_slot' => 4],
]);

$tests = [];

$tests['vfs uri open lock byte current source next100 dependency marker'] = static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-open-lock-byte-current-source-next100', $main100()['dependencies'], true));
$tests['vfs uri open lock byte current source next100 event count'] = static fn (TestRunner $t) => $t->same(10, count($main100()['events']));
$tests['vfs uri open lock byte current source next100 current starts empty'] = static fn (TestRunner $t) => $t->same(0, $main100()['current']['source_count']);
$tests['vfs uri open lock byte current source next100 open main status'] = static fn (TestRunner $t) => $t->same('opened', $main100()['events'][0]['status']);
$tests['vfs uri open lock byte current source next100 open main selected'] = static fn (TestRunner $t) => $t->same('main', $main100()['events'][0]['next']['selected_source']);
$tests['vfs uri open lock byte current source next100 open path decoded'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp current.sqlite', $main100()['events'][0]['next']['sources']['main']['path']);
$tests['vfs uri open lock byte current source next100 open authority localhost'] = static fn (TestRunner $t) => $t->same('localhost', $main100()['events'][0]['result']['open']['uri']['authority']);
$tests['vfs uri open lock byte current source next100 open cache shared'] = static fn (TestRunner $t) => $t->same('shared', $main100()['events'][0]['next']['sources']['main']['cache']);
$tests['vfs uri open lock byte current source next100 open vfs unix'] = static fn (TestRunner $t) => $t->same('unix', $main100()['events'][0]['next']['sources']['main']['vfs']);
$tests['vfs uri open lock byte current source next100 open dependency shared cache'] = static fn (TestRunner $t) => $t->same(true, in_array('shared-cache-coordination', $main100()['events'][0]['result']['dependencies'], true));
$tests['vfs uri open lock byte current source next100 alias open status'] = static fn (TestRunner $t) => $t->same('opened', $main100()['events'][1]['status']);
$tests['vfs uri open lock byte current source next100 alias same decoded path'] = static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp current.sqlite', $main100()['events'][1]['next']['sources']['main-alias']['path']);
$tests['vfs uri open lock byte current source next100 path sources group aliases'] = static fn (TestRunner $t) => $t->same(['main', 'main-alias'], $main100()['events'][1]['next']['path_sources']['/srv/www/wp-content/database/wp current.sqlite']);
$tests['vfs uri open lock byte current source next100 constants pending'] = static fn (TestRunner $t) => $t->same(1073741824, $main100()['events'][1]['next']['constants']['pending']);
$tests['vfs uri open lock byte current source next100 shared planned'] = static fn (TestRunner $t) => $t->same('planned', $main100()['events'][2]['status']);
$tests['vfs uri open lock byte current source next100 shared holder'] = static fn (TestRunner $t) => $t->same('shared', $main100()['events'][2]['next']['sources']['main']['holders']['wp-reader']);
$tests['vfs uri open lock byte current source next100 shared slot stored'] = static fn (TestRunner $t) => $t->same(7, $main100()['events'][2]['next']['sources']['main']['shared_slots']['wp-reader']);
$tests['vfs uri open lock byte current source next100 shared offset'] = static fn (TestRunner $t) => $t->same(1073741833, $main100()['events'][2]['result']['plan']['acquire'][0]['offset']);
$tests['vfs uri open lock byte current source next100 reserved planned'] = static fn (TestRunner $t) => $t->same('planned', $main100()['events'][3]['status']);
$tests['vfs uri open lock byte current source next100 reserved holder'] = static fn (TestRunner $t) => $t->same('reserved', $main100()['events'][3]['next']['sources']['main-alias']['holders']['wp-import']);
$tests['vfs uri open lock byte current source next100 reserved range'] = static fn (TestRunner $t) => $t->same('reserved', $main100()['events'][3]['result']['plan']['acquire'][1]['name']);
$tests['vfs uri open lock byte current source next100 pending planned'] = static fn (TestRunner $t) => $t->same('planned', $main100()['events'][4]['status']);
$tests['vfs uri open lock byte current source next100 pending releases shared slot'] = static fn (TestRunner $t) => $t->same(false, array_key_exists('wp-import', $main100()['events'][4]['next']['sources']['main-alias']['shared_slots']));
$tests['vfs uri open lock byte current source next100 pending range'] = static fn (TestRunner $t) => $t->same('pending', $main100()['events'][4]['result']['plan']['acquire'][0]['name']);
$tests['vfs uri open lock byte current source next100 exclusive initially blocked across aliases'] = static fn (TestRunner $t) => $t->same('blocked', $main100()['events'][5]['status']);
$tests['vfs uri open lock byte current source next100 exclusive blocker names alias reader'] = static fn (TestRunner $t) => $t->same(['wp-reader:shared'], $main100()['events'][5]['result']['blocking']);
$tests['vfs uri open lock byte current source next100 release reader status'] = static fn (TestRunner $t) => $t->same('released', $main100()['events'][6]['status']);
$tests['vfs uri open lock byte current source next100 release reader removes holder'] = static fn (TestRunner $t) => $t->same([], $main100()['events'][6]['next']['sources']['main']['holders']);
$tests['vfs uri open lock byte current source next100 release reader plan current shared'] = static fn (TestRunner $t) => $t->same('shared', $main100()['events'][6]['result']['plan']['current']);
$tests['vfs uri open lock byte current source next100 release reader plan next none'] = static fn (TestRunner $t) => $t->same('none', $main100()['events'][6]['result']['plan']['next']);
$tests['vfs uri open lock byte current source next100 exclusive repeat planned'] = static fn (TestRunner $t) => $t->same('planned', $main100()['events'][7]['status']);
$tests['vfs uri open lock byte current source next100 exclusive repeat retains holder'] = static fn (TestRunner $t) => $t->same('exclusive', $main100()['events'][7]['next']['sources']['main-alias']['holders']['wp-import']);
$tests['vfs uri open lock byte current source next100 close main closed'] = static fn (TestRunner $t) => $t->same('closed', $main100()['events'][8]['status']);
$tests['vfs uri open lock byte current source next100 close main removes source'] = static fn (TestRunner $t) => $t->same(false, array_key_exists('main', $main100()['events'][8]['next']['sources']));
$tests['vfs uri open lock byte current source next100 close main selects alias'] = static fn (TestRunner $t) => $t->same('main-alias', $main100()['events'][8]['next']['selected_source']);
$tests['vfs uri open lock byte current source next100 close alias closed'] = static fn (TestRunner $t) => $t->same('closed', $main100()['events'][9]['status']);
$tests['vfs uri open lock byte current source next100 close alias releases import'] = static fn (TestRunner $t) => $t->same(['wp-import' => 'exclusive'], $main100()['events'][9]['result']['released']);
$tests['vfs uri open lock byte current source next100 final no sources'] = static fn (TestRunner $t) => $t->same(0, $main100()['next']['source_count']);
$tests['vfs uri open lock byte current source next100 final no holders'] = static fn (TestRunner $t) => $t->same(0, $main100()['next']['holder_count']);

$tests['vfs uri open lock byte current source next100 reopen preserves current source'] = static fn (TestRunner $t) => $t->same(2, $reopen100()['current']['source_count']);
$tests['vfs uri open lock byte current source next100 reopen status reopened'] = static fn (TestRunner $t) => $t->same('reopened', $reopen100()['events'][0]['status']);
$tests['vfs uri open lock byte current source next100 reopen increments open count'] = static fn (TestRunner $t) => $t->same(2, $reopen100()['events'][0]['next']['sources']['main-alias']['open_count']);
$tests['vfs uri open lock byte current source next100 reopen lock shared blocked by exclusive'] = static fn (TestRunner $t) => $t->same('blocked', $reopen100()['events'][1]['status']);
$tests['vfs uri open lock byte current source next100 reopen lock blocker names import'] = static fn (TestRunner $t) => $t->same(['wp-import:exclusive'], $reopen100()['events'][1]['result']['blocking']);
$tests['vfs uri open lock byte current source next100 reopen import exclusive retained'] = static fn (TestRunner $t) => $t->same('exclusive', $reopen100()['events'][1]['next']['sources']['main-alias']['holders']['wp-import']);
$tests['vfs uri open lock byte current source next100 reopen generation advanced'] = static fn (TestRunner $t) => $t->same(5, $reopen100()['events'][1]['next']['sources']['main-alias']['generation']);

$tests['vfs uri open lock byte current source next100 readonly open immutable'] = static fn (TestRunner $t) => $t->same(true, $readonly100()['events'][0]['next']['sources']['archive']['immutable']);
$tests['vfs uri open lock byte current source next100 readonly shared blocked by immutable'] = static fn (TestRunner $t) => $t->same('blocked', $readonly100()['events'][1]['status']);
$tests['vfs uri open lock byte current source next100 readonly shared reason immutable'] = static fn (TestRunner $t) => $t->same('immutable_uri_disables_lock_bytes', $readonly100()['events'][1]['result']['reason']);
$tests['vfs uri open lock byte current source next100 readonly reserved blocked'] = static fn (TestRunner $t) => $t->same('blocked', $readonly100()['events'][2]['status']);
$tests['vfs uri open lock byte current source next100 readonly reserved no holders'] = static fn (TestRunner $t) => $t->same([], $readonly100()['events'][2]['next']['sources']['archive']['holders']);

$tests['vfs uri open lock byte current source next100 nolock dependency'] = static fn (TestRunner $t) => $t->same(true, in_array('nolock-open', $nolock100()['dependencies'], true));
$tests['vfs uri open lock byte current source next100 nolock lock blocked'] = static fn (TestRunner $t) => $t->same('blocked', $nolock100()['events'][1]['status']);
$tests['vfs uri open lock byte current source next100 nolock lock reason'] = static fn (TestRunner $t) => $t->same('nolock_uri_disables_lock_bytes', $nolock100()['events'][1]['result']['reason']);

$tests['vfs uri open lock byte current source next100 busy open blocked'] = static fn (TestRunner $t) => $t->same('blocked', $busy100()['events'][0]['status']);
$tests['vfs uri open lock byte current source next100 busy dependency'] = static fn (TestRunner $t) => $t->same(true, in_array('busy-handler', $busy100()['dependencies'], true));
$tests['vfs uri open lock byte current source next100 busy lock blocked source not open'] = static fn (TestRunner $t) => $t->same('blocked', $busy100()['events'][1]['status']);
$tests['vfs uri open lock byte current source next100 busy lock reason'] = static fn (TestRunner $t) => $t->same('source_is_not_open', $busy100()['events'][1]['result']['reason']);

$tests['vfs uri open lock byte current source next100 string open works'] = static fn (TestRunner $t) => $t->same('opened', $run100(['open main /srv/www/wp-content/database/plain.sqlite wp'])['events'][0]['status']);
$tests['vfs uri open lock byte current source next100 string lock works'] = static fn (TestRunner $t) => $t->same('planned', $run100(['open main /srv/www/wp-content/database/plain.sqlite wp', 'lock main shared wp 1'])['events'][1]['status']);
$tests['vfs uri open lock byte current source next100 string close works'] = static fn (TestRunner $t) => $t->same('closed', $run100(['open main /srv/www/wp-content/database/plain.sqlite wp', 'close main wp'])['events'][1]['status']);
$tests['vfs uri open lock byte current source next100 close decrements reopened source'] = static fn (TestRunner $t) => $t->same('decremented', $run100(['open main /srv/www/wp-content/database/plain.sqlite wp', 'open main /srv/www/wp-content/database/plain.sqlite wp', 'close main wp'])['events'][2]['status']);
$tests['vfs uri open lock byte current source next100 close decremented open count'] = static fn (TestRunner $t) => $t->same(1, $run100(['open main /srv/www/wp-content/database/plain.sqlite wp', 'open main /srv/www/wp-content/database/plain.sqlite wp', 'close main wp'])['events'][2]['next']['sources']['main']['open_count']);

$tests['vfs uri open lock byte current source next100 rejects empty operations'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run100([]));
$tests['vfs uri open lock byte current source next100 rejects unknown string'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run100(['checkpoint main']));
$tests['vfs uri open lock byte current source next100 rejects missing source'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run100([['kind' => 'open', 'filename' => '/tmp/a.sqlite']]));
$tests['vfs uri open lock byte current source next100 rejects bad lock source'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run100([['kind' => 'lock', 'source' => 'missing', 'level' => 'shared', 'connection' => 'wp']]));
$tests['vfs uri open lock byte current source next100 rejects bad shared slot'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run100([['kind' => 'open', 'source' => 'main', 'filename' => '/tmp/a.sqlite'], ['kind' => 'lock', 'source' => 'main', 'level' => 'shared', 'connection' => 'wp', 'shared_slot' => 510]]));
$tests['vfs uri open lock byte current source next100 rejects close missing'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run100([['kind' => 'close', 'source' => 'missing']]));
$tests['vfs uri open lock byte current source next100 rejects bad uri authority'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run100([['kind' => 'open', 'source' => 'main', 'filename' => 'file://example.com/tmp/a.sqlite?mode=rw']]));

return $tests;
