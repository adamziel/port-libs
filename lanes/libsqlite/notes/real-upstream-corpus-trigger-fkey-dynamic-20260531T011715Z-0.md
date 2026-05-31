# real-upstream-corpus-trigger-fkey-dynamic-20260531T011715Z-0

Slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T011715Z-0`
Base accepted HEAD: `2541019b82319811accbb79790d214be59d31028`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test`
- Ported section: `trigger1-24.1..24.2`
- Behavior: `RAISE()` in a trigger program accepts an arbitrary SQL expression as
  its message argument, may reference `new.a`, formats the failing row value,
  and applies normal `ABORT`, `FAIL`, and `ROLLBACK` statement effects.

## Files

- `lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseExpressionTest.php`
- `lanes/libsqlite/notes/real-upstream-corpus-trigger-fkey-dynamic-20260531T011715Z-0.md`
- `lanes/libsqlite/lane-status.json`

## Verification

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - Result: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseExpressionTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseExpressionTest.php`
  - Result: `1 test files, 3407 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseExpressionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseActionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicRaiseCorpusTest.php`
  - Result: `3 test files, 20539 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

## Countability

- Focused selected movement: `+3407` real TestRunner PASS/assertion lines.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior growth,
  not new denominator mapping.

## Non-Overlap

This does not repeat accepted trigger/FK UPDATE-FROM, fkey2 NOCASE repair,
fkey6 deferred restrict repair, fkey7/fkey8 action/counter behavior,
trigger2 row timing, trigger3 static RAISE action matrix, trigger4/5/7/8/9/A/C/D/E/F/G
coverage, DROP TRIGGER, schema-source reprepare, trigger RETURNING, or
UPSERT/RETURNING trigger old-value batches. The new surface is specifically the
2024 upstream `trigger1-24.*` arbitrary-expression `RAISE()` message behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
trigger/FK dynamic planner surface and the hydrated SQLite upstream checkout as
source truth.
