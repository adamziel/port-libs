Real upstream trigger/FK dynamic child lookup plan, 2026-05-31

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T212040Z-0`
Base accepted HEAD: `3a3374ad59c06e8a3561833481036dd945373160`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Upstream scenarios: `e_fkey-25.1` through `e_fkey-25.7`,
  dynamic `e_fkey-26.2.1`/`26.2.2`, `e_fkey-26.3.1`/`26.3.2`,
  `e_fkey-26.4.1`/`26.4.2`, and `e_fkey-27.1` through `e_fkey-27.4`.

Behavior added:

- `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyChildLookupPlan()` models the
  parent DELETE/UPDATE child-row lookup described by upstream as
  `SELECT rowid FROM <child-table> WHERE <child-key> = :parent_key_value`.
- The plan reports parent DELETE blocking when the child lookup returns any row,
  NULL child-key short-circuiting, parent UPDATE planning both old and new
  child-key lookups, and EQP shape differences between child-table scans and
  covering child-key indexes, including reversed unique child-key indexes.
- This is source-neutral and does not add a new support component.

Non-overlap:

- This handoff does not touch accepted drop-trigger cleanup, temp trigger
  lifecycle, triggerC/triggerG recursion diagnostics, fkey2 conflict policy,
  fkey4 deferred autocommit, fkey5/e_fkey comparison and action sections, or
  pragma index diagnostics. It specifically covers the e_fkey section-3 child
  lookup execution/EQP behavior in `e_fkey-25` through `e_fkey-27`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicChildLookupPlan20260531Test.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicChildLookupPlan20260531Test.php`
  - `1 test files, 24044 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Passed with no whitespace errors.

Status delta:

- `lanes/libsqlite/lane-status.json` selected evidence moves from `3847998` to
  `3872042` pass / `0` fail, a `+24044` focused assertion delta.
- Mapped denominator remains `1589 / 1589`.

Dependency closure:

- No new support component is required. The slice reuses the existing
  `SQLiteDynamicTriggerForeignKeyPlan` bounded corpus-plan surface and adds
  focused source-truth tests only.
