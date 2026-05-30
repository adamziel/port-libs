# JSON Table Generated Path Rowid Cost Current Source Next215

## Behavior

Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext215()` as a layer after the accepted generated-path rowid `xCurrent` next212 profile. The new profile records the rowid yielded from the pinned current source, the next rowid to resume at, EOF-after-yield state, reprepare/materialize/empty-yield opcodes, cost class, transition tape, and next215 replan reasons.

This covers a Application-style copied `wp_options` JSON diagnostics path where a generated-path `json_tree()` cursor can emit rowid `7`, preserve resume rowid `8`, and force a reprepare when the next source changes generation/path fingerprints.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext215Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next215.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext215Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next215.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next215 self-test passed`

## Non-Overlap

This slice does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint pushdown, next207 limit behavior, next208 final cost, next209 range constraints, or next212 xCurrent row materialization. It consumes next212 output and adds the subsequent generated-path rowid yield/resume cost behavior.

## Dependency Closure

No new support component is needed. The patch reuses native JSON table generated-path rowid current-source, range, alias projection, and source-fingerprint metadata.
