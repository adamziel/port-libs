<?php

declare(strict_types=1);

use PortLibs\Syncthing\Availability;
use PortLibs\Syncthing\DeviceActivity;

return [
    'maps upstream device activity least busy sequence' => static function (TestRunner $t): void {
        $n0 = new Availability('01020304', fromTemporary: false);
        $n1 = new Availability('05060708', fromTemporary: true);
        $n2 = new Availability('090a0b0c', fromTemporary: false);
        $devices = [$n0, $n1, $n2];
        $activity = new DeviceActivity();

        $t->same(0, $activity->leastBusyIndex($devices));
        $t->same(0, $activity->leastBusyIndex($devices));

        $lb = $activity->leastBusyIndex($devices);
        $activity->using($devices[$lb]);
        $t->same(1, $activity->leastBusyIndex($devices));

        $lb = $activity->leastBusyIndex($devices);
        $activity->using($devices[$lb]);
        $t->same(2, $activity->leastBusyIndex($devices));

        $lb = $activity->leastBusyIndex($devices);
        $activity->using($devices[$lb]);
        $t->same(0, $activity->leastBusyIndex($devices));

        $activity->done($n1);
        $t->same(1, $activity->leastBusyIndex($devices));

        $activity->done($n2);
        $t->same(1, $activity->leastBusyIndex($devices));

        $activity->done($n0);
        $t->same(0, $activity->leastBusyIndex($devices));
    },
    'balances wordpress media block plans across full and temporary peers' => static function (TestRunner $t): void {
        $activity = new DeviceActivity();

        $fullPeer = new Availability('site-cdn-peer', fromTemporary: false);
        $temporaryPeer = new Availability('editor-laptop-peer', fromTemporary: true);
        $offlinePeer = new Availability('offline-peer', fromTemporary: false);

        $t->same(0, $activity->leastBusyIndex([$fullPeer, $temporaryPeer]));
        $activity->using($fullPeer);
        $t->same(1, $activity->leastBusyIndex([$fullPeer, $temporaryPeer]));
        $activity->using($temporaryPeer);
        $t->same(0, $activity->leastBusyIndex([$fullPeer, $temporaryPeer]));

        $activity->done($fullPeer);
        $t->same(0, $activity->leastBusyIndex([$fullPeer, $temporaryPeer]));
        $t->same(0, $activity->usage('site-cdn-peer'));
        $t->same(1, $activity->usage('editor-laptop-peer'));

        $t->same(null, $activity->leastBusy([]));
        $t->same(-1, $activity->leastBusyIndex([]));
        $t->throws(InvalidArgumentException::class, static fn () => $activity->leastBusyIndex([$offlinePeer, 'not-availability']));
        $t->throws(InvalidArgumentException::class, static fn () => $activity->usage(''));
    },
];
