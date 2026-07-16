# PRAGMA index_xinfo / foreign-key current-source next252

Slice: `pragma-index-xinfo-foreignkey-current-source-next252`

Behavior:

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, layered on the
  accepted next250 PRAGMA/FK page.
- Appends current/next rows for `PRAGMA foreign_key_list.from` child columns
  that are absent even from `PRAGMA table_xinfo`.
- Keeps generated child columns distinct from missing child columns: generated
  columns remain covered by next249/next250, while this slice only reports
  truly absent child columns.
- Includes the missing-child summaries in source hashing, pagination, and
  stale resume-cursor validation.

Application relevance:

Copied Application taxonomy import DDL can reference staging columns that were
not copied into the replay schema. A `foreign_key_list` row still names those
columns, so the import preflight needs a table_xinfo-backed blocker before
schema repair continues.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next252.php --self-test
application-pragma-index-xinfo-foreignkey-current-source-next252 self-test passed
```

Expected dashboard movement:

`phpPass +48`, from `134837` to `134885`. Mapped upstream coverage remains
unchanged because this is focused PHP behavior over the already mapped PRAGMA
`index_xinfo` / `foreign_key_list` family.

Non-overlap:

Avoids accepted next249/next250 generated child-column visibility by only
reporting FK child columns missing from `table_xinfo`. It also avoids accepted
parent UNIQUE arity, expression-index, rowid-alias, partial-index, collation,
action, deferral, SET DEFAULT/SET NULL, and match-name PRAGMA/FK clusters.

Dependency closure:

No new support component is needed. The slice reuses `SQLiteSchemaRecord`,
`SQLitePragmaSchemaCatalog::tableInfo(..., true)`, accepted
`foreign_key_list` extraction, and the current-source PRAGMA pagination chain.
