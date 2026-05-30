# JSON Table Generated Path Rowid Cost Current Source Next175

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext175()`.
- Extends the existing generated-path plus rowid current-source chain with a cache-generation profile over the next173 xBestIndex fingerprint.
- Reuses a pinned current-source JSON table cache only when the source generation, source token, rowid argv scope, ordered rowids, and xBestIndex fingerprint remain stable.
- Forces next-source reprepare when a copied `wp_options` JSON source changes generation or generated-path rowset, preventing stale generated-path rowid cache reuse.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext175Test.php`
- Result: `1 test files, 55 assertions, 0 failures`
- Application smoke: `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next175.php --self-test`
- Result: `application-json-table-generated-path-rowid-cost-current-source-next175 self-test passed`

## Non-Overlap

- Does not repeat next161 admission, next168 resumable yield batches, next173 raw xBestIndex costing, JSON visible/hidden constraints, JSON table SELECT/FROM cursor wiring, or accepted JSON table host joins.
- This slice is limited to source-generation cache reuse/invalidation for generated-path rowid current-source plans.

## Dependency Closure

No new support component is needed. The slice reuses native PHP JSON table planning, generated-path validation, rowid cost profiles, and current-source xBestIndex metadata.
