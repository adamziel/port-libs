# real-upstream-corpus-trigger-fkey-dynamic-20260531T010813Z-0

Added `SQLiteRealUpstreamTriggerFkeyDynamicCountChangesBoundaryTest.php` and
`SQLiteDynamicTriggerForeignKeyPlan::fkey2CountChangesBoundary()` for real
upstream trigger/FK count-change boundaries.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  - `fkey2-17.1.5..17.1.9`: failed FK update rolls back only the statement and
    the surrounding transaction can still commit.
  - `fkey2-17.1.10..17.1.14`: `PRAGMA count_changes=1` yields a row before a
    deferred FK violation is reported on the next step/finalize.
  - `fkey2-17.2.1..17.2.10`: FK action rows are excluded from
    `changes()`/count-changes rows but included in `total_changes()`.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCountChangesBoundaryTest.php`
  - no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCountChangesBoundaryTest.php`
  - `1 test files, 8585 assertions, 0 failures`
  - `8585` focused PASS lines.

Non-overlap:

- This slice extends trigger/FK count-change and deferred constraint boundary
  behavior from `fkey2-17.*`.
- It does not repeat accepted `fkey2-2.*` deferred graph/counter-reset,
  `fkey2-12.*` nocase repair, `fkey6` deferred restrict repair, trigger5 undo,
  trigger7/triggerC rowid mutation, trigger/FK action-matrix, JSON, WAL, pager,
  VFS, B-tree, PRAGMA, SELECT, or metadata-only upstream runner rows.
- All names remain source-neutral generic SQLite/application names.

Dependency closure:

- No new support component is needed. This reuses the existing native
  `SQLiteDynamicTriggerForeignKeyPlan` trigger/FK helper surface and adds one
  bounded native PHP method for the upstream `fkey2-17.*` count-change
  boundary.
