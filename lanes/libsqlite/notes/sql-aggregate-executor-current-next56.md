# sql-aggregate-executor-current-next56

Behavior slice:
- Adds parser-level `count(DISTINCT column)` aggregate arguments in `SQLiteSelectSql`.
- Allows grouped `count(*)` execution without requiring a value column.
- Covers grouped and implicit aggregate SELECT text through projection, HAVING, ORDER BY, LIMIT/OFFSET, CTE, compound-arm, and Application copied `wp_options` paths.

Focused evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectAggregateCurrentNext56Test.php`
- Result: `1 test files, 43 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-select-aggregate-current-next56.php`
- Result: `application-select-aggregate-current-next56 self-test passed`.

Dashboard delta:
- `phpPass` increases by exactly `+43`, from `20008` to `20051`, based on the focused PASS lines above.
- No mapped upstream denominator movement is claimed.

Non-overlap:
- Avoids accepted parser-level GROUP BY/HAVING pipeline, composite GROUP BY, SQL expression ORDER BY, SELECT subqueries, JSON table source/cursor/constraints, WAL/VFS transaction application, B-tree page/freelist clusters, and Unicode GLOB work.
- This is limited to aggregate argument parsing and executor summary behavior for `count(*)` and `count(DISTINCT column)`.

Dependency closure:
- No new support component is needed.
- Reuses existing native PHP `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteGroupedAggregate` execution paths.
