# JSON Table Left Join Rowid Current Source Next79

Behavior slice: parser-level `json_each()` / `json_tree()` sources now expose
SQLite rowid aliases when the JSON table is the current/base source feeding a
following `LEFT JOIN`.

Implementation:

- `SQLiteSelectSql::sourcePlan()` now normalizes JSON table source rows through
  JSON-aware source helpers before joins are applied.
- Current/base JSON table rows receive `rowid`, `_rowid_`, and `oid` aliases in
  unqualified single-source SELECTs and qualified `alias.rowid` forms when they
  feed a later join.
- Existing joined JSON table nullable right-column handling is preserved, so
  unmatched `LEFT JOIN` rows still NULL-extend `r.rowid`, `r._rowid_`, and
  `r.oid`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableLeftJoinRowidCurrentSourceNext79Test.php
Focused test run: 1 selected test files (root lock skipped)
24 PASS lines
1 test files, 24 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-left-join-rowid-current-source-next79.php
```

Non-overlap: this avoids accepted parser-level JSON table SELECT source/cursor
work, hidden/visible constraint pushdown, host joins, nested joined JSON table
rowid alias handling, JSONB CHECK/path admission, SELECT GROUP/ORDER/subquery
clusters, WAL/VFS/B-tree storage clusters, and Unicode GLOB behavior. The new
surface is the held JSON table left-join regression where the current/base JSON
source itself lacked rowid aliases before the next join consumed it.

Dependency closure: no new support component is needed. The slice reuses the
lane-local parser-level SELECT executor and existing JSON table row materializer.
