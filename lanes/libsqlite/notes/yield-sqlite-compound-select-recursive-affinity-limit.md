# Compound SELECT Recursive Affinity LIMIT

- Slice: `compound-select-recursive-affinity-limit`.
- Consolidation: renamed the former numbered current-source implementation to
  `SQLiteCompoundSelectRecursiveAffinityLimitPlan` and replaced its numbered
  public/private methods with descriptive unsuffixed names.
- Behavior: parser-level `WITH RECURSIVE` row production feeding a DISTINCT
  `UNION` compound SELECT keeps SQLite set-operator storage-class boundaries
  (`1`, `1.0`, and `'1'`) before final compound `ORDER BY ... LIMIT/OFFSET`
  pagination is applied.
- WordPress path: copied `wp_options` import staging can recursively walk
  option dependency edges and page a migration preview without collapsing text
  and numeric option metadata too early.
- Non-overlap: avoids accepted batch144 compound UNION limit affinity,
  recursive/window compound, expression ORDER BY, grouped SELECT text, JSON
  table source/cursor/constraint work, WAL/VFS, B-tree, and UTF-16/GLOB
  clusters. This slice combines recursive CTE queue diagnostics with final
  compound LIMIT after UNION and does not touch those accepted helpers.
- Dependency closure: no new support component is needed; this reuses the
  existing native PHP `SQLiteSelectSql` recursive CTE and compound SELECT
  executor.
- Focused evidence:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectRecursiveAffinityLimitTest.php`
  - `php lanes/libsqlite/examples/wordpress-select-sql-compound-recursive-affinity-limit.php`
  - PHP lint for changed PHP files
  - `git diff --check -- lanes/libsqlite`
