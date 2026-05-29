# JSON Table Generated Path Rowid Cost Current Source Next211

## Behavior

- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext211()`.
- Builds on accepted next209 rowid range/order planning and adds a current-source high-water rowid resume profile.
- Records resume/yield/deferred rowids, EOF and blocked-yield states, current/next replan reasons, and bounded cost classes.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext211Test.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next211.php --self-test`
- Dependency closure: no new support component needed; this reuses native JSON table generated-path rowid range/order profiles.

## Non-overlap

Avoids accepted next209 range constraint pushdown, next206 alias order consumption, JSON table SELECT source/cursor behavior, visible/hidden constraints, and JSON host joins. This slice only adds current-source rowid resume/high-water behavior on top of the accepted current-source profiles.
