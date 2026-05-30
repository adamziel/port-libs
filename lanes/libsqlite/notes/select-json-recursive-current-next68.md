# select-json-recursive-current-next68

Status: focused PHP behavior growth for recursive SELECT current/next JSON table materialization.

This slice adds `SQLiteSelectRecursiveJsonMaterialization::recursiveJsonCurrentNextFrontier()`. The helper exposes each recursive CTE current row, the next queued row, accepted next rows, skipped duplicate rows, and the JSON table rows attached to each boundary. It is intended for Application menu/plugin option imports where recursive `json_each()` traversal feeds materialized `json_tree()` rows and a caller must distinguish the current recursive source from the next queued source.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectJsonRecursiveCurrentNext68Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 86 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-select-json-recursive-current-next68.php --self-test
application-select-json-recursive-current-next68 self-test passed
```

Dashboard delta:

- `phpPass`: `25285` to `25346` for the 61 focused PASS lines verified above.
- `benchmarkDenominator.mapped`: unchanged; this is additive focused runtime coverage over already mapped recursive SELECT/JSON table units, not a new upstream inventory denominator row.

Dependency closure: no new support component is needed. The slice reuses native PHP `SQLiteSelectSql`, JSON table execution, JSONB handling, and the existing recursive JSON materialization helper.

Non-overlap: avoids accepted parser-level JSON table SELECT source/cursor wiring, hidden/visible constraint pushdown, JSON host joins, recursive JSON materialization/window batches 48/51/52/54/64, SQL GROUP BY/subquery/ORDER-expression text dispatch, WAL/VFS/B-tree storage clusters, Unicode GLOB, release-runner evidence, and status-only changes. The new surface is the current/next frontier summary across recursive SELECT rows and their attached JSON table rows.
