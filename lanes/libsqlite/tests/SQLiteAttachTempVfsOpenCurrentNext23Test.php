<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachTempVfsOpenPlan;
use PortLibs\LibSqlite\SQLiteBusyHandler;

$temp = static fn (): array => SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '' AS scratch", true, true);
$filePlan = static fn (): array => SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '/srv/wp/site.sqlite' AS site", true, true);
$missing = static fn (): array => SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH 'file:/srv/wp/new.sqlite?mode=rwc&cache=shared' AS created", false, true);
$readonly = static fn (): array => SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH 'file:/srv/wp/archive.sqlite?mode=ro&immutable=1&vfs=unix-dotfile' AS archive", true, false);
$busy = static fn (): array => SQLiteAttachTempVfsOpenPlan::forAttachSql(
    "ATTACH 'file:/srv/wp/site.sqlite?mode=rw&cache=shared' AS site",
    true,
    true,
    false,
    SQLiteBusyHandler::timeout(25, 5),
);

return [
    'attach temp vfs open current next23 empty filename is temp attachment' => static fn (TestRunner $t) => $t->same(true, $temp()['temp_attachment']),
    'attach temp vfs open current next23 temp attach status is ready' => static fn (TestRunner $t) => $t->same('temp-ready', $temp()['status']),
    'attach temp vfs open current next23 temp attach preserves empty database list file' => static fn (TestRunner $t) => $t->same('', $temp()['database_list'][2]['file']),
    'attach temp vfs open current next23 temp attach schema is normalized' => static fn (TestRunner $t) => $t->same('scratch', $temp()['schema']),
    'attach temp vfs open current next23 temp open can create' => static fn (TestRunner $t) => $t->same(true, $temp()['open']['can_create']),
    'attach temp vfs open current next23 temp open is not memory database' => static fn (TestRunner $t) => $t->same(false, $temp()['open']['memory']),
    'attach temp vfs open current next23 temp open path stays empty' => static fn (TestRunner $t) => $t->same('', $temp()['open']['path']),
    'attach temp vfs open current next23 temp sidecar has no wal path' => static fn (TestRunner $t) => $t->same('', $temp()['sidecar']['wal_path']),
    'attach temp vfs open current next23 temp sidecar has no shm path' => static fn (TestRunner $t) => $t->same('', $temp()['sidecar']['shm_path']),
    'attach temp vfs open current next23 temp journal is writable' => static fn (TestRunner $t) => $t->same(true, $temp()['sidecar']['journal_writable']),
    'attach temp vfs open current next23 temp uses no shared memory' => static fn (TestRunner $t) => $t->same(false, $temp()['sidecar']['uses_shared_memory']),
    'attach temp vfs open current next23 temp requires directory write' => static fn (TestRunner $t) => $t->same(true, $temp()['sidecar']['requires_directory_write']),
    'attach temp vfs open current next23 temp dependencies include temp vfs' => static fn (TestRunner $t) => $t->same(true, in_array('temp-vfs-open', $temp()['dependencies'], true)),
    'attach temp vfs open current next23 temp dependencies include delete on commit' => static fn (TestRunner $t) => $t->same(true, in_array('temp-journal-delete-on-commit', $temp()['dependencies'], true)),
    'attach temp vfs open current next23 temp database list keeps main first' => static fn (TestRunner $t) => $t->same(['seq' => 0, 'name' => 'main', 'file' => null], $temp()['database_list'][0]),
    'attach temp vfs open current next23 temp database list keeps temp second' => static fn (TestRunner $t) => $t->same(['seq' => 1, 'name' => 'temp', 'file' => ''], $temp()['database_list'][1]),
    'attach temp vfs open current next23 temp attached schema is third' => static fn (TestRunner $t) => $t->same(2, $temp()['database_list'][2]['seq']),
    'attach temp vfs open current next23 ordinary file is not temp attachment' => static fn (TestRunner $t) => $t->same(false, $filePlan()['temp_attachment']),
    'attach temp vfs open current next23 ordinary file status is ready' => static fn (TestRunner $t) => $t->same('ready', $filePlan()['status']),
    'attach temp vfs open current next23 ordinary file path is parsed' => static fn (TestRunner $t) => $t->same('/srv/wp/site.sqlite', $filePlan()['open']['path']),
    'attach temp vfs open current next23 ordinary file wal sidecar follows path' => static fn (TestRunner $t) => $t->same('/srv/wp/site.sqlite-wal', $filePlan()['sidecar']['wal_path']),
    'attach temp vfs open current next23 ordinary file shm sidecar follows path' => static fn (TestRunner $t) => $t->same('/srv/wp/site.sqlite-shm', $filePlan()['sidecar']['shm_path']),
    'attach temp vfs open current next23 ordinary file journal sidecar follows path' => static fn (TestRunner $t) => $t->same('/srv/wp/site.sqlite-journal', $filePlan()['sidecar']['journal_path']),
    'attach temp vfs open current next23 ordinary file uses shared memory' => static fn (TestRunner $t) => $t->same(true, $filePlan()['sidecar']['uses_shared_memory']),
    'attach temp vfs open current next23 ordinary file wal writable in writable directory' => static fn (TestRunner $t) => $t->same(true, $filePlan()['sidecar']['wal_writable']),
    'attach temp vfs open current next23 ordinary file database list records filename' => static fn (TestRunner $t) => $t->same('/srv/wp/site.sqlite', $filePlan()['database_list'][2]['file']),
    'attach temp vfs open current next23 create attach reports create status' => static fn (TestRunner $t) => $t->same('create', $missing()['status']),
    'attach temp vfs open current next23 create attach can create' => static fn (TestRunner $t) => $t->same(true, $missing()['open']['can_create']),
    'attach temp vfs open current next23 create attach keeps shared cache dependency' => static fn (TestRunner $t) => $t->same(true, in_array('shared-cache-coordination', $missing()['dependencies'], true)),
    'attach temp vfs open current next23 create attach sidecar writable' => static fn (TestRunner $t) => $t->same(true, $missing()['sidecar']['journal_writable']),
    'attach temp vfs open current next23 uri schema is normalized' => static fn (TestRunner $t) => $t->same('created', $missing()['schema']),
    'attach temp vfs open current next23 readonly immutable attach can open' => static fn (TestRunner $t) => $t->same(true, $readonly()['open']['can_open']),
    'attach temp vfs open current next23 readonly immutable attach is read only' => static fn (TestRunner $t) => $t->same(true, $readonly()['open']['read_only']),
    'attach temp vfs open current next23 readonly immutable disables shm writable' => static fn (TestRunner $t) => $t->same(false, $readonly()['sidecar']['shm_writable']),
    'attach temp vfs open current next23 readonly immutable disables journal writable' => static fn (TestRunner $t) => $t->same(false, $readonly()['sidecar']['journal_writable']),
    'attach temp vfs open current next23 readonly immutable keeps vfs dependency' => static fn (TestRunner $t) => $t->same(true, in_array('vfs-admission', $readonly()['dependencies'], true)),
    'attach temp vfs open current next23 busy attach reports busy timeout status' => static fn (TestRunner $t) => $t->same('busy-timeout', $busy()['status']),
    'attach temp vfs open current next23 busy attach cannot open' => static fn (TestRunner $t) => $t->same(false, $busy()['open']['can_open']),
    'attach temp vfs open current next23 busy attach records reason' => static fn (TestRunner $t) => $t->same('database lock is busy', $busy()['open']['reason']),
    'attach temp vfs open current next23 busy attach keeps busy dependency' => static fn (TestRunner $t) => $t->same(true, in_array('busy-handler', $busy()['dependencies'], true)),
    'attach temp vfs open current next23 busy attach preserves operation' => static fn (TestRunner $t) => $t->same('attach', $busy()['operation']),
    'attach temp vfs open current next23 double quoted schema normalizes' => static fn (TestRunner $t) => $t->same('plugin-cache', SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '/srv/wp/cache.sqlite' AS \"Plugin-Cache\"", true, true)['schema']),
    'attach temp vfs open current next23 bracket schema normalizes' => static fn (TestRunner $t) => $t->same('plugin-cache', SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '/srv/wp/cache.sqlite' AS [Plugin-Cache]", true, true)['schema']),
    'attach temp vfs open current next23 quoted filename unescapes apostrophe' => static fn (TestRunner $t) => $t->same("/srv/wp/site's.sqlite", SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '/srv/wp/site''s.sqlite' AS quoted", true, true)['file']),
    'attach temp vfs open current next23 rejects main schema' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '/srv/wp/main.sqlite' AS main", true, true)),
    'attach temp vfs open current next23 rejects temp schema' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempVfsOpenPlan::forAttachSql("ATTACH '/srv/wp/temp.sqlite' AS temp", true, true)),
    'attach temp vfs open current next23 rejects expressions' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempVfsOpenPlan::forAttachSql('ATTACH concat("/srv/", "site.sqlite") AS site', true, true)),
    'attach temp vfs open current next23 rejects non attach sql' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteAttachTempVfsOpenPlan::forAttachSql('PRAGMA database_list', true, true)),
];
