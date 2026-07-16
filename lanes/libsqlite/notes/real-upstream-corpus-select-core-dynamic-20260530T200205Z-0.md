# real-upstream-corpus-select-core-dynamic-20260530T200205Z-0

- Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`.
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`.
- Ported section: `select1-17.1` and `select1-17.2`, the Chromium 922312 FROM-clause subquery `ORDER BY` / `LIMIT` behavior.
- Added PHP test: `lanes/libsqlite/tests/SQLiteRealUpstreamSelect1DerivedOrderLimitDynamicTest.php`.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1DerivedOrderLimitDynamicTest.php` passed with `1 test files, 7066 assertions, 0 failures` and 1011 focused PASS cases.
- Non-overlap: avoids accepted SELECT2 / SELECT7 / SELECT8 / SELECT9 / SELECTA / SELECTB / SELECTD, grouped SELECT, JOIN text, expression ORDER BY, subquery predicate, compound collation, and comma-LIMIT clusters. This slice only covers `select1.test` FROM-derived `ORDER BY` / `LIMIT` preservation.
- Red-first blocker found and excluded from the ready patch: `select1-17.3` compound derived subquery with `UNION ALL ... ORDER BY y,z LIMIT` currently fails in `SQLiteSelectSql` with `SQLite SELECT SQL compound ORDER BY term does not match a result column`. A future slice can fix compound subquery result-column resolution and then admit that section.
- Dependency closure: no new support component needed; the existing `SQLiteSelectSql` row-array executor is reused.
