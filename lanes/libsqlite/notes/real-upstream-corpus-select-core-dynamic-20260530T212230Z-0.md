## real-upstream-corpus-select-core-dynamic-20260530T212230Z-0

Base accepted HEAD: `0c8f3edfb501039f3334d15acf03c96514063bb1`.

Added `SQLiteRealUpstreamSelectCAliasDistinctDynamicTest.php`, a real upstream select-core corpus slice based on `/home/claude/port-libs/.upstream-cache/libsqlite/test/selectC.test`.

Upstream scenarios cited:

- `selectC-1.1` through `selectC-1.11`: SELECT-list aliases and concatenated expressions in `WHERE`, `GROUP BY`, and `HAVING`.
- `selectC-4.2`: DISTINCT subquery projection preserves repeated visible `a` values after deduplicating `(a,b)`.

Focused behavior:

- 11 direct upstream scenario assertions over generic `t1` rows.
- 750 dynamic alias predicate cases varying alias-vs-expression, `WHERE` vs `HAVING`, unary alias lookup, and `IN`/equality predicate forms.
- 249 DISTINCT subquery projection cases varying LIMIT over the upstream `t_distinct_bug` shape.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCAliasDistinctDynamicTest.php`
- Result: `1 test files, 4035 assertions, 0 failures`
- Selected PASS-line delta: `+1011`

Non-overlap:

- This slice uses `selectC.test` alias resolution and DISTINCT subquery projection behavior. It does not repeat accepted SELECT E/F generated aggregate names, select1 repeated wildcard, select2/select3/select4/select5/select7/select8/select9/selectA/selectB/selectD/selectG/selectH batches, grouped SELECT text, expression ORDER BY, JSON table SELECT sources, or WordPress-shaped scenarios.

Dependency closure:

- No new support component is needed. The slice reuses the existing native `SQLiteSelectSql` executor and generic row-array sources.

Root harness:

- Not run; isolated micro-slice.
