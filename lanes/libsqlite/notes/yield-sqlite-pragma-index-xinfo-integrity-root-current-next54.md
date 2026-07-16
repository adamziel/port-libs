# PRAGMA index_xinfo integrity root current-next54

Slice: `yield-sqlite-pragma-index-xinfo-integrity-root-current-next54`.

This patch adds a bounded native PHP yield surface that combines resolved
`PRAGMA index_xinfo` rows with root-page integrity diagnostics. It is intended
for copied Application database import diagnostics where the caller needs to page
through index metadata first, then continue into current `integrity_check` or
`quick_check` root-page errors without losing current/next cursor state.

Focused coverage:

- schema-qualified, unqualified TEMP-first, and table-valued `index_xinfo`
  inputs;
- expression, ordinary, descending, collation, and rowid auxiliary
  `index_xinfo` rows;
- current/next page metadata, empty tail pages, offset and limit validation;
- appended `integrity_check` and `quick_check` root diagnostics for largest
  root-page mismatches and `sqlite_schema` root pages beyond the image;
- duplicate root integrity messages are de-duplicated before pagination.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoIntegrityRootCurrentNext54Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

The focused run produced 55 PASS lines. `lane-status.json` `phpPass` increases
from 19277 to 19332 by that verified PASS-line delta.

Application smoke:

```text
$ php lanes/libsqlite/examples/application-pragma-index-xinfo-integrity-root-current-next54.php
{
    "scenario": "copied wp_options PRAGMA index_xinfo plus integrity root current/next pagination",
    ...
}
```

Dependency closure: no new support component is needed. This reuses the
lane-local PRAGMA schema catalog/cursor and integrity-check primitives.

Non-overlap: avoids accepted PRAGMA `index_xinfo` expression metadata,
standalone PRAGMA row cursor, deep integrity root-page checks, pointer-map/
freelist integrity pagination, JSON table source/cursor/constraint work,
SELECT SQL text/subquery/group/order clusters, VFS writer/sync/lock/rollback
clusters, WAL byte/checkpoint/savepoint clusters, B-tree page move/root
collapse/overflow freelist clusters, and Unicode GLOB work. The new behavior is
the combined current/next pagination contract across index metadata and root
integrity diagnostics.
