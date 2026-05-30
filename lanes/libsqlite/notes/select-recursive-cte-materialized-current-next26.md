# SELECT Recursive CTE Materialized Current Next26

- Slice: `yield-sqlite-select-recursive-cte-materialized-current-next26`.
- Behavior: parser-level `WITH RECURSIVE ... AS MATERIALIZED` and `AS NOT MATERIALIZED` now apply SQLite recursive-term `LIMIT` / `OFFSET` to the recursive queue instead of treating it as a per-step SELECT limit. `OFFSET` rows are skipped from the final recursive table while still driving subsequent recursion; `LIMIT 0` suppresses anchors; negative `LIMIT` remains unbounded; negative `OFFSET` is rejected.
- Upstream evidence: local `sqlite3` oracle confirmed `WITH RECURSIVE seq(x) AS MATERIALIZED (VALUES (1) UNION ALL SELECT x + 1 FROM seq WHERE x < 10 LIMIT 4) SELECT x FROM seq;` returns `1,2,3,4`, while `LIMIT 3 OFFSET 2` returns `3,4,5`.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveCteMaterializedCurrentNext26Test.php` -> `1 test files, 76 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveCteCurrentSourceTest.php lanes/libsqlite/tests/SQLiteCompoundMaterializedCteCurrentNext15Test.php lanes/libsqlite/tests/SQLiteRecursiveCteMaterializedCurrentNext26Test.php` -> `3 test files, 133 assertions, 0 failures`.
- Application smoke: `php lanes/libsqlite/examples/application-select-recursive-cte-materialized-current-next26.php --self-test` verifies copied `wp_options` import-window selection `[3,4,5]` through a bounded recursive materialized CTE.
- Non-overlap: avoids accepted batch23 derived-table materialization, accepted non-recursive CTE materialization, accepted recursive CTE current-source baseline, grouped SELECT SQL text, subquery text, expression ORDER BY, JSON table sources/cursors/constraints, B-tree, WAL, and VFS clusters.
- Dependency closure: no new support component is needed; this reuses the existing native `SQLiteSelectSql` parser/executor and focused PHP runner.
