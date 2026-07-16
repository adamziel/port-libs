# vfs-open-lock-filecontrol-uri-current-source-next109

Slice: `vfs-open-lock-filecontrol-uri-current-source-next109`

## Behavior

Adds `SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext109()` for
SQLite-compatible VFS URI helper semantics on the existing URI-aware
open/lock/file-control current-source timeline.

The next109 entry point keeps accepted next105 strict URI probes intact while
adding upstream-style `sqlite3_uri_boolean()` and `sqlite3_uri_int64()` behavior:
boolean probes accept `yes`, `true`, `on`, case-insensitive matches, non-zero
numeric prefixes, false text forms, zero numeric prefixes, and caller defaults;
integer probes accept signed integers, return `0` for invalid integer text, and
return caller defaults for missing parameters. The probes remain read-only:
they do not mutate lock state, do not bump data-version generations, and still
report stale current-source handles after sibling writes.

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlUriCurrentSourceNext109Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 41 assertions, 0 failures
```

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlUriCurrentSourceNext105Test.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlUriCurrentSourceNext109Test.php
Focused test run: 2 selected test files (root lock skipped)
...
2 test files, 93 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-vfs-open-lock-filecontrol-uri-current-source-next109.php --self-test
application-vfs-open-lock-filecontrol-uri-current-source-next109 self-test passed
```

## Non-Overlap

This avoids accepted VFS open admission, URI parsing, lock byte ranges,
process-backed locks, VFS file writer, sync apply, rollback-journal
apply/commit, savepoint rollback, xOpen device characteristics, next99
data-version current-source tracking, and next105 strict URI file-control
probes. The new behavior is specifically the SQLite C API helper conversion
semantics for URI parameter boolean and integer file-controls.

## Dependency Closure

No new support component is needed. The implementation reuses existing native
PHP `SQLiteFileUri`, VFS current-source generation, lock-state, file-control,
and device-characteristic helpers.
