# real-upstream-corpus-trigger-fkey-dynamic implicit DROP TABLE

Session: `port-dev-sqlite-yield-dyn-real-trigger-20260531T094718Z`
Micro-slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T094718Z-0`
Base accepted HEAD: `ffcc95ebfcac7bbcd16b24facd07c90559f1565a`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported sections:
  - `e_fkey-57.1` through `e_fkey-57.7`: `DROP TABLE` runs an implicit delete when foreign keys are enabled, applies FK actions, and does not fire SQL triggers.
  - `e_fkey-58.1` through `e_fkey-58.4`: immediate FK failure rolls back the drop and leaves the parent table visible.
  - `e_fkey-59.1` through `e_fkey-59.5`: deferred FK failure is reported at commit and can be repaired before commit.
  - `e_fkey-60.1` through `e_fkey-60.6`: mismatch errors during the implicit drop-table delete are ignored while valid FK actions still run.
  - `e_fkey-61.3.1` through `e_fkey-61.3.3`: FK-off mode disables the special drop-table behavior.

## Patch Summary

- Added `SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyDropTableImplicitDeletePlan()` for composite parent/child row-array behavior.
- Added `SQLiteRealUpstreamTriggerFkeyDynamicImplicitDrop20260531Test.php` with generic `app_parent_*` / child table names.
- New focused evidence: `4103` distinct TestRunner `PASS` lines, `4241` assertions, `0` failures.
- Expected selected `phpPass` movement: `2840323 -> 2844426` (`+4103`).
- Mapped coverage remains `1589 / 1589`; this is PASS-line growth over an already mapped upstream corpus file.

## Non-Overlap

This slice is intentionally limited to `e_fkey.test` implicit `DROP TABLE` foreign-key delete behavior. It does not repeat the already accepted/covered fkey2 schema DDL planner (`fkey2-14`), recursive FK cascade pragma behavior, fkey6 defer lifecycle/status behavior, trigger rowid/variable handling, or parent-key distinctness before ON UPDATE action dispatch.

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicImplicitDrop20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicImplicitDrop20260531Test.php`
  - `1 test files, 4241 assertions, 0 failures`
  - `4103` `PASS` lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicImplicitDrop20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicDdlTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSchemaDropTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRecursiveCascadePragmaTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `5 test files, 23782 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP row-array trigger/FK planning primitives and the hydrated upstream SQLite Tcl corpus as source truth.
