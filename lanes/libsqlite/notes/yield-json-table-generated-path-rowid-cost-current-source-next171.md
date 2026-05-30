# JSON table generated path rowid cost current-source next171

Slice: `json-table-generated-path-rowid-cost-current-source-next171`

Behavior:
- Adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext171()`.
- Composes the accepted next167 generated-path rowid xFilter layer into xNext-style current-source cursor metadata.
- Records cursor opcode, pinned source identity, generated path, argv columns, seek rowids, yielded rowids, skipped post-LIMIT rowids, missing seek rowids, xColumn/xNext/xEof program steps, cost class, cursor fingerprint, and current/next replan reasons.
- Application smoke models copied `wp_options` plugin-rule JSON where the current `json_tree()` cursor yields rowids `[6,5]` from a generated path while rowid `42` is retained as a missing seek, and the shifted next source prepares a fresh cursor.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext171Test.php
php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next171.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext171Test.php
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next171.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 60 assertions, 0 failures
```

Dependency closure: no new support component needed. The slice reuses native PHP JSON table current-source, generated-path rowid-cost, ORDER, xFilter, JSON1/JSONB, and cursor-yield metadata helpers.

Non-overlap: avoids accepted JSON table SELECT source/cursor wiring, hidden/visible constraint extraction, generated-path rowid cost next145/158/160/163/165/167, current-source source-cost and xFilter admission layers, JSON aggregate/window behavior, VFS/WAL/B-tree/storage surfaces, and encoding/collation work. This slice only adds the xNext cursor-yield program above accepted next167 filter metadata.
