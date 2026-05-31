# Real Upstream Corpus Trigger/FK Dynamic

Base accepted HEAD: `57904efd88f87abfad6d70c753ea59660958850e`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- Scenarios: `fkey2-11.1.1` ON UPDATE CASCADE, `fkey2-12.1.4` immediate RESTRICT despite deferred declaration, `fkey2-9.*` SET NULL / SET DEFAULT actions, and deferred NO ACTION commit checking from the fkey2 deferred-action corpus.

Implemented behavior:

- `SQLiteTriggerDeferredForeignKeyPlan` now models parent-key `ON UPDATE` actions for `CASCADE`, `SET NULL`, `SET DEFAULT`, immediate `RESTRICT`, and deferred `NO ACTION`.
- Deferred no-action parent updates queue a commit-time `parent-update-check`; repairing the old parent key before commit clears the violation.
- Tests use generic `parent_items` / `child_items` fixtures and add no new domain-specific source text.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerDeferredForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyParentUpdateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyParentUpdateDynamicTest.php`
  - `1 test files, 75 assertions, 0 failures`
  - `5` focused PASS lines
- The no-domain API guard file is absent in this worktree, so the guard was not run.

Dependency closure:

- No new support component is needed. The slice extends the existing bounded trigger/deferred-FK planner.

Non-overlap:

- Avoids accepted trigger/FK REPLACE counter behavior and prior recursive/view/returning trigger families by targeting parent-key `ON UPDATE` action materialization from real upstream `fkey2.test`.
