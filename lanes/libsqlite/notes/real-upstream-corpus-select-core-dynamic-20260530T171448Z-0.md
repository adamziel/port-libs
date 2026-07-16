# real-upstream-corpus-select-core-dynamic-20260530T171448Z-0

- Base accepted HEAD: `6a6cf1aff10d18a35ed78eace2a787cb40f2b02d`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`.
- Ported scenarios: `e_select-0.1.1` ON join-constraint paths, `e_select-0.1.2` USING join-constraint paths, `e_select-0.1.3` unconstrained join paths, selected `e_select-0.2` SELECT core FROM/WHERE/DISTINCT/ALL/GROUP BY/HAVING paths, `select5-1.0` through `select5-4.5` aggregate GROUP BY/HAVING and empty aggregate behavior, `select5-6.1`, `select5-6.2`, `select5-7.2`, `select5-8.1`, and `select5-8.2`.
- Behavior fix: `SQLiteSelectSql` now parses `==` as SQLite equality and allows `CROSS JOIN` with ordinary `ON`/`USING` constraints by lowering the constrained join to the existing inner-join predicate path.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicJoinAggregateTest.php` passed `1 test files / 1004 assertions / 0 failures / 51 PASS lines`.
- Non-overlap: this avoids accepted SELECT JOIN text, GROUP BY text, expression ORDER BY, subquery, JSON table source/cursor, and status-only suite evidence slices by covering real upstream SELECT core join-constraint syntax and aggregate GROUP BY/HAVING behavior from distinct upstream files.
- Dependency closure: no new support component needed; this reuses the native PHP SELECT parser/executor, predicate, join, aggregate, and row-array query components already in `lanes/libsqlite/src`.
