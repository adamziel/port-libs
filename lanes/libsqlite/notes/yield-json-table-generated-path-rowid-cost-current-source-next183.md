# JSON table generated path rowid cost current-source next183

Behavior slice: `json-table-generated-path-rowid-cost-current-source-next183`

## Implementation

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext183()`.
- Extends accepted next180 generated-path/rowid materialization with a bounded current-source batch yield profile.
- Records batch size, resume rowid/ordinal, emitted rowids, remaining rowids, EOF state, stale next-source restart state, emit cost, yield tape, and replan reasons.
- Admits materialized current-source rows before a next-source replan, while reset/empty/contradictory materializations and changed next-source profiles force restart/reseek.

## Verification

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext183Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next183.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext183Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next183.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next183 self-test passed`

## Non-overlap

Avoids accepted next180 generated-path/rowid materialization, next178 yield resumption, next176 xFilter, next173 xBestIndex, JSON table SELECT source/cursor wiring, visible/hidden constraint extraction, and JSON aggregate/window work. This slice starts after materialization and models bounded row emission/restart admission.

## Dependency closure

No new support component is needed. The slice reuses native JSON table generated-path/rowid planning, materialized row profiles, rowid aliases, and current-source replan helpers.
