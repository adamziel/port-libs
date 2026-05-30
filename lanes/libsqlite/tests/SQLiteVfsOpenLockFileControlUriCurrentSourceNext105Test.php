<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run105 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planUriFileControls($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix&psow=1&application_role=import&busy_timeout=2500',
    'device_flags' => ['powersafe_overwrite', 'safe_append'],
    'sector_size' => 4096,
]);

$source = static function () use ($run105): array {
    static $result = null;
    if ($result === null) {
        $result = $run105([
            'open(file:/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=shared&vfs=unix&psow=1&application_role=import&busy_timeout=2500&tag=alpha&tag=beta)',
            'file_control(uri_parameter, application_role)',
            'file_control(uri_int, busy_timeout)',
            'file_control(uri_boolean, psow)',
            'lock(reserved)',
            'file_control(persist_wal, 1)',
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'uri_parameter', 'value' => 'tag'],
            ['op' => 'open', 'filename' => 'file://localhost/srv/www/wp-content/database/wp%20copy.sqlite?mode=rw&cache=private&vfs=unix-dotfile&psow=0&application_role=repair&busy_timeout=100&tag=gamma'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_parameter', 'value' => 'application_role'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_int', 'value' => 'busy_timeout'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_boolean', 'value' => 'psow'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'data_version'],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'reserve_bytes', 'value' => 32],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'uri_parameter', 'value' => 'missing'],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
        ]);
    }

    return $result;
};

$readonly = static fn (): array => $run105([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/archive.sqlite?mode=ro&immutable=1&cache=private&application_role=archive&busy_timeout=0'],
    'file_control(uri_parameter, application_role)',
    'file_control(uri_int, busy_timeout)',
    'file_control(uri_boolean, immutable)',
    'lock(reserved)',
]);

$plain = static fn (): array => $run105([
    ['op' => 'open', 'filename' => '/srv/www/wp-content/database/plain.sqlite'],
    'file_control(uri_parameter, application_role)',
    'file_control(uri_boolean, psow)',
]);

$memory = static fn (): array => $run105([
    ['op' => 'open', 'filename' => 'file::memory:?cache=shared&mode=memory&application_role=scratch&busy_timeout=5'],
    'file_control(uri_parameter, application_role)',
    'file_control(uri_int, busy_timeout)',
    'file_control(uri_boolean, psow)',
]);

$badBool = static fn (): array => $run105([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/bad.sqlite?psow=yes'],
    'file_control(uri_boolean, psow)',
]);

$badInt = static fn (): array => $run105([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/bad.sqlite?busy_timeout=soon'],
    'file_control(uri_int, busy_timeout)',
]);

