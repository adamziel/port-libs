# FTS5 schema import current next26

Adds bounded native PHP planning for imported `CREATE VIRTUAL TABLE ... USING fts5(...)`
statements used by Application schema copies. The slice parses FTS5 columns,
`UNINDEXED` columns, `tokenize`, `prefix`, `content`, `content_rowid`,
`detail`, and `columnsize` options, then reports shadow-table names and
external-content rebuild admission.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteFts5SchemaImportCurrentNext26Test.php
```

Expected Application smoke:

```sh
php lanes/libsqlite/examples/application-fts5-schema-import-current-next26.php
```

Non-overlap: this is schema-import planning for FTS5 virtual-table DDL. It does
not repeat accepted FTS5 rank/snippet corpus search, PRAGMA module metadata,
JSON table cursor/source/constraint work, SELECT SQL text/subquery/GROUP BY/
ORDER BY work, VFS writer/sync/lock/rollback clusters, WAL checkpoint/
savepoint clusters, B-tree page move/root-collapse/overflow freelist work, or
the batch23 upstream/release countability evidence.

Dependency closure: no new support component is required. The slice reuses
lane-local SQL tokenization and schema-record planning. Follow-up activation
gate is parser-level virtual-table execution for `MATCH` against imported FTS5
schemas or a distinct upstream FTS release blocker.
