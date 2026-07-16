# Real Upstream Corpus Trigger/FK Dynamic: Composite FK Section

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260601T161216Z-0`

Base accepted HEAD: `9c58ee164dbff3a0a230487ba6ff5944d5abeef5`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported sections: `e_fkey-28.1..28.9`, `e_fkey-29.1..29.3`, `e_fkey-30.1`

Behavior added:

- `SQLiteDynamicTriggerForeignKeyPlan::eForeignKeyCompositeConstraintPlan()`
  models upstream composite FK cardinality behavior from section 4.1.
- Covers create-time child/parent key cardinality diagnostics, parse errors for
  empty explicit parent key lists, DML-time mismatch for implicit parent primary
  key width mismatch, the upstream album/song composite FK example, and the
  rule that any NULL child-key column satisfies the FK without a parent row.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeSection20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeSection20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeSection20260601Test.php`
  - `1 test files, 10817 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeSection20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRequiredIndex20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicChildLookupPlan20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 36207 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: `+10817` focused assertions from the new real-upstream corpus test.
- Mapped denominator: no change claimed.

Non-overlap:

- This slice owns `e_fkey.test` composite FK section `e_fkey-28.1..30.1`.
- It intentionally avoids existing trigger/FK batches for required parent
  indexes (`e_fkey-18..24`), child lookup and child indexes (`e_fkey-25..27`),
  deferred/savepoint constraints (`e_fkey-31..38`), action satisfaction and
  deferrable-clause matrices (`e_fkey-39..54`), trigger1/2/6/7/C/G dynamic
  trigger diagnostics, and the source-neutral trigger/upsert/view cleanup.

Dependency closure:

- No new support component is needed. The slice reuses the existing
  `SQLiteDynamicTriggerForeignKeyPlan` dynamic trigger/FK corpus surface and
  the hydrated upstream Tcl cache for source citations.
