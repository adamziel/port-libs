# real-upstream-corpus-select-core-dynamic-20260531T053745Z-0

Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test`
- Scenario families: `do_select_tests e_select-0.2` select-core syntax matrix and `e_select-1` ORDER BY / LIMIT / OFFSET behavior.

Added focused PHP coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T053745ZTest.php`
- 1,001 distinct TestRunner PASS cases.
- 5,005 behavior assertions.
- Dynamic generic application row coverage for SELECT ALL/DISTINCT, WHERE, GROUP BY ordinal, HAVING, ORDER BY, LIMIT, and OFFSET through `SQLiteSelectSql`.

Non-overlap:

- This slice does not repeat accepted SELECT B set ops, SELECT D parenthesized joins, expression ORDER BY, grouped SELECT SQL text, single-table SELECT SQL text, JOIN text dispatch, comma LIMIT, subqueries, JSON table SELECT sources, or prior selectC alias-resolution batches.
- The first local run exposed the existing executor limit `SQLite SELECT SQL GROUP BY supports one aggregate value column` for a two-aggregate dynamic group. That unsupported shape was excluded from this ready batch and is a follow-up candidate because it is a real SELECT-core parity gap.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T053745ZTest.php`: no syntax errors.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260531T053745ZTest.php`: `1 test files, 5005 assertions, 0 failures`.

Expected movement:

- PASS-line growth: `+1001`.
- `phpPass`: `2323745 -> 2324746`.
- Mapped coverage: unchanged, `1589 / 1589`.

Dependency closure:

- No new support component is needed. This reuses existing `SQLiteSelectSql` and the hydrated upstream SQLite test checkout.
