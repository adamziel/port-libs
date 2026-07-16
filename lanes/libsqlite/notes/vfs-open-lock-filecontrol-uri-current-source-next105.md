# vfs-open-lock-filecontrol-uri-current-source-next105

Slice: `vfs-open-lock-filecontrol-uri-current-source-next105`

## Behavior

Adds a bounded native VFS current-source path for URI-aware file-control reads.
`SQLiteVfsOpenLockFileControlCurrentSource::currentSourceNext105()` preserves
decoded `file:` URI query parameters on each opened handle and exposes
read-only `uri_parameter`, `uri_boolean`, and `uri_int` file-control probes.

The probes are per handle, do not mutate lock state, do not bump
`data_version`, and still report whether the handle was opened against a stale
current-source generation after sibling writes.

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenLockFileControlUriCurrentSourceNext105Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 52 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-vfs-open-lock-filecontrol-uri-current-source-next105.php --self-test
application-vfs-open-lock-filecontrol-uri-current-source-next105 self-test passed
```

## Non-Overlap

This is narrower than accepted VFS open admission, URI parsing, lock-byte
routing, file-control state transitions, xOpen device characteristics,
process-backed locks, locked writer, and VFS sync/apply clusters. It only adds
URI file-control reads on the existing current-source open/lock/file-control
timeline.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteFileUri`, VFS current-source state, lock-state, file-control, and
device-characteristic helpers.
