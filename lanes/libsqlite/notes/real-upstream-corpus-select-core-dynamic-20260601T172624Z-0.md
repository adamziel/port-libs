# real-upstream-corpus-select-core-dynamic-20260601T172624Z-0

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select9.test`
- Upstream scenarios: `select9-2.$iOuterLoop.2` through `select9-2.$iOuterLoop.6`, plus the single-column `EXCEPT` / `INTERSECT` cases in the same loop.

Behavior ported:
- Added bounded `REVERSE` collation comparison support for the upstream `db collate reverse reverse` test fixture.
- Added `SQLiteRealUpstreamCorpusSelectCoreDynamic20260601T172624ZTest.php` with 1000 dynamic `select9-2.*` WHERE-filtered compound SELECT LIMIT/OFFSET cases across `UNION`, `UNION ALL`, flipped arm order, `ORDER BY 1`, `ORDER BY 2, 1`, `ORDER BY 2 COLLATE reverse, 1`, `EXCEPT`, and `INTERSECT`.

Non-overlap:
- This owns `select9.test` filtered compound SELECT section `select9-2.*`.
- It avoids accepted `select9-1` compound LIMIT/OFFSET matrix, grouped SELECT, expression ORDER BY, JSON table, pager/WAL, B-tree, and VFS clusters.

Verification:
- Red-first probe before the fix: `SQLiteSelectSql::execute("SELECT * FROM t1 WHERE a<5 UNION SELECT * FROM t2 WHERE d>=5 ORDER BY 2 COLLATE reverse, 1 LIMIT 3", ...)` failed with `Unsupported SQLite ORDER BY collation: REVERSE`.
- `php -l lanes/libsqlite/src/SQLiteSelectResult.php`: no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteAffinityComparison.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260601T172624ZTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamic20260601T172624ZTest.php`: 1 test file, 7010 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9CompoundLimitDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect9SetOpsDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicCompoundCollationTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicSortCollation20260531T085917ZTest.php`: 4 test files, 53475 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test file, 7 assertions, 0 failures.

Dependency closure:
- No new support component is needed. The slice reuses `SQLiteSelectSql` compound execution and extends the existing comparison/collation dispatch to cover the upstream registered reverse test collation.

Expected dashboard movement:
- Focused PHP pass growth: +1002 TestRunner PASS cases from the new file.
- Mapped coverage remains 1589 / 1589.
