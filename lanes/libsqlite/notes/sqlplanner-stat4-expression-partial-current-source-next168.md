# sqlplanner-stat4-expression-partial-current-source-next168

This slice adds focused current-source planner coverage for STAT4-backed partial expression indexes when the query predicate is a simple prefix `LIKE` over `lower(option_name)`.

Behavior covered:

- stale prepared planner fences are rejected when schema/stat4/source signatures change;
- `LIKE 'plugin-%'` is converted into the same lower-inclusive / upper-exclusive prefix range SQLite can use for index scanning;
- the partial-index predicate (`blog_id = 1`, `autoload = 'yes'`, `option_name IS NOT NULL`) must still be implied before the index is admitted;
- STAT4 samples and current rows must agree before the cursor program is emitted;
- non-covering cases stay ready but require deferred table lookup.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext168Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next168.php`

Dependency closure: no new support component is needed; the slice reuses existing native PHP planner, expression normalization, partial-index proof, and STAT4 diagnostic structures.

Non-overlap: avoids accepted next154 equality/IN/BETWEEN row streams, next158 stale-row range exclusion, next161 OR-split probes, next164 explicit range-bounds planning, expression ORDER BY, range-cost, JSON, WAL/VFS, and B-tree clusters.
