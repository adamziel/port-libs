# real-upstream-corpus-select-core-dynamic-20260531T045705Z-0

Base accepted HEAD: `d470482ec8f04bd52049cae518f9a06a2103fe0c`.

Added `SQLiteRealUpstreamCorpusSelectCoreDynamicBatch3Test.php` with 1001 focused TestRunner PASS cases and 4004 assertions.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`

Ported scenario ranges:

- `select4.test` `select4-14.1` and `select4-14.2`: compound `SELECT ... INTERSECT VALUES(...)` arms.
- `select4.test` `select4-14.5`, `select4-14.6`, and `select4-14.8`: compound `SELECT ... EXCEPT VALUES(...)` arms including non-matching text values.
- `select4.test` `select4-14.3`, `select4-14.4`, `select4-14.16`, and `select4-14.17`: `UNION` with `VALUES` arms plus final `ORDER BY` and `LIMIT`.
- `select9.test` `select9-6.1`, `select9-6.2`, and `select9-6.3`: `WHERE 0` on compound SELECT arms.

Non-overlap:

- Existing accepted select-core dynamic batch coverage already exercises `select5.test`/`select6.test` derived aggregate, grouped join, and nullable grouping behavior.
- This batch targets compound SELECT with `VALUES` arms and empty-arm `WHERE 0` behavior only, and does not repeat accepted grouped SELECT text, JOIN text, expression ORDER BY, or JSON table SELECT source work.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicBatch3Test.php`
  - `1 test files, 4004 assertions, 0 failures`
  - 1001 PASS lines

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql` compound SELECT and VALUES execution paths against row-array tables.
