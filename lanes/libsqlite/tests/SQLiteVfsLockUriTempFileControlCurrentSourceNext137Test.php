<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsLockUriTempFileControlCurrentSourceNextPlan;

$run137 = static fn (array $ops, array $options = []): array => SQLiteVfsLockUriTempFileControlCurrentSourceNextPlan::run($ops, $options + [
    'temp_directory' => '/srv/www/wp-content/uploads/sqlite-tmp',
]);

$import = static function () use ($run137): array {
    static $result = null;
    if ($result === null) {
        $result = $run137([
            'open(main, file://localhost/srv/www/wp-content/database/wp.sqlite?mode=rw&cache=shared&immutable=0&psow=1)',
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'psow', 'default' => false]],
            ['op' => 'filecontrol', 'control' => 'temp_directory', 'value' => '/srv/www/wp-content/uploads/import-tmp'],
            'open(temp, file:/wp-import-stage?mode=memory&cache=private&tempdir=/srv/www/wp-content/uploads/request-tmp&scratch=17)',
            ['op' => 'filecontrol', 'control' => 'uri_parameter', 'value' => ['parameter' => 'scratch', 'default' => '0']],
            ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'scratch', 'default' => 0]],
            'lock(shared, wp-import)',
            'lock(reserved, wp-cron)',
            'lock(reserved, wp-import)',
            'source(main)',
            ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'missing', 'default' => true]],
            'source(temp)',
            'close(temp)',
            'open(temp)',
            'lock(shared, wp-cron)',
        ]);
    }

    return $result;
};

$readonly = static fn (): array => $run137([
    'open(archive, file:/srv/www/wp-content/database/archive.sqlite?mode=ro&nolock=1&cache=private)',
    'lock(shared, wp-reader)',
    'lock(reserved, wp-reader)',
    ['op' => 'filecontrol', 'control' => 'uri_boolean', 'value' => ['parameter' => 'nolock', 'default' => false]],
]);

$directory = static fn (): array => $run137([
    'open(temp)',
    ['op' => 'filecontrol', 'control' => 'temp_directory', 'value' => '/tmp/wp-new'],
    'open(temp2)',
]);