return [
    'vfs open lock filecontrol uri current source next105 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-lock-filecontrol-uri-current-source-next105', $source()['dependencies'], true)),
    'vfs open lock filecontrol uri current source next105 uri dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-uri-file-control', $source()['dependencies'], true)),
    'vfs open lock filecontrol uri current source next105 final status ok' => static fn (TestRunner $t) => $t->same('ok', $source()['status']),
    'vfs open lock filecontrol uri current source next105 decoded source key' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $source()['events'][0]['source_key']),
    'vfs open lock filecontrol uri current source next105 first cache shared' => static fn (TestRunner $t) => $t->same('shared', $source()['events'][0]['uri']['cache']),
    'vfs open lock filecontrol uri current source next105 first vfs unix' => static fn (TestRunner $t) => $t->same('unix', $source()['events'][0]['uri']['vfs']),
    'vfs open lock filecontrol uri current source next105 first xopen sharedcache' => static fn (TestRunner $t) => $t->same(true, in_array('sharedcache', $source()['events'][0]['xopen_flags'], true)),
    'vfs open lock filecontrol uri current source next105 uri text value' => static fn (TestRunner $t) => $t->same('import', $source()['events'][1]['value']),
    'vfs open lock filecontrol uri current source next105 uri text values' => static fn (TestRunner $t) => $t->same(['import'], $source()['events'][1]['values']),
    'vfs open lock filecontrol uri current source next105 uri text parameter' => static fn (TestRunner $t) => $t->same('application_role', $source()['events'][1]['parameter']),
    'vfs open lock filecontrol uri current source next105 uri text no generation bump' => static fn (TestRunner $t) => $t->same(1, $source()['events'][1]['source_generation']),
    'vfs open lock filecontrol uri current source next105 uri int value' => static fn (TestRunner $t) => $t->same(2500, $source()['events'][2]['value']),
    'vfs open lock filecontrol uri current source next105 uri int raw values' => static fn (TestRunner $t) => $t->same(['2500'], $source()['events'][2]['values']),
    'vfs open lock filecontrol uri current source next105 uri bool true' => static fn (TestRunner $t) => $t->same(true, $source()['events'][3]['value']),
    'vfs open lock filecontrol uri current source next105 reserved lock ok' => static fn (TestRunner $t) => $t->same('ok', $source()['events'][4]['status']),
    'vfs open lock filecontrol uri current source next105 persist wal ok' => static fn (TestRunner $t) => $t->same('ok', $source()['events'][5]['status']),
    'vfs open lock filecontrol uri current source next105 persist wal bumps generation' => static fn (TestRunner $t) => $t->same(2, $source()['events'][5]['source_generation']),
    'vfs open lock filecontrol uri current source next105 duplicate last tag value' => static fn (TestRunner $t) => $t->same('beta', $source()['events'][6]['value']),
    'vfs open lock filecontrol uri current source next105 duplicate tag values' => static fn (TestRunner $t) => $t->same(['alpha', 'beta'], $source()['events'][6]['values']),
    'vfs open lock filecontrol uri current source next105 second open same source key' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp copy.sqlite', $source()['events'][7]['source_key']),
    'vfs open lock filecontrol uri current source next105 second open private cache' => static fn (TestRunner $t) => $t->same('private', $source()['events'][7]['uri']['cache']),
    'vfs open lock filecontrol uri current source next105 second open vfs differs' => static fn (TestRunner $t) => $t->same('unix-dotfile', $source()['events'][7]['uri']['vfs']),
    'vfs open lock filecontrol uri current source next105 second open generation current' => static fn (TestRunner $t) => $t->same(2, $source()['events'][7]['next']['handles']['db-2']['source_generation']),
    'vfs open lock filecontrol uri current source next105 second role value' => static fn (TestRunner $t) => $t->same('repair', $source()['events'][8]['value']),
    'vfs open lock filecontrol uri current source next105 second int value' => static fn (TestRunner $t) => $t->same(100, $source()['events'][9]['value']),
    'vfs open lock filecontrol uri current source next105 second bool false' => static fn (TestRunner $t) => $t->same(false, $source()['events'][10]['value']),
    'vfs open lock filecontrol uri current source next105 second data version fresh' => static fn (TestRunner $t) => $t->same(false, $source()['events'][11]['stale_current_source']),
    'vfs open lock filecontrol uri current source next105 first data version fresh after own write' => static fn (TestRunner $t) => $t->same(false, $source()['events'][12]['stale_current_source']),
    'vfs open lock filecontrol uri current source next105 sibling reserve uses reused lock' => static fn (TestRunner $t) => $t->same('ok', $source()['events'][13]['status']),
    'vfs open lock filecontrol uri current source next105 sibling reserve bumps generation' => static fn (TestRunner $t) => $t->same(3, $source()['events'][13]['source_generation']),
    'vfs open lock filecontrol uri current source next105 missing parameter null' => static fn (TestRunner $t) => $t->same(null, $source()['events'][14]['value']),
    'vfs open lock filecontrol uri current source next105 missing reason' => static fn (TestRunner $t) => $t->same('missing_uri_parameter', $source()['events'][14]['reason']),
    'vfs open lock filecontrol uri current source next105 missing values empty' => static fn (TestRunner $t) => $t->same([], $source()['events'][14]['values']),
    'vfs open lock filecontrol uri current source next105 first data version sees sibling write' => static fn (TestRunner $t) => $t->same(3, $source()['events'][15]['value']),
    'vfs open lock filecontrol uri current source next105 first handle stale after sibling write' => static fn (TestRunner $t) => $t->same(true, $source()['events'][15]['stale_current_source']),
    'vfs open lock filecontrol uri current source next105 final open count' => static fn (TestRunner $t) => $t->same(2, $source()['next']['open_count']),
    'vfs open lock filecontrol uri current source next105 final generation count' => static fn (TestRunner $t) => $t->same(1, $source()['next']['persistent_generation_count']),

    'vfs open lock filecontrol uri current source next105 readonly role' => static fn (TestRunner $t) => $t->same('archive', $readonly()['events'][1]['value']),
    'vfs open lock filecontrol uri current source next105 readonly busy timeout zero' => static fn (TestRunner $t) => $t->same(0, $readonly()['events'][2]['value']),
    'vfs open lock filecontrol uri current source next105 readonly immutable bool' => static fn (TestRunner $t) => $t->same(true, $readonly()['events'][3]['value']),
    'vfs open lock filecontrol uri current source next105 readonly lock blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][4]['status']),

    'vfs open lock filecontrol uri current source next105 plain missing role' => static fn (TestRunner $t) => $t->same(null, $plain()['events'][1]['value']),
    'vfs open lock filecontrol uri current source next105 plain missing psow' => static fn (TestRunner $t) => $t->same(null, $plain()['events'][2]['value']),
    'vfs open lock filecontrol uri current source next105 plain source persistent' => static fn (TestRunner $t) => $t->same(1, $plain()['next']['open_count']),

    'vfs open lock filecontrol uri current source next105 memory role' => static fn (TestRunner $t) => $t->same('scratch', $memory()['events'][1]['value']),
    'vfs open lock filecontrol uri current source next105 memory int' => static fn (TestRunner $t) => $t->same(5, $memory()['events'][2]['value']),
    'vfs open lock filecontrol uri current source next105 memory missing psow' => static fn (TestRunner $t) => $t->same(null, $memory()['events'][3]['value']),
    'vfs open lock filecontrol uri current source next105 memory no persistent generations' => static fn (TestRunner $t) => $t->same(0, $memory()['next']['persistent_generation_count']),

    'vfs open lock filecontrol uri current source next105 rejects bad boolean' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badBool),
    'vfs open lock filecontrol uri current source next105 rejects bad int' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badInt),
    'vfs open lock filecontrol uri current source next105 rejects empty uri parameter' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run105([['op' => 'open'], ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => '']])),
    'vfs open lock filecontrol uri current source next105 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenLockFileControlCurrentSource::planUriFileControls([])),
];
