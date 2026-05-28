<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteLockByteRangePlan;

$tests = [];

$path = '/srv/www/wp-content/database/.ht.sqlite';

$transitions = [
    'none to shared acquires selected shared slot' => ['none', 'shared', 4, 4, 0, 1, 0, 'shared', 1073741830],
    'shared to reserved retains shared slot and acquires reserved byte' => ['shared', 'reserved', 4, 4, 1, 1, 0, 'reserved', 1073741825],
    'reserved to pending releases shared and retains reserved' => ['reserved', 'pending', 4, 4, 1, 1, 1, 'pending', 1073741824],
    'pending to exclusive retains pending and reserved and acquires shared range' => ['pending', 'exclusive', 4, 4, 2, 1, 0, 'shared', 1073741826],
    'exclusive to none releases all lock bytes' => ['exclusive', 'none', 4, 4, 0, 0, 3, null, null],
    'shared slot move releases old reader slot and acquires new slot' => ['shared', 'shared', 2, 8, 0, 1, 1, 'shared', 1073741834],
    'reserved slot move keeps reserved but swaps reader slot' => ['reserved', 'reserved', 2, 8, 1, 1, 1, 'shared', 1073741834],
    'reserved to exclusive keeps reserved and acquires pending plus shared range' => ['reserved', 'exclusive', 2, 2, 1, 2, 1, 'pending', 1073741824],
    'exclusive to pending keeps pending and reserved and releases shared range' => ['exclusive', 'pending', 2, 2, 2, 0, 1, null, null],
    'pending to reserved keeps reserved and releases pending' => ['pending', 'reserved', 2, 2, 1, 1, 1, 'shared', 1073741828],
];

foreach ($transitions as $name => [$current, $next, $currentSlot, $nextSlot, $retain, $acquire, $release, $firstAcquireName, $firstAcquireOffset]) {
    $tests['vfs lock byte range current next72 ' . $name] = static function (TestRunner $t) use ($path, $current, $next, $currentSlot, $nextSlot, $retain, $acquire, $release, $firstAcquireName, $firstAcquireOffset): void {
        $plan = SQLiteLockByteRangePlan::transition($path, $current, $next, false, 'wp-admin-import', $currentSlot, $nextSlot);

        $t->same('planned', $plan['status']);
        $t->same(true, $plan['can_transition']);
        $t->same($path, $plan['path']);
        $t->same('wp-admin-import', $plan['connection']);
        $t->same($current, $plan['current']);
        $t->same($next, $plan['next']);
        $t->same($retain, count($plan['retain']));
        $t->same($acquire, count($plan['acquire']));
        $t->same($release, count($plan['release']));
        $t->same(true, in_array('sqlite-lock-byte-range-current-next', $plan['dependencies'], true));
        $t->same(null, $plan['reason']);

        if ($firstAcquireName !== null) {
            $t->same($firstAcquireName, $plan['acquire'][0]['name']);
            $t->same($firstAcquireOffset, $plan['acquire'][0]['offset']);
        }
    };
}

$tests['vfs lock byte range current next72 preserves reserved byte while promoting to exclusive'] = static function (TestRunner $t) use ($path): void {
    $plan = SQLiteLockByteRangePlan::transition($path, 'reserved', 'exclusive', false, 'wp-admin-import', 11);

    $t->same([['name' => 'reserved', 'offset' => 1073741825, 'length' => 1, 'mode' => 'exclusive']], $plan['retain']);
    $t->same('pending', $plan['acquire'][0]['name']);
    $t->same('shared', $plan['acquire'][1]['name']);
    $t->same('shared', $plan['release'][0]['name']);
    $t->same(1073741837, $plan['release'][0]['offset']);
};

$tests['vfs lock byte range current next72 release plan keeps connection optional for none'] = static function (TestRunner $t) use ($path): void {
    $plan = SQLiteLockByteRangePlan::transition($path, 'shared', 'none', false, null, 19);

    $t->same('planned', $plan['status']);
    $t->same(null, $plan['connection']);
    $t->same([], $plan['next_ranges']);
    $t->same([], $plan['acquire']);
    $t->same(1, count($plan['release']));
    $t->same(1073741845, $plan['release'][0]['offset']);
};

$tests['vfs lock byte range current next72 nolock blocks acquisition but not release'] = static function (TestRunner $t) use ($path): void {
    $blocked = SQLiteLockByteRangePlan::transition($path, 'none', 'shared', true, 'wp-repair', 1);
    $release = SQLiteLockByteRangePlan::transition($path, 'reserved', 'none', true, null, 1);

    $t->same('blocked', $blocked['status']);
    $t->same(false, $blocked['can_transition']);
    $t->same(true, $blocked['nolock']);
    $t->same([], $blocked['acquire']);
    $t->same('nolock VFS disables POSIX byte-range locking', $blocked['reason']);
    $t->same(['sqlite-lock-byte-range', 'vfs-file-lock', 'nolock-open', 'sqlite-lock-byte-range-current-next'], $blocked['dependencies']);

    $t->same('planned', $release['status']);
    $t->same(true, $release['can_transition']);
    $t->same([], $release['acquire']);
    $t->same(2, count($release['release']));
};

$tests['vfs lock byte range current next72 validates next writer connection'] = static function (TestRunner $t) use ($path): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLockByteRangePlan::transition($path, 'none', 'shared'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLockByteRangePlan::transition($path, 'shared', 'exclusive', false, 'wp', -1));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteLockByteRangePlan::transition($path, 'shared', 'exclusive', false, 'wp', 0, 510));
};

return $tests;
