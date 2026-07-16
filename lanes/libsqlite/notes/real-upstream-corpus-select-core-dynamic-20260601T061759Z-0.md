# real-upstream-corpus-select-core-dynamic-20260601T061759Z-0

Added `SQLiteRealUpstreamSelect7ArityErrorsDynamic20260601T061759ZTest.php` as an additive real upstream SELECT-core dynamic corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select7.test`
- `select7-5.1` through `select7-5.4`: `IN` subqueries must return exactly one column, including wildcard and compound subquery forms.
- `select7` testprefix `8.1` and `8.2`: compound SELECT arms with different result-column counts are rejected before an outer `WHERE`, aggregate wrapper, or query-plan wrapper hides the mismatch.

Focused coverage:

- 1,002 new focused TestRunner PASS cases.
- 18,014 behavior assertions.
- 1,000 dynamic generic table variants exercise `SQLiteSelectSql` IN-subquery arity validation and compound-arm result-width validation across `UNION`, `UNION ALL`, `INTERSECT`, and `EXCEPT`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect7ArityErrorsDynamic20260601T061759ZTest.php`
- Result: `1 test files, 18014 assertions, 0 failures`.

Non-overlap:

- This owns `select7.test` arity-error behavior from `select7-5.*` and the `8.1` / `8.2` compound-width checks.
- It does not repeat accepted `select7` grouped CASE/type-affinity, correlated `EXCEPT`, `selectG` large `VALUES`, `selectH` omit-unused, expression `ORDER BY`, JSON, WAL, VFS, B-tree, PRAGMA, or metadata-only runner rows.
- Mapped denominator remains unchanged because `select7.test` is already present in the hydrated upstream runner-map evidence.

Dependency closure:

- No new support component needed; this reuses `SQLiteSelectSql` IN-subquery and compound SELECT arity validation.
