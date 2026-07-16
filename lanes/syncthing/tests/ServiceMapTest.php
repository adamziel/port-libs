<?php

declare(strict_types=1);

use PortLibs\Syncthing\ServiceMap;

return [
    'maps upstream service map add remove lifecycle' => static function (TestRunner $t): void {
        $events = [];
        $map = syncthing_service_map_with_events($events);
        $d1 = new SyncthingServiceMapDummyService('d1');
        $d2 = new SyncthingServiceMapDummyService('d2');

        $map->add('d1', $d1);
        $map->add('d2', $d2);

        $t->same(['start:d1', 'start:d2'], $events);
        $t->same($d1, $map->get('d1'));
        $t->same($d2, $map->get('d2'));
        $t->same(['d1', 'd2'], $map->runningKeys());

        $t->true($map->remove('d1'));
        $t->true($map->remove('d2'));
        $t->same(['start:d1', 'start:d2', 'stop:d1', 'stop:d2'], $events);
        $t->same(0, $map->count());
        $t->same(null, $map->get('d1'));
        $t->true(!$map->remove('d1'));
    },
    'maps upstream service map overwrite stop before replace' => static function (TestRunner $t): void {
        $events = [];
        $map = syncthing_service_map_with_events($events);
        $old = new SyncthingServiceMapDummyService('old-indexer');
        $new = new SyncthingServiceMapDummyService('new-indexer');

        $map->add('folder', $old);
        $map->add('folder', $new);

        $t->same(['start:old-indexer', 'stop:old-indexer', 'start:new-indexer'], $events);
        $t->same($new, $map->get('folder'));
        $t->same(['folder'], $map->keys());
        $t->true($map->remove('folder'));
        $t->same(['start:old-indexer', 'stop:old-indexer', 'start:new-indexer', 'stop:new-indexer'], $events);
    },
    'maps upstream stop retention remove and wait boundaries' => static function (TestRunner $t): void {
        $events = [];
        $map = syncthing_service_map_with_events($events);
        $service = new SyncthingServiceMapDummyService('media-watcher');

        $map->add('wordpress-media', $service);
        $map->stop('wordpress-media');

        $t->same(['start:media-watcher', 'stop:media-watcher'], $events);
        $t->same($service, $map->get('wordpress-media'), 'Stop retains the service in the map like upstream');
        $t->true(!$map->isRunning('wordpress-media'));
        $t->same(null, $map->stopAndWait('wordpress-media'));
        $t->true($map->remove('wordpress-media'));

        $notFound = $map->removeAndWait('wordpress-media');
        $t->true($notFound instanceof RuntimeException);
        $t->same(ServiceMap::ERR_SERVICE_NOT_FOUND, $notFound->getMessage());
    },
    'maps upstream iteration with remove and wait' => static function (TestRunner $t): void {
        $events = [];
        $map = syncthing_service_map_with_events($events);
        foreach (['keep1', 'remove2', 'keep3', 'remove4'] as $name) {
            $map->add($name, new SyncthingServiceMapDummyService($name));
        }

        $visited = [];
        $error = $map->each(static function (string|int $key, SyncthingServiceMapDummyService $service) use ($map, &$visited): ?Throwable {
            $visited[] = $service->name;
            if (str_starts_with((string) $key, 'remove')) {
                return $map->removeAndWait($key);
            }

            return null;
        });

        $t->same(null, $error);
        $t->same(['keep1', 'remove2', 'keep3', 'remove4'], $visited);
        $t->same(['keep1', 'keep3'], $map->keys());
        $t->same(null, $map->get('remove2'));
        $t->true($map->isRunning('keep1'));
        $t->true($map->isRunning('keep3'));
        $t->same([
            'start:keep1',
            'start:remove2',
            'start:keep3',
            'start:remove4',
            'stop:remove2',
            'stop:remove4',
        ], $events);
    },
    'manages wordpress folder services without dropping retained state' => static function (TestRunner $t): void {
        $events = [];
        $map = syncthing_service_map_with_events($events);

        $map->add('wordpress-media', new SyncthingServiceMapDummyService('media-indexer'));
        $map->add('wordpress-private', new SyncthingServiceMapDummyService('receive-encrypted-indexer'));
        $map->stop('wordpress-private');
        $map->add('wordpress-media', new SyncthingServiceMapDummyService('media-indexer-rescan'));

        $t->same(['wordpress-media', 'wordpress-private'], $map->keys());
        $t->same(['wordpress-media'], $map->runningKeys());
        $t->same('receive-encrypted-indexer', $map->get('wordpress-private')->name);

        $t->same(null, $map->removeAndWait('wordpress-private'));
        $t->same(['wordpress-media'], $map->keys());
        $t->same([
            'start:media-indexer',
            'start:receive-encrypted-indexer',
            'stop:receive-encrypted-indexer',
            'stop:media-indexer',
            'start:media-indexer-rescan',
        ], $events);
    },
    'propagates service map callback errors like upstream each errors' => static function (TestRunner $t): void {
        $map = new ServiceMap();
        $map->add('a', 'alpha');
        $map->add('b', 'beta');
        $error = new RuntimeException('stop iteration');

        $visited = [];
        $returned = $map->each(static function (string|int $key, string $service) use (&$visited, $error): ?Throwable {
            $visited[] = $key . ':' . $service;

            return $key === 'a' ? $error : null;
        });

        $t->same($error, $returned);
        $t->same(['a:alpha'], $visited);
        $t->throws(InvalidArgumentException::class, static fn () => new ServiceMap('not-callable'));
    },
];

/**
 * @param list<string> $events
 */
function syncthing_service_map_with_events(array &$events): ServiceMap
{
    return new ServiceMap(
        static function (string|int $key, SyncthingServiceMapDummyService $service) use (&$events): void {
            $events[] = 'start:' . $service->name;
            $service->started = true;
        },
        static function (string|int $key, SyncthingServiceMapDummyService $service) use (&$events): void {
            $events[] = 'stop:' . $service->name;
            $service->stopped = true;
        },
    );
}

final class SyncthingServiceMapDummyService
{
    public bool $started = false;
    public bool $stopped = false;

    public function __construct(public readonly string $name)
    {
    }
}
