# VFS open SHM file-control URI current-source next128

## Behavior

- Added `SQLiteVfsLockByteUriShmCurrentSourceNext::currentSourceNext128()` for SQLite-style URI xFileControl probes on the combined VFS open/byte-lock/SHM/current-source path.
- `uri_parameter`, `uri_boolean`, and `uri_int` now read from the active handle's own URI query parameters for `main`, `wal`, and `shm` sources.
- URI probes are read-only: they do not mutate persistent controls or data-version generation, but they report stale current-source state when another handle has changed generation.
- Repeated URI parameters preserve all values and return the last value, matching SQLite URI helper behavior. Missing boolean/int probes use explicit defaults; malformed integer URI values return `0` under SQLite helper semantics.

## Application relevance

Copied Application SQLite import and repair flows can tag URI opens with per-handle role, busy/readmark, checkpoint, psow, or custom probe hints. The next128 planner keeps those hints scoped to the current main/WAL/SHM handle while preserving byte-lock ownership, SHM locks, and stale data-version detection without requiring `ext/sqlite`.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsOpenShmFileControlUriCurrentSourceNext128Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

## Non-overlap

This slice avoids accepted VFS lock-byte ranges, VFS file writer, locked writer, process file locks, lock-state, VFS sync plan/apply, rollback-journal apply/commit, VFS URI/SHM lock current-source next126, and VFS open-lock-filecontrol URI next105/next109 standalone handle probes. The new behavior is specifically the combined open + SHM + file-control + URI current-source path for next128.

## Dependency closure

No new support component is needed. The slice reuses `SQLiteFileUri`, `SQLiteLockByteRangePlan`, current-source generation tracking, and existing SHM/byte-lock helpers.
