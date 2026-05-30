# Real Upstream SELECT Core Dynamic Batch

- Base accepted HEAD: `45c7c0b7038266bad342ad051199ea41c2a0cb28`.
- Source truth: hydrated upstream SQLite tests in `/home/claude/port-libs/.upstream-cache/libsqlite/test`.
- Added file: `SQLiteRealUpstreamSelectCoreDynamicAdditionalTest.php`.
- Upstream files cited: `select2.test`, `select4.test`, `select5.test`.
- Focused behavior: nested SELECT iteration equivalents, large `tbl2` predicate counts, commuted equality predicates, closed range predicates, CROSS JOIN truthiness, compound `UNION`/`UNION ALL`/`EXCEPT`/`INTERSECT` ordering and NULL distinctness, aggregate `GROUP BY`/`HAVING` ordering, empty aggregate return values, NULL grouping, and grouped join counts.
- Non-overlap: existing `SQLiteRealUpstreamSelectCoreDynamicTest.php` covers `select1.test` and `select3.test`; this batch adds non-overlapping `select2.test`, `select4.test`, and `select5.test` cases.
- Focused assertion count: `502` assertions in the new file.
- Focused PASS-line growth: `125` PASS lines in the new file.
- Mapped denominator change: none claimed; this is PHP behavior coverage from already hydrated upstream SELECT scripts.
- Dependency closure: no new support component needed; the existing `SQLiteSelectSql` / `SQLiteSelectQuery` executor is reused.
- Known exclusions for follow-up: `select2.test` CASE predicates in WHERE, one scalar `max(a,b)` join predicate, and `select5.test` non-aggregate/expression `GROUP BY` forms still expose parser/executor gaps and were not hidden as passing coverage.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicAdditionalTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicAdditionalTest.php` passed: `1 test files, 502 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicAdditionalTest.php` passed: `2 test files, 1109 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 3 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.