return [
    'vfs lock uri temp filecontrol current source next137 dependency marker' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-lock-uri-temp-filecontrol-current-source-next137', $import()['dependencies'], true)),
    'vfs lock uri temp filecontrol current source next137 final status ok' => static fn (TestRunner $t) => $t->same('ok', $import()['status']),
    'vfs lock uri temp filecontrol current source next137 current starts empty' => static fn (TestRunner $t) => $t->same([], $import()['current']['handles']),
    'vfs lock uri temp filecontrol current source next137 initial temp directory' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/sqlite-tmp', $import()['current']['temp_directory']),
    'vfs lock uri temp filecontrol current source next137 main open status' => static fn (TestRunner $t) => $t->same('open', $import()['events'][0]['status']),
    'vfs lock uri temp filecontrol current source next137 main not temporary' => static fn (TestRunner $t) => $t->same(false, $import()['events'][0]['temporary']),
    'vfs lock uri temp filecontrol current source next137 main owner decoded' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/database/wp.sqlite', $import()['events'][0]['owner']),
    'vfs lock uri temp filecontrol current source next137 main current selected' => static fn (TestRunner $t) => $t->same('main', $import()['events'][0]['next']['current_source']),
    'vfs lock uri temp filecontrol current source next137 main uri cache captured' => static fn (TestRunner $t) => $t->same('shared', $import()['events'][0]['uri_parameters']['cache']),
    'vfs lock uri temp filecontrol current source next137 main uri psow captured' => static fn (TestRunner $t) => $t->same(true, $import()['events'][0]['uri_parameters']['psow']),
    'vfs lock uri temp filecontrol current source next137 uri boolean routes current source' => static fn (TestRunner $t) => $t->same('current-source-uri', $import()['events'][1]['routed_to']),
    'vfs lock uri temp filecontrol current source next137 uri boolean value true' => static fn (TestRunner $t) => $t->same(true, $import()['events'][1]['value']),
    'vfs lock uri temp filecontrol current source next137 temp directory changed' => static fn (TestRunner $t) => $t->same(true, $import()['events'][2]['changed']),
    'vfs lock uri temp filecontrol current source next137 temp directory previous' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/sqlite-tmp', $import()['events'][2]['previous']),
    'vfs lock uri temp filecontrol current source next137 temp directory next' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/import-tmp', $import()['events'][2]['next']['temp_directory']),
    'vfs lock uri temp filecontrol current source next137 existing handle stale after temp directory' => static fn (TestRunner $t) => $t->same(true, $import()['events'][2]['stale_current_source']),
    'vfs lock uri temp filecontrol current source next137 temp opens memory' => static fn (TestRunner $t) => $t->same('temp-open', $import()['events'][3]['status']),
    'vfs lock uri temp filecontrol current source next137 temp uses uri tempdir' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/request-tmp', $import()['events'][3]['temp_directory']),
    'vfs lock uri temp filecontrol current source next137 temp generated path' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/request-tmp/sqlite-temp-2.db', $import()['events'][3]['path']),
    'vfs lock uri temp filecontrol current source next137 temp owner handle scoped' => static fn (TestRunner $t) => $t->same('temp:temp:2', $import()['events'][3]['owner']),
    'vfs lock uri temp filecontrol current source next137 temp delete on close' => static fn (TestRunner $t) => $t->same(true, $import()['events'][3]['delete_on_close']),
    'vfs lock uri temp filecontrol current source next137 temp scratch parameter captured' => static fn (TestRunner $t) => $t->same('17', $import()['events'][3]['uri_parameters']['scratch']),
    'vfs lock uri temp filecontrol current source next137 uri parameter returns string' => static fn (TestRunner $t) => $t->same('17', $import()['events'][4]['value']),
    'vfs lock uri temp filecontrol current source next137 uri int returns integer' => static fn (TestRunner $t) => $t->same(17, $import()['events'][5]['value']),
    'vfs lock uri temp filecontrol current source next137 shared lock ok' => static fn (TestRunner $t) => $t->same('ok', $import()['events'][6]['status']),
    'vfs lock uri temp filecontrol current source next137 shared lock owner' => static fn (TestRunner $t) => $t->same('temp:temp:2', $import()['events'][6]['owner']),
    'vfs lock uri temp filecontrol current source next137 lock state stores shared' => static fn (TestRunner $t) => $t->same('wp-import', $import()['events'][6]['next']['owner_locks']['temp:temp:2']['shared']),
    'vfs lock uri temp filecontrol current source next137 reserved blocked by reader' => static fn (TestRunner $t) => $t->same('busy', $import()['events'][7]['status']),
    'vfs lock uri temp filecontrol current source next137 reserved blocker listed' => static fn (TestRunner $t) => $t->same(['wp-import:shared'], $import()['events'][7]['blocking']),
    'vfs lock uri temp filecontrol current source next137 reserved same owner ok' => static fn (TestRunner $t) => $t->same('ok', $import()['events'][8]['status']),
    'vfs lock uri temp filecontrol current source next137 source main status' => static fn (TestRunner $t) => $t->same('ok', $import()['events'][9]['status']),
    'vfs lock uri temp filecontrol current source next137 missing uri default true' => static fn (TestRunner $t) => $t->same(true, $import()['events'][10]['value']),
    'vfs lock uri temp filecontrol current source next137 missing uri reason' => static fn (TestRunner $t) => $t->same('missing_uri_parameter', $import()['events'][10]['reason']),
    'vfs lock uri temp filecontrol current source next137 source temp status' => static fn (TestRunner $t) => $t->same('ok', $import()['events'][11]['status']),
    'vfs lock uri temp filecontrol current source next137 close temp deleted' => static fn (TestRunner $t) => $t->same(true, $import()['events'][12]['deleted_temp']),
    'vfs lock uri temp filecontrol current source next137 close temp releases locks' => static fn (TestRunner $t) => $t->same(false, array_key_exists('temp:temp:2', $import()['events'][12]['next']['owner_locks'])),
    'vfs lock uri temp filecontrol current source next137 close temp records owner' => static fn (TestRunner $t) => $t->same(['temp:temp:2'], $import()['events'][12]['next']['deleted_temp_owners']),
    'vfs lock uri temp filecontrol current source next137 reopen temp uses changed directory' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/import-tmp', $import()['events'][13]['temp_directory']),
    'vfs lock uri temp filecontrol current source next137 reopen temp path regenerated' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/import-tmp/sqlite-temp-3.db', $import()['events'][13]['path']),
    'vfs lock uri temp filecontrol current source next137 reopen temp owner new' => static fn (TestRunner $t) => $t->same('temp:temp:3', $import()['events'][13]['owner']),
    'vfs lock uri temp filecontrol current source next137 reader after reopen ok' => static fn (TestRunner $t) => $t->same('ok', $import()['events'][14]['status']),
    'vfs lock uri temp filecontrol current source next137 final current temp' => static fn (TestRunner $t) => $t->same('temp', $import()['next']['current_source']),
    'vfs lock uri temp filecontrol current source next137 final temp open count' => static fn (TestRunner $t) => $t->same(1, $import()['next']['temp_open_count']),
    'vfs lock uri temp filecontrol current source next137 final lock owner count' => static fn (TestRunner $t) => $t->same(1, $import()['next']['lock_owner_count']),
    'vfs lock uri temp filecontrol current source next137 final generation' => static fn (TestRunner $t) => $t->same(2, $import()['next']['generation']),
    'vfs lock uri temp filecontrol current source next137 final deleted owners' => static fn (TestRunner $t) => $t->same(['temp:temp:2'], $import()['next']['deleted_temp_owners']),

    'vfs lock uri temp filecontrol current source next137 readonly nolock shared blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][1]['status']),
    'vfs lock uri temp filecontrol current source next137 readonly nolock reason' => static fn (TestRunner $t) => $t->same('URI nolock disables byte-range locking', $readonly()['events'][1]['reason']),
    'vfs lock uri temp filecontrol current source next137 readonly reserved blocked' => static fn (TestRunner $t) => $t->same('blocked', $readonly()['events'][2]['status']),
    'vfs lock uri temp filecontrol current source next137 readonly reserved reason' => static fn (TestRunner $t) => $t->same('URI nolock disables byte-range locking', $readonly()['events'][2]['reason']),
    'vfs lock uri temp filecontrol current source next137 nolock uri boolean true' => static fn (TestRunner $t) => $t->same(true, $readonly()['events'][3]['value']),
    'vfs lock uri temp filecontrol current source next137 directory current handle unchanged' => static fn (TestRunner $t) => $t->same('/srv/www/wp-content/uploads/sqlite-tmp', $directory()['events'][0]['temp_directory']),
    'vfs lock uri temp filecontrol current source next137 directory next handle changed' => static fn (TestRunner $t) => $t->same('/tmp/wp-new', $directory()['events'][2]['temp_directory']),
    'vfs lock uri temp filecontrol current source next137 rejects empty operations' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteVfsLockUriTempFileControlCurrentSourceNextPlan::run([])),
    'vfs lock uri temp filecontrol current source next137 rejects bad source' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run137([['op' => 'open', 'source' => '../temp']])),
    'vfs lock uri temp filecontrol current source next137 rejects bad uri authority' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run137(['open(temp, file://example.com/wp.sqlite?mode=memory)'])),
    'vfs lock uri temp filecontrol current source next137 rejects bad temp directory' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run137(['open(temp)', ['op' => 'filecontrol', 'control' => 'temp_directory', 'value' => '']])),
    'vfs lock uri temp filecontrol current source next137 rejects bad uri int' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run137(['open(temp, file:/tmp/a?mode=memory&scratch=abc)', ['op' => 'filecontrol', 'control' => 'uri_int', 'value' => ['parameter' => 'scratch']]])),
];
