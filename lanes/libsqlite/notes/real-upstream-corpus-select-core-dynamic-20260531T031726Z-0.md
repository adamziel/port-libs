# real-upstream-corpus-select-core-dynamic-20260531T031726Z-0

Base accepted HEAD: `148cfd0e2c7cc75dba20ff0e424e615192f1e7c6`

Added focused real upstream SELECT core coverage in:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicRealSelect20260531T031726ZTest.php`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- Cited scenarios: `select1-1.4` through `select1-1.13` projection, repeated `*`, joined table extraction, scalar `min()` / `max()` rows, plus `select1-2.4`, `select1-2.5`, `select1-2.7`, `select1-2.10`, `select1-2.15`, and `select1-2.12` aggregate/scalar result rows.

Focused movement:

- `1252` focused TestRunner PASS cases.
- `5009` behavior assertions.
- No production source change was needed; the current `SQLiteSelectSql` path already supports this select1 core projection/join/scalar cluster.

Non-overlap:

- Avoids accepted grouped SELECT text, SELECT subqueries, expression `ORDER BY`, compound/coroutine yield, JSON table SELECT sources/cursors/hidden constraints, and prior select4/select6/select7/select8/select9/selectA/selectB/selectC/selectG/selectH dynamic corpus slices.
- This slice covers real `select1.test` core projection order, repeated wildcard expansion, joined table wildcard order, qualified join column extraction, two-argument scalar `min()` / `max()`, count expression arithmetic, and aggregate `min()` / `max()` / `sum()` rows over dynamic numeric inputs.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicRealSelect20260531T031726ZTest.php`
- Result: `1 test files, 5009 assertions, 0 failures`

Dependency closure:

- No new support component needed.
- Reuses lane-local `SQLiteSelectSql` projection, wildcard, join, scalar function, count, and aggregate execution helpers.
