# real-upstream-corpus-trigger-fkey-dynamic-20260530T183226Z-0

Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`.

Added `SQLiteRealUpstreamTriggerFkeyDynamicTrigger2BatchTest.php` with 1,001
distinct TestRunner PASS cases and 10,504 focused assertions. The batch cites
real hydrated upstream SQLite source file
`/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger2.test` and ports
the following non-overlapping sections:

- `trigger2-3.1..3.2`: selective `UPDATE OF` firing and `WHEN` predicates,
  including subquery-style preinsert visibility.
- `trigger2-4.1..4.2`: cascaded trigger program execution with recursive
  trigger limiting.
- `trigger2-5`: `count_changes` boundary excluding trigger-program side
  effects.
- `trigger2-6.1a..6.1h` and `trigger2-6.2a..6.2h`: outer conflict policy
  propagation into trigger programs.

Non-overlap: this does not touch accepted trigger/fkey dynamic batches for
`fkey1`, `fkey2`, `fkey6`, `trigger1`, `triggerC`, RETURNING, savepoint,
deferred-FK, or current-source view/upsert helper families. It reuses the
existing generic `SQLiteDynamicTriggerForeignKeyPlan` behavior surface and adds
only upstream-backed focused tests.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2BatchTest.php`
  passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2BatchTest.php`
  passed: `1 test files, 10504 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicTrigger2BatchTest.php`
  passed: `2 test files, 21248 assertions, 0 failures`.

Dependency closure: no new support component is needed; this batch reuses the
existing bounded native PHP trigger/FK dynamic plan helper.
