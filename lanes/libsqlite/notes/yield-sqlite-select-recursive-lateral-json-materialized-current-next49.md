# yield-sqlite-select-recursive-lateral-json-materialized-current-next49

## Scope

Added focused parser-level SELECT SQL coverage for MATERIALIZED recursive CTE rows feeding current `json_each()` / `json_tree()` roots. The slice covers copied Application `wp_options` navigation JSON stored as text JSON and JSONB, recursive object-root queueing, grouped and limited outer scans, recursive trace dependency evidence, and malformed dynamic-root rejection.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectRecursiveLateralJsonMaterializedCurrentNext49Test.php`
  - `1 test files, 83 assertions, 0 failures`
  - 58 PASS lines

## Non-overlap

This does not repeat accepted standalone JSON table cursor/source/hidden/visible constraint work, accepted lateral JSON flattening, or accepted compound recursive CTE materialization. The new surface is the composition where a materialized recursive CTE's current rows become dynamic JSON table roots in the parser-level SELECT executor.

## Dependency closure

No new support component is needed. The slice reuses lane-local `SQLiteSelectSql`, recursive CTE execution, JSONB, and JSON table source machinery; no ext/sqlite or live service dependency is introduced.
