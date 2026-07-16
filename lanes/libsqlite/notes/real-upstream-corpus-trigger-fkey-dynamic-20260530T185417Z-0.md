# real-upstream-corpus-trigger-fkey-dynamic-20260530T185417Z-0

- Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260530T185417Z-0`
- Base accepted HEAD: `49b5c4e4a088c53e02910590cc011ce37a3ffc52`
- Upstream source:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
  - Scenario range: section 4.3 `ON DELETE and ON UPDATE Actions`, including action vocabulary/default NO ACTION, SET NULL, SET DEFAULT, CASCADE, and deferred NO ACTION evidence blocks.

Implemented lane-local PHP coverage in `SQLiteRealUpstreamTriggerFkeyActionMatrixDynamicTest.php`.

Focused delta:

- 1004 distinct TestRunner PASS cases.
- 5011 behavior assertions.
- Generic parent/child `setting_key` rows only; no WordPress-named API or scenario.
- `phpPass` expected movement: `355604 -> 356608`.
- Mapped coverage remains `1472 / 1589`.

Non-overlap:

This does not repeat the existing trigger2 row timing, selective UPDATE OF / WHEN, cascaded trigger, trigger conflict propagation, view trigger, trigger RAISE, fkey1 replacement cascade, fkey2 deferred savepoint, PRAGMA foreign-key catalog, or RETURNING trigger/FK batches. The new surface is the upstream `e_fkey.test` section 4.3 action matrix over native PHP FK action application.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local `SQLiteTriggerForeignKeyReturningPlan` update/delete FK action behavior and adds real upstream matrix coverage over it.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyActionMatrixDynamicTest.php`
  - Result: `1 test files, 5011 assertions, 0 failures`.

Next task:

Continue trigger/FK real-upstream work with a non-overlapping upstream range such as `e_fkey.test` recursive trigger-depth sections or `trigger3.test` RAISE expression variants, only if it can satisfy the current real-corpus handoff floor.
