# VFS URI Open Lock-Byte Current Source Next100

## Delta

- Added `SQLiteVfsUriOpenLockByteCurrentSourceNext` for current-source
  lifecycle behavior around decoded SQLite `file:` URI opens.
- The slice tracks URI-normalized sources, alias groups for equivalent decoded
  paths, file-wide byte-lock conflicts across aliases, reopen reference counts,
  close-time holder release, and lock-byte transitions including explicit
  `none` release.
- Added a WordPress smoke for copied `wp-content/database/wp.sqlite` reader and
  import handles sharing one decoded path while retaining independent current
  source state.

## Verification

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsUriOpenLockByteCurrentSourceNextTest.php
```

Expected focused result: `1 test files`, `70 assertions`, `0 failures`.

## Non-overlap

This avoids accepted VFS lock byte-range-only, VFS lock-state, SHM lock,
process lock, file writer, file-control persistence, and rollback/sync apply
clusters. The behavior here is current-source lifecycle handoff after URI open:
reopen/close counts and lock-byte release/downgrade transitions for named
current handles.

## Dependency closure

No new support component is needed. This reuses the bounded native
`SQLiteFileUri`, `SQLiteOpenPlan`, and `SQLiteLockByteRangePlan` primitives.
