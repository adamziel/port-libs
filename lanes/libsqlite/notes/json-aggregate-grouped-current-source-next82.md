# JSON Aggregate Grouped SELECT Current-Source Next82

Status: focused current-source rework for parser-level grouped SELECT JSON aggregate summaries.

## Behavior

- `SQLiteSelectSql` now collects JSON aggregate summary plans from grouped `HAVING` and final `ORDER BY` expressions, not only from the projected SELECT list.
- This lets grouped SELECTs filter or sort by `json_group_array(...)` / `jsonb_group_array(...)` without projecting that aggregate column.
- The rewriter also follows `NOT` predicate terms and unary/collated expression operands when finding or rewriting aggregate expressions.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonAggregateGroupedCurrentNext82Test.php`
  - `1 test files, 40 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-aggregate-grouped-current-next82.php`
  - prints copied `wp_options` grouped JSON aggregate HAVING/ORDER BY smoke JSON.

## Non-Overlap

This does not repeat accepted JSON aggregate DISTINCT projection coverage, JSON object aggregate/window coverage, parser-level GROUP BY/HAVING text, expression ORDER BY, JSON table cursor/source/constraint behavior, VFS/WAL/B-tree storage clusters, or Unicode GLOB work. The new surface is the missing current-source materialization of JSON aggregate summaries referenced only by grouped `HAVING` or final `ORDER BY`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP SELECT SQL parser, grouped aggregate executor, JSON aggregate helpers, JSONB encoder/decoder, and Application smoke harness.
