# real-upstream-corpus-trigger-fkey-dynamic-20260531T033247Z-0

Status: focused PHP behavior growth from real upstream SQLite trigger/FK corpus.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey6.test`
- Ported sections: `fkey6-3.2.1..3.2.6` and `fkey6-3.3.1..3.3.4`

Behavior ported:

- Immediate `ON UPDATE RESTRICT` and `ON DELETE RESTRICT` checks fail before
  the statement can change parent rows when `defer_foreign_keys` is off.
- With `defer_foreign_keys` on, RESTRICT checks are postponed until commit.
- A deferred parent-key update can commit if the final parent set satisfies the
  child reference, but a later deferred update rolls back when commit leaves an
  orphan child key.
- A deferred delete can commit when an AFTER DELETE trigger repairs the parent
  key before the deferred FK check runs.

Changed files:

- `lanes/libsqlite/src/SQLiteDeferredForeignKeyRestrictPlan.php`
- `lanes/libsqlite/tests/SQLiteDeferredForeignKeyRestrictPlanTest.php`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T033247Z-0.md`

Verification:

- `php -l lanes/libsqlite/src/SQLiteDeferredForeignKeyRestrictPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteDeferredForeignKeyRestrictPlanTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteDeferredForeignKeyRestrictPlanTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Focused assertion count:

- `SQLiteDeferredForeignKeyRestrictPlanTest.php`: 43 assertions, 0 failures.

Non-overlap:

- This does not repeat accepted trigger/FK attached restrict behavior, fkey
  action matrices, fkey7/nocase/composite/defer-pragma basics, trigger9
  INSTEAD OF view trigger rows, trigger5 undo, trigger7 name/drop diagnostics,
  trigger8 large body execution, triggerD rowid alias handling, triggerE
  variable rejection, triggerF WITHOUT ROWID conflicts, triggerG recursive
  SELECT behavior, UPSERT RETURNING trigger/FK helpers, row-value RETURNING,
  or source-neutral cleanup.

Dependency closure:

- No new support component is needed. This reuses generic lane-local
  array-backed parent/child row execution, trigger-effect recording, and
  deferred FK commit checking.
