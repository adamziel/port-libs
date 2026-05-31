# real-upstream-corpus-trigger-fkey-dynamic-20260531T024202Z-0

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey8.test`
- Ported sections: `fkey8-2.1` prior parent DELETE plus `INSERT OR REPLACE`
  deferred FK failure, `fkey8-2.2` child-table REPLACE counter cancellation,
  and `fkey8-2.3` trigger-induced parent REPLACE after an implicit DELETE.

## Patch

- Added `SQLiteForeignKeyReplaceCounterPlan` for generic settings-style
  parent/child rows.
- Added `SQLiteRealUpstreamCorpusTriggerFkeyReplaceCounterTest.php` with a
  high-yield dynamic matrix for immediate/deferred counters, prior statement
  deletes, child REPLACE cancellation, statement-journal indication for
  WITHOUT ROWID parent replacement, and trigger-induced replacement.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteForeignKeyReplaceCounterPlan.php`: pass.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyReplaceCounterTest.php`: pass.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyReplaceCounterTest.php`: `1 test files, 6008 assertions, 0 failures`.

## Non-overlap

This is distinct from the already-present recursive trigger/deferred FK yield
coverage and trigger/FK action-matrix coverage. The new behavior is fkey8
implicit DELETE/REPLACE FK counter accounting, including the child-side
counter-cancellation case and a trigger-induced replacement case.

## Dependency closure

No new support component is required. The slice uses existing PHP array-model
execution and real upstream corpus text as source truth.
