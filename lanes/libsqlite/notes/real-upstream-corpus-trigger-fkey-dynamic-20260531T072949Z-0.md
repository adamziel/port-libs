# real-upstream-corpus-trigger-fkey-dynamic-20260531T072949Z-0

Implemented a bounded real upstream trigger/FK dynamic cluster in
`SQLiteTriggerForeignKeyDynamicPlan` and extended
`SQLiteTriggerForeignKeyDynamicCorpusTest`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test`
- `fkey2-4.1..4.4`: recursive foreign-key `ON DELETE CASCADE` actions still
  recurse when `PRAGMA recursive_triggers=off`, while equivalent user triggers
  stop after their direct trigger body.
- `fkey2-12.2.1..12.2.4`: an `AFTER DELETE` trigger can repair `NO ACTION`
  parent removal before the FK check, but `ON DELETE RESTRICT` blocks before
  the repair trigger can run. The parent key uses `NOCASE` matching.

Focused evidence:

- Red-first: the initial cascade model failed 3 focused assertions because it
  suppressed the direct trigger body when recursive triggers were disabled.
- `php -l lanes/libsqlite/src/SQLiteTriggerForeignKeyDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerForeignKeyDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerForeignKeyDynamicCorpusTest.php`
  passed: `1 test files, 68 assertions, 0 failures`.

New focused assertions:

- 17 added assertions over real `fkey2.test` trigger/FK behavior.

Dependency closure:

- No new support component is required. This reuses the existing bounded
  trigger/FK row-state model and adds source-local cascade/NOCASE helpers.

Non-overlap:

- This does not repeat prior `e_fkey.test` deferred savepoint/commit repair,
  parent update order, variable rejection, counter, temp-trigger lifecycle, or
  view-trigger corpus coverage. The added scenarios are from `fkey2.test`
  recursive cascade behavior and delete-trigger repair/restrict timing.
