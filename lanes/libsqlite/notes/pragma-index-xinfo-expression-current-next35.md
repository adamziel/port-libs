### 2026-05-27 PRAGMA index_xinfo expression current/next next35

This isolated libsqlite slice adds focused coverage for streaming
`PRAGMA index_xinfo` rows over expression indexes through the existing native
PHP row cursor.

Focused behavior:

- `current()` / `next()` walks expression rows with SQLite-style `cid = -2`,
  `name = NULL`, expression collation, and DESC flags.
- Ordinary key columns and rowid / WITHOUT ROWID primary-key auxiliary rows
  continue after expression terms.
- Cursor `rewind()` and `remainingRows()` preserve the opened current-source
  schema even after an attached schema is detached.
- Table-valued `pragma_index_xinfo()` cursor calls follow the same current
  source and explicit schema behavior for expression indexes.
- Missing expression indexes keep SQLite-compatible empty rowsets.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoExpressionCurrentNext35Test.php
Focused test run: 1 selected test files (root lock skipped)
56 PASS lines
1 test files, 69 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pragma-index-xinfo-expression-current-next35.php
```

Dependency closure: no new support component is needed; this reuses
`SQLitePragmaSchemaCatalog`, `SQLiteAttachedSchemaCatalog`, and
`SQLitePragmaRowCursor`.

Non-overlap: this does not repeat accepted PRAGMA table/index cursor row order,
table-valued PRAGMA current-source resolution, expression-index metadata
rowsets, JSON table cursor/source behavior, B-tree page-move/freeblock/
overflow clusters, WAL savepoint/rollback/checkpoint clusters, VFS writer/
lock/sync clusters, or Unicode GLOB work. It adds focused current/next cursor
coverage for expression-index `index_xinfo` rows.
