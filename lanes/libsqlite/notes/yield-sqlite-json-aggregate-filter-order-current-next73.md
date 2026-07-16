# SQLite JSON Aggregate FILTER/ORDER Current Next 73

## Behavior

- Adds parser-level `SQLiteSelectSql` support for `json_group_array()` and `jsonb_group_array()` as grouped or implicit aggregates.
- Supports aggregate-local `ORDER BY <column>` and `FILTER (WHERE ...)` clauses before final projection, so aggregate input order is independent from final SELECT ordering.
- Preserves JSON subtype and JSONB input values through SELECT execution and returns JSONB aggregate output as `SQLiteBlobValue`.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateFilterOrderCurrentNext73Test.php`
  - `1 test files, 37 assertions, 0 failures`
  - 23 focused PASS lines.
- `php lanes/libsqlite/examples/application-json-aggregate-filter-order-current-next73.php`
  - local Application smoke for copied `wp_options` aggregate summaries.

## Non-Overlap

This avoids accepted JSON object aggregate/window, JSON table cursor/source/constraint, JSONB CHECK optional-path, and recursive JSON SELECT materialization slices. It is limited to parser-level JSON array aggregate `FILTER`/`ORDER BY` execution for SELECT result summaries.

## Dependency Closure

No new support component is needed. The slice reuses existing native `SQLiteSelectSql`, `SQLiteGroupedAggregate`, `SQLiteJsonAggregate`, and `SQLiteJsonB` components.
