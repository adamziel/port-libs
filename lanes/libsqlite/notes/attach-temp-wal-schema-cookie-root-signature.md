# attach-temp-wal-schema-cookie-root-signature

Status: consolidation replay for ATTACH/temp/WAL schema-cookie current-source validation.

This surface composes the accepted WAL/temp schema-cookie source planner with schema root-page signatures so copied WordPress imports can distinguish:

- WAL page-1 checkpoint movement where the numeric schema cookie and root signature are unchanged, so prepared statements can be reused.
- Temp rollback-journal or attached WAL schema changes where the numeric cookie is unchanged but root pages or table presence changed, so statements expire with `SQLITE_SCHEMA`.
- Qualified main statements that remain stable despite WAL source movement, unqualified statements shadowed by a new temp table, write statements blocked before retry, and read statements that can be reprepared.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCookieRootSignatureTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 65 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-attach-temp-wal-schema-cookie-root-signature.php --self-test
wordpress-attach-temp-wal-schema-cookie-root-signature self-test passed
```

PASS delta: no new assertion-count claim; this consolidation preserves the existing direct coverage while removing numbered operation/test/example names. `lane-status.json` is intentionally unchanged. Mapped upstream coverage is unchanged because this is an attach/schema-cookie current-source composition over already mapped primitives.

Non-overlap: avoids accepted ATTACH WAL/temp rollback routing, WAL/temp schema-cookie routing, schema-cache routing, trigger/view cache current-source slices, VFS file-control, WAL restart/truncate, JSON table/source work, and B-tree/pager accepted clusters. The preserved surface is same-cookie source movement versus same-cookie root-signature DDL changes.

Dependency closure: no new support component is needed. The slice reuses the lane-local attached schema catalog, WAL page-1 schema-cookie source planner, and temp rollback-journal schema-cookie diagnostics.

Next task: wire the same root-signature distinction into the broader parser/executor prepared-statement cache once the native connection owns attached file handles directly.
