# JSON table hidden rowid path current-source

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceHiddenRowidPathPlan()` for the current-source planner boundary where hidden JSON table rowid aliases (`rowid`, `_rowid_`, `oid`) are pinned together with path/fullkey constraints across a current/next copied `wp_options` JSON source.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidPathCurrentSourceTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-hidden-rowid-path-current-source.php --self-test
application-json-table-hidden-rowid-path-current-source self-test passed
```

Non-overlap: this builds on accepted rowid-hidden-path and point-source behavior but adds a new hidden-rowid/path current-source profile with source tokens, alias metadata, pinned rowid/fullkey tapes, point-seek classification, and hidden-rowid path replan reasons. It does not repeat JSON table hidden/visible constraint extraction, parser-level JSON table SELECT source/cursor wiring, generated hidden rowid cost next142, path/generated ORDER work, lateral rowid host joins, WAL/VFS/B-tree clusters, or SQL expression ORDER BY/subquery/GROUP text execution.

Dependency closure: no new support component is needed. The slice reuses native PHP JSON table nested path/rowid planning, JSON path traversal, and residual constraint matching.
