# real-upstream-corpus-select-core-dynamic-20260601T095728Z-0

Base accepted HEAD: `c6000a6885bc6b5b6b4980e335c606d935a6fb65`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
- `select6-13.110`: nested `SELECT 1 FROM t2 LEFT JOIN empty1` feeds an inner join, then a comma source before a `RIGHT JOIN`.
- `select6-13.120`: the same chained comma/`RIGHT JOIN` shape with final `WHERE t1.y` filtering.

Patch:

- `SQLiteSelectResult::leftJoin()` now treats a schema-less empty right side as a no-op null-extension when no right-side columns are known. This keeps left rows alive for queries that do not project or filter on the missing right columns.
- Added `SQLiteRealUpstreamSelect6RightJoinEmptyDynamic20260601T095728ZTest.php` with 1,004 distinct focused PASS cases and 4,015 assertions over the real upstream `select6-13.110` and `select6-13.120` shapes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSelectResult.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect6RightJoinEmptyDynamic20260601T095728ZTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6RightJoinEmptyDynamic20260601T095728ZTest.php`: 1 test files / 4,015 assertions / 0 failures; 1,004 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6*Test.php`: 6 test files / 51,426 assertions / 0 failures; 6,329 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 test files / 5 assertions / 0 failures.

Dashboard movement:

- `phpPass`: `5,792,118 -> 5,793,122` (`+1,004` focused PASS cases).
- Mapped denominator: unchanged at `1,589 / 1,589`; this is behavior/PHP-pass growth, not a new upstream inventory row.

Non-overlap:

- Owns `select6.test` `select6-13.110` and `select6-13.120` chained comma/`RIGHT JOIN` behavior with nested empty `LEFT JOIN`.
- Avoids accepted grouped SELECT text, expression `ORDER BY`, select6 derived aggregate/limit batches, JSON table, VFS, WAL, B-tree, pager, PRAGMA, and source-neutral cleanup surfaces.

Dependency closure:

- No new support component needed. The patch reuses `SQLiteSelectSql`/`SQLiteSelectResult`; the only executor change is bounded to schema-less empty `LEFT JOIN` null-extension when no right-side columns are available.
