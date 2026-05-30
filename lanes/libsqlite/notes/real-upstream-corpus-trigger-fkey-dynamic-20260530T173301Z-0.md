# real-upstream-corpus-trigger-fkey-dynamic-20260530T173301Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T173301Z-0`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test`
- Scenario ranges:
  - `trigger2-3.1` `UPDATE OF` triggers
  - `trigger2-3.2` `WHEN` clauses, including subquery pre-insert visibility
  - `trigger2-4.1` cascaded trigger execution
  - `trigger2-4.2` non-recursive trigger-program limit
  - `trigger2-5` `changes()` excludes trigger-program side effects
  - `trigger2-6.1a` through `trigger2-6.2h` trigger-program conflict policy propagation

## Status

Added native PHP trigger/FK corpus follow-up coverage in
`SQLiteDynamicTriggerForeignKeyPlan` plus
`SQLiteRealUpstreamTriggerFkeyDynamicFollowupTest.php`.

Focused new test delta:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFollowupTest.php`
- Result: `1 test files, 2044 assertions, 0 failures`

Focused trigger/FK family check:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusTriggerFkeyDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicFollowupTest.php`
- Result: `3 test files, 8719 assertions, 0 failures`

## Non-Overlap

This extends the accepted trigger/FK dynamic corpus after the existing
`trigger2-1` row-trigger BEFORE/AFTER timing coverage. It does not repeat the
already accepted `fkey1.test` replacement cascade, `fkey2.test` recursive FK
cascade, `fkey6.test` deferred pragma checks, or `trigger1.test` schema
lifecycle/statement-preservation cases. The new behavior is specifically
`trigger2.test` selective trigger firing, cascaded trigger programs,
`changes()` boundaries, and conflict-policy propagation into trigger programs.

## Dependency Closure

No new support component is needed. The slice reuses lane-local trigger/FK
row-array execution primitives and adds bounded native PHP behavior models for
additional upstream trigger execution cases.
