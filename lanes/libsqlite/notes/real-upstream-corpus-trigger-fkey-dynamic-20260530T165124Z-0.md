# real-upstream-corpus-trigger-fkey-dynamic-20260530T165124Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported scenario ranges: `fkey2-4.*`, `fkey2-9.*`, `fkey2-11.*`,
  `fkey2-12.*`, and `fkey2-20.*`.

Implemented behavior:

- `SQLiteTriggerForeignKeyReturningPlan` now treats `RESTRICT` actions as
  immediate even when the constraint is deferred, matching the `fkey2-12.*`
  upstream behavior.
- Added generic application-row corpus tests for cascading FK actions,
  `SET NULL`, `SET DEFAULT`, deferred `NO ACTION`, statement conflict policy
  independence, row trigger order, RETURNING images, and dynamic parent-key
  batches.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTest.php`
- Result: `1 test files, 603 assertions, 0 failures`
- New focused PASS lines in this slice: 46
- Mapped denominator: unchanged; no new manifest row is claimed.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP
  trigger/FK/RETURNING helper and tightens its upstream-compatible behavior.

Non-overlap:

- This slice does not touch accepted trigger lifecycle, recursive-view,
  UPSERT/RETURNING, pager/WAL, B-tree, JSON, or source-neutral cleanup
  surfaces. It is limited to real upstream FK dynamic action behavior from
  `fkey2.test`.
