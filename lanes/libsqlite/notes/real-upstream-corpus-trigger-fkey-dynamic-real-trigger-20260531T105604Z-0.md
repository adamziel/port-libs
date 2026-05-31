# real-upstream-corpus-trigger-fkey-dynamic-real-trigger-20260531T105604Z-0

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Ported sections:
  - `fkey2-dd08e5.1.1..1.6`: composite child references to `(a,b)` reject parent delete, missing child inserts, child key updates, and parent key updates while preserving the original rows.
  - `fkey2-ce7c13.1.1..1.6`: no-op updates of the referenced composite parent key commit for both external unique indexes and inline `UNIQUE(a,b)`, while changed parent-key updates still fail.
  - `fkey2-20150416-100`: parser-time foreign-key mismatch propagates instead of being hidden by following statements.

## Behavior Ported

- Added `SQLiteDynamicTriggerForeignKeyPlan::fkey2CompositeParentRegressionPlan()`.
- Models external unique-index and inline unique-constraint parent key forms.
- Preserves attempted versus committed parent/child row images for failed statements and records the composite child key that caused each violation.
- Adds parser mismatch propagation metadata for the upstream 2015 regression case.

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeParentRegression20260531Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeParentRegression20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeParentRegression20260531Test.php`
  - `1 test files, 93009 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCompositeParentRegression20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicGenfkeyCompatibility20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicConflictPolicy20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 170079 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Focused PASS-line growth: `+1004` TestRunner PASS cases.
Mapped coverage remains `1589 / 1589`; this is behavior/PASS growth over already mapped upstream `fkey2.test`.

## Non-Overlap

This slice is distinct from the accepted `fkey2-genfkey.*` compatibility block, `fkey2-20.*` conflict-policy behavior, `fkey2-17.*` count_changes behavior, `fkey2-15/16` counter/self-reference behavior, `fkey6` defer-pragma behavior, `fkey5` foreign_key_check behavior, trigger name catalog behavior, implicit DROP behavior, JSON, VFS, WAL, B-tree, and PRAGMA batches.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local dynamic trigger/FK planner and the hydrated SQLite upstream checkout.
