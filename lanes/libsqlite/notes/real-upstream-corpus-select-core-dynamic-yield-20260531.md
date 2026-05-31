# Real Upstream Corpus: SELECT Core Dynamic Yield

Slice: `real-upstream-corpus-select-core-dynamic-20260531T012955Z-0`

Base accepted HEAD: `a890092c734c05eb72a795bdc37321c497f93beb`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- Scenario `select4-15.1`, the compound `SELECT DISTINCT` self-join regression for coroutine/Yield register preservation.

Implemented coverage:

- Added `SQLiteRealUpstreamSelectCoreDynamicYieldTest.php`.
- Covers `select4-15.1` with 1250 dynamic seeds over generic `stream_rows` data.
- Exercises compound `UNION` and `UNION ALL`, two self-join arms, `SELECT DISTINCT`, cross-product filtering, final `ORDER BY 1 ASC/DESC`, duplicate-preserving and duplicate-eliminating result paths, and result fingerprint/edge/count checks.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicYieldTest.php`
- Result: `1 test files, 5004 assertions, 0 failures`
- PASS lines: `1251`

Expected movement:

- Count as focused PASS-line growth: `+1251`.
- Count as behavior assertions: `+5004`.
- No mapped denominator growth; upstream inventory remains `1589 / 1589`.

Non-overlap:

- Does not repeat accepted SELECTA UNION DISTINCT ORDER remainder behavior, expression `ORDER BY`, GROUP BY text, JOIN text, JSON table SELECT source/cursor behavior, or SELECTG/VALUES work.
- This slice owns upstream `select4.test` `select4-15.1` coroutine/Yield compound SELECT behavior.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP `SQLiteSelectSql` parser/executor and compound SELECT machinery.
