2026-05-27 - cast affinity comparison corpus next6

- Added bounded parser-level `CAST(expression AS type)` support for SELECT SQL value expressions.
- Implemented explicit cast evaluation for `INTEGER`, `REAL`, `NUMERIC`, `TEXT`, and `BLOB`/`NONE` targets and reused it through projection, WHERE predicates, ORDER BY hidden columns, LIMIT expressions, arithmetic, LIKE/GLOB, and grouped HAVING rewrites.
- Updated SELECT predicate comparison to use SQLite storage-class ordering for non-NULL mixed storage classes instead of treating mixed scalar classes as unknown.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCastAffinityComparisonCorpusTest.php` passed with `1 test files, 68 assertions, 0 failures` and 66 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-select-sql-cast-affinity.php` reports copied `wp_options` option-value casts for numeric comparison, raw lexical comparison without cast, and text storage-rank comparison without requiring `ext/sqlite`.
- Dashboard delta: `lane-status.json` `phpPass` moves from 2017 to 2083, exactly +66 verified PASS lines from the new focused test file. Mapped upstream denominator is unchanged because this is a focused corpus slice, not a newly mapped manifest unit.
- Non-overlap: this avoids accepted SELECT subqueries, comma LIMIT parsing, expression ORDER BY, grouped SELECT SQL text, expression-index range costs, Unicode GLOB ranges, JSON table cursor/source/constraint work, WAL/VFS writer/sync/rollback clusters, and B-tree overflow/page-move/root-collapse clusters.
- Dependency closure: no new support component is needed; the slice reuses existing native PHP SELECT row-array execution and scalar BLOB wrappers.
