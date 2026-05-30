# PRAGMA index_xinfo / foreign-key current-source next249

Slice: `pragma-index-xinfo-foreignkey-current-source-next249`

Behavior:

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, layered on the
  accepted next246 PRAGMA/FK page.
- Appends current/next diagnostic rows for `PRAGMA foreign_key_list.from`
  child columns that are generated columns visible through
  `PRAGMA table_xinfo` but omitted from `PRAGMA table_info`.
- Distinguishes virtual (`hidden = 2`) and stored (`hidden = 3`) generated
  child columns, carries not-null metadata, includes row summaries in the
  source hash, and rejects stale resume cursors after schema drift.
- Verifies next-source repair when copied Application taxonomy import schemas
  replay generated FK child columns as ordinary visible columns.

Application relevance:

Copied Application taxonomy/meta staging schemas may derive normalized FK child
keys such as `slug_key AS (lower(raw_slug))`. A `table_info`-only diagnostic
can lose those child columns even though `PRAGMA foreign_key_list` reports
them. This slice keeps the FK child side countable through `table_xinfo`.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 60 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next249.php --self-test
application-pragma-index-xinfo-foreignkey-current-source-next249 self-test passed
```

Expected dashboard movement:

`phpPass +60`, from `131296` to `131356`. Mapped upstream coverage is unchanged
because this is focused PHP behavior over the already mapped PRAGMA
`index_xinfo` / `foreign_key_list` family.

Non-overlap:

Avoids accepted next245/next246 generated parent-key and generated parent-column
diagnostics by checking generated child columns from `foreign_key_list.from`.
Also avoids accepted parent UNIQUE arity, expression-index, partial-index,
rowid-alias, collation, child-index prefix/action/nullability, and
foreign-key timing clusters.

Dependency closure:

No new support component is needed. The slice reuses `SQLiteSchemaRecord`,
`SQLitePragmaSchemaCatalog::tableInfo(..., true)`, accepted
`foreign_key_list` extraction, and the current-source PRAGMA pagination chain.
