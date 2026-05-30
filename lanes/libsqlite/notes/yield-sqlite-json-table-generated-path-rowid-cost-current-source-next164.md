# JSON table generated path rowid cost current source next164

## Scope

Adds current-source `json_tree` planner behavior for generated-path rowid seek plans that can satisfy `ORDER BY rowid` / `_rowid_` / `oid` / `id` without a temp sorter. The profile records forward and reverse scan direction, LIMIT cost capping, ordered seek rowids, sorter fallback for non-rowid ORDER BY terms, and replan reasons when current/next source admission changes.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext164Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next164.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext164Test.php`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next164.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next164 self-test passed`

## Non-overlap

This slice builds on next161 admission but only adds ORDER BY/LIMIT cost state for pinned current-source rowid seek tapes. It does not repeat accepted JSON table hidden constraints, visible constraints, SELECT/FROM source wiring, cursor behavior, generated path rowid seek/admission tests, or path ORDER BY cost profiles.

## Dependency closure

No new support component is needed. The patch reuses the existing native JSON table generated-path, rowid seek, and current-source admission profiles.
