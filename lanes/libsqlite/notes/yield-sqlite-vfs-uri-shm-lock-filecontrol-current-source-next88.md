# libsqlite VFS URI SHM Lock File-Control Current-Source Next88

## Behavior

- Adds `SQLiteVfsShmLockFileControlCurrentSource::currentSourceNext88()` for URI-aware SHM lock and xFileControl current-source state.
- `file:` URI paths are parsed through `SQLiteFileUri`, so percent-decoded paths and `file://localhost/...` aliases share one SHM source.
- `mode=ro` allows read-only file controls under a SHM read lock but ignores write controls.
- `immutable=1`, `nolock=1`, and `mode=memory` block SHM lock acquisition with explicit reasons instead of mutating shared lock state.

## Evidence

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriShmLockFileControlCurrentSourceNext88Test.php`
- Expected focused delta: 55 assertions / 0 failures from one new lane-scoped test file.
- Application smoke: `php lanes/libsqlite/examples/application-vfs-uri-shm-lock-filecontrol-current-source-next88.php`

## Non-Overlap

This avoids accepted VFS lock-state, VFS file-writer, VFS open URI lock current-source next86, and SHM lock file-control next85 by covering the missing combined URI canonicalization plus SHM lock/file-control current-source behavior.

## Dependency Closure

No new support component is needed. The slice reuses existing `SQLiteFileUri` parsing and the existing bounded SHM lock/file-control current-source model.
