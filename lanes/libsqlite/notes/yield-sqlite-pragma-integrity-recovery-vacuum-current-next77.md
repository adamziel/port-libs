# PRAGMA Integrity Recovery/Vacuum Current-Next 77

## Scope

Adds `SQLitePragmaIntegrityRecoveryVacuumYield`, a focused current/next
planner that turns PRAGMA integrity findings into recovery and vacuum gating
actions. The slice covers header, freelist, pointer-map, schema-root, btree,
foreign-key, and inferred message classifications without duplicating earlier
PRAGMA integrity pagination, pointer-map/freelist diagnostics, or foreign-key
index integrity slices.

## Application path

`examples/application-pragma-integrity-recovery-vacuum-current-next77.php`
models a Application SQLite import/repair pass where integrity findings block
incremental vacuum until freelist, pointer-map, and btree recovery actions are
completed.

## Verification

Focused command:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityRecoveryVacuumCurrentNext77Test.php`

Expected focused result after this slice:

`1 test files, 80 assertions, 0 failures`

Example smoke:

`php lanes/libsqlite/examples/application-pragma-integrity-recovery-vacuum-current-next77.php`

## Dependency Closure

No new support component is needed. The slice reuses existing
`SQLitePragmaIntegrityCurrentNextYield`, `SQLitePragmaIntegrityCheck`, and
`SQLiteDatabase` pointer-map metadata when a real database image is available,
while accepting already-collected integrity rows for runner and Application repair
preflight paths.

## Non-Overlap

Avoids accepted PRAGMA foreign-key/index integrity, pointer-map/freelist
pagination, table-scope/root diagnostics, hot rollback/super-journal recovery,
and VACUUM pointer-map application. This handoff only adds the recovery/vacuum
gating layer for integrity findings.
