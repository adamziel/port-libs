<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsOpenLockFileControlCurrentSource;

$run109 = static fn (array $ops, array $options = []): array => SQLiteVfsOpenLockFileControlCurrentSource::planSqliteUriFileControls($ops, $options + [
    'filename' => 'file:/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=shared&vfs=unix',
    'device_flags' => ['powersafe_overwrite', 'safe_append'],
    'sector_size' => 4096,
]);

$sqliteUri = static function () use ($run109): array {
    static $result = null;
    if ($result === null) {
        $result = $run109([
            ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=shared&vfs=unix&flag=yes&flag=off&truth=TrUe&numeric=2abc&zero=0abc&invalid=maybe&empty&timeout=2500&badint=soon&negative=-7&role=import'],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'flag', 'default' => true]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'truth', 'default' => false]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'numeric', 'default' => false]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'zero', 'default' => true]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'invalid', 'default' => true]],
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'missing_bool', 'default' => true]],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'timeout', 'default' => 99]],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'badint', 'default' => 99]],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'negative', 'default' => 99]],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'missing_int', 'default' => 99]],
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'empty'],
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => 'role'],
            'lock(reserved)',
            'file_control(persist_wal, 1)',
            ['op' => 'open', 'filename' => 'file://localhost/srv/www/wp-content/database/wp%20uri.sqlite?mode=rw&cache=private&vfs=unix-dotfile&flag=on&timeout=soon&role=repair'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_boolean', 'value' => ['parameter' => 'flag', 'default' => false]],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'uri_int', 'value' => ['parameter' => 'timeout', 'default' => 123]],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'data_version'],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
            ['op' => 'filecontrol', 'handle' => 'db-2', 'control' => 'reserve_bytes', 'value' => 32],
            ['op' => 'filecontrol', 'handle' => 'db-1', 'control' => 'data_version'],
        ]);
    }

    return $result;
};

$defaults = static fn (): array => $run109([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/defaults.sqlite?mode=rw'],
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => 'missing_bool'],
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => 'missing_int'],
]);

$badDefaults = static fn (): array => $run109([
    ['op' => 'open', 'filename' => 'file:/srv/www/wp-content/database/bad-default.sqlite?mode=rw'],
    ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'missing_int', 'default' => 'soon']],
]);

