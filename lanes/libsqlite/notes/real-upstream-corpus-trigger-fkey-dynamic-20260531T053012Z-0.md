# real-upstream-corpus-trigger-fkey-dynamic-20260531T053012Z-0

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`.

This slice extends the existing real upstream trigger/FK dynamic corpus with
top-level statement change accounting. The behavior is source-backed by:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
  `fkey2-17.*`: `count_changes` does not interfere with FK processing.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
  `trigger2-5`: trigger side effects are excluded from the top-level change
  count.
- Existing matrix anchors remain `fkey2-2.*`, `fkey2-4.*`, `trigger2-4.*`,
  and `trigger3-3.*`.

Implementation:

- `SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan` now reports
  `statement_changes` and `next_statement_changes` separately from
  `current_changes` / `next_changes`, preserving the existing recursive effect
  count while exposing the SQLite top-level statement count.
- `SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php` adds two focused
  assertions for every existing dynamic matrix case: one for current
  statement-level changes and one for post-rollback/deferred boundary changes.

Focused evidence:

- Before this slice, the focused file had `1601` PASS/assertion lines.
- After this slice:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php`
  passed with `1 test files, 2241 assertions, 0 failures`.
- New focused PASS-line movement: `+640`.
- Local `lane-status.json` selected count moves from `2297185` to `2297825`
  pass / `0` fail. Mapped coverage remains `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicYieldCurrentTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the
existing native trigger/FK/savepoint plan helper and adds no external runner,
extension, bridge, or fixture dependency.

Non-overlap: this does not add metadata-only rows, fake upstream script ids, or
WordPress-shaped APIs. It avoids accepted trigger/FK replace-counter,
WITHOUT ROWID, fkey7, view-trigger, and recursive RETURNING behavior by
targeting only statement-level change accounting over the existing dynamic
trigger/FK matrix.
