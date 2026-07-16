# real-upstream-corpus-trigger-fkey-dynamic-20260531T065207Z-0

Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey5.test`
- Ported sections: `fkey5-1.2..8.7`, `fkey5-10.3`, `fkey5-12.0..13.12`

Behavior added:

- `SQLiteDynamicTriggerForeignKeyPlan::foreignKeyCheckRows()` models PRAGMA `foreign_key_check` result-row production for child table, rowid, parent table, and foreign-key id.
- Covers integer parent-key checks, NULL child-key short-circuiting, parent-side `NOCASE`/`RTRIM` collation matching for composite references, attached-schema parent resolution, missing local parent behavior, and WITHOUT ROWID child rowid reporting as NULL.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`
  - Result: `1 test files, 26562 assertions, 0 failures`

PASS-line delta:

- Adds 541 distinct focused TestRunner PASS cases in `SQLiteRealUpstreamTriggerFkeyDynamicCorpusTest.php`.

Non-overlap:

- Extends the existing trigger/FK dynamic corpus into `fkey5.test` foreign-key-check row production.
- Avoids existing fkey1 replace cascade, trigger1/trigger2 execution order, fkey8 deferred counters, e_fkey compile capability modes, and recently accepted trigger/FK fkey5 basic check coverage.

Dependency closure:

- No new support component is needed.
- Reuses the existing dynamic trigger/FK planning helper and adds a bounded source-neutral method under the same class.