return [
    'vfs open lock filecontrol uri current source next109 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-open-lock-filecontrol-uri-current-source-next109', $sqliteUri()['dependencies'], true)),
    'vfs open lock filecontrol uri current source next109 sqlite helper dependency' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite3-uri-helper-semantics', $sqliteUri()['dependencies'], true)),
    'vfs open lock filecontrol uri current source next109 final status ok' => static fn (TestRunner $t) => $t->same('ok', $sqliteUri()['status']),
    'vfs open lock filecontrol uri current source next109 decoded path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp uri.sqlite', $sqliteUri()['events'][0]['source_key']),
    'vfs open lock filecontrol uri current source next109 shared cache flag' => static fn (TestRunner $t) => $t->same('shared', $sqliteUri()['events'][0]['uri']['cache']),
    'vfs open lock filecontrol uri current source next109 first xopen sharedcache' => static fn (TestRunner $t) => $t->same(true, in_array('sharedcache', $sqliteUri()['events'][0]['xopen_flags'], true)),
    'vfs open lock filecontrol uri current source next109 duplicate bool uses last value' => static fn (TestRunner $t) => $t->same(false, $sqliteUri()['events'][1]['value']),
    'vfs open lock filecontrol uri current source next109 duplicate bool values preserved' => static fn (TestRunner $t) => $t->same(['yes', 'off'], $sqliteUri()['events'][1]['values']),
    'vfs open lock filecontrol uri current source next109 duplicate bool default recorded' => static fn (TestRunner $t) => $t->same(true, $sqliteUri()['events'][1]['default']),
    'vfs open lock filecontrol uri current source next109 true text accepted' => static fn (TestRunner $t) => $t->same(true, $sqliteUri()['events'][2]['value']),
    'vfs open lock filecontrol uri current source next109 nonzero numeric prefix accepted' => static fn (TestRunner $t) => $t->same(true, $sqliteUri()['events'][3]['value']),
    'vfs open lock filecontrol uri current source next109 zero numeric prefix false' => static fn (TestRunner $t) => $t->same(false, $sqliteUri()['events'][4]['value']),
    'vfs open lock filecontrol uri current source next109 invalid bool returns default' => static fn (TestRunner $t) => $t->same(true, $sqliteUri()['events'][5]['value']),
    'vfs open lock filecontrol uri current source next109 missing bool returns default' => static fn (TestRunner $t) => $t->same(true, $sqliteUri()['events'][6]['value']),
    'vfs open lock filecontrol uri current source next109 missing bool reason' => static fn (TestRunner $t) => $t->same('missing_uri_parameter', $sqliteUri()['events'][6]['reason']),
    'vfs open lock filecontrol uri current source next109 int value parsed' => static fn (TestRunner $t) => $t->same(2500, $sqliteUri()['events'][7]['value']),
    'vfs open lock filecontrol uri current source next109 invalid int returns zero' => static fn (TestRunner $t) => $t->same(0, $sqliteUri()['events'][8]['value']),
    'vfs open lock filecontrol uri current source next109 negative int parsed' => static fn (TestRunner $t) => $t->same(-7, $sqliteUri()['events'][9]['value']),
    'vfs open lock filecontrol uri current source next109 missing int returns default' => static fn (TestRunner $t) => $t->same(99, $sqliteUri()['events'][10]['value']),
    'vfs open lock filecontrol uri current source next109 no-value parameter empty string' => static fn (TestRunner $t) => $t->same('', $sqliteUri()['events'][11]['value']),
    'vfs open lock filecontrol uri current source next109 role parameter' => static fn (TestRunner $t) => $t->same('import', $sqliteUri()['events'][12]['value']),
    'vfs open lock filecontrol uri current source next109 uri bool does not bump generation' => static fn (TestRunner $t) => $t->same(1, $sqliteUri()['events'][12]['source_generation']),
    'vfs open lock filecontrol uri current source next109 lock reserved ok' => static fn (TestRunner $t) => $t->same('ok', $sqliteUri()['events'][13]['status']),
    'vfs open lock filecontrol uri current source next109 persist wal bumps generation' => static fn (TestRunner $t) => $t->same(2, $sqliteUri()['events'][14]['source_generation']),
    'vfs open lock filecontrol uri current source next109 second open same source' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp uri.sqlite', $sqliteUri()['events'][15]['source_key']),
    'vfs open lock filecontrol uri current source next109 second open private cache' => static fn (TestRunner $t) => $t->same('private', $sqliteUri()['events'][15]['uri']['cache']),
    'vfs open lock filecontrol uri current source next109 second open current generation' => static fn (TestRunner $t) => $t->same(2, $sqliteUri()['events'][15]['next']['handles']['db-2']['source_generation']),
    'vfs open lock filecontrol uri current source next109 second bool on true' => static fn (TestRunner $t) => $t->same(true, $sqliteUri()['events'][16]['value']),
    'vfs open lock filecontrol uri current source next109 second invalid int zero' => static fn (TestRunner $t) => $t->same(0, $sqliteUri()['events'][17]['value']),
    'vfs open lock filecontrol uri current source next109 second data version fresh' => static fn (TestRunner $t) => $t->same(false, $sqliteUri()['events'][18]['stale_current_source']),
    'vfs open lock filecontrol uri current source next109 first data version fresh before sibling write' => static fn (TestRunner $t) => $t->same(false, $sqliteUri()['events'][19]['stale_current_source']),
    'vfs open lock filecontrol uri current source next109 reserve bytes ok' => static fn (TestRunner $t) => $t->same('ok', $sqliteUri()['events'][20]['status']),
    'vfs open lock filecontrol uri current source next109 reserve bytes bumps generation' => static fn (TestRunner $t) => $t->same(3, $sqliteUri()['events'][20]['source_generation']),
    'vfs open lock filecontrol uri current source next109 first stale after sibling write' => static fn (TestRunner $t) => $t->same(true, $sqliteUri()['events'][21]['stale_current_source']),
    'vfs open lock filecontrol uri current source next109 final generation count' => static fn (TestRunner $t) => $t->same(1, $sqliteUri()['next']['persistent_generation_count']),
    'vfs open lock filecontrol uri current source next109 final open count two' => static fn (TestRunner $t) => $t->same(2, $sqliteUri()['next']['open_count']),
    'vfs open lock filecontrol uri current source next109 missing bool default false without array' => static fn (TestRunner $t) => $t->same(false, $defaults()['events'][1]['value']),
    'vfs open lock filecontrol uri current source next109 missing int default zero without array' => static fn (TestRunner $t) => $t->same(0, $defaults()['events'][2]['value']),
    'vfs open lock filecontrol uri current source next109 rejects bad int default' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, $badDefaults),
    'vfs open lock filecontrol uri current source next109 rejects empty parameter array' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run109([['op' => 'open'], ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => '']]])),
    'vfs open lock filecontrol uri current source next109 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsOpenLockFileControlCurrentSource::planSqliteUriFileControls([])),
];
