# Attach TEMP VFS open current-next23

Status: focused PHP corpus growth for bounded ATTACH database VFS-open state and SQLite TEMP attached-database behavior.

- Added `SQLiteAttachTempVfsOpenPlan` for current ATTACH open planning without touching native files.
- Covers SQLite `ATTACH '' AS schema` temp-file database admission, database-list empty filename preservation, temp rollback-journal sidecar planning, ordinary attached file WAL/SHM/journal sidecars, URI create/read-only/immutable/busy-lock states, quoted schema normalization, and bounded rejection of unsafe attach expressions.
- Added `application-attach-temp-vfs-open-current-next23.php` to smoke a copied Application import scratch attachment plus a shared-cache site attachment without requiring `ext/sqlite`.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempVfsOpenCurrentNext23Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 48 PASS lines
# 1 test files, 48 assertions, 0 failures
```

Expected dashboard movement:

- `phpPass`: `8166 -> 8214` (+48 verified focused PASS lines).
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage, not a newly mapped upstream inventory unit.

Dependency closure:

- No new support component is needed. The slice reuses bounded `SQLiteOpenPlan`, `SQLiteVfsSidecarPlan`, and `SQLiteBusyHandler` primitives and adds only a lane-local ATTACH/open planner.

Non-overlap:

- Avoids accepted ATTACH temp trigger FK resolution, attach/detach schema catalog lookup, VFS file writer/application, VFS lock state/process locks, VFS sync apply, rollback-journal commit/apply, super-journal commits, WAL byte truncation/checkpoint transactions, and file-control/device-capability clusters.
- This slice is limited to VFS-open planning for attached schemas and SQLite's empty-filename TEMP attached database behavior.
