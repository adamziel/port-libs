# ATTACH URI Schema Cache Current Next31

## Behavior

- Adds `SQLiteAttachUriSchemaCache`, a bounded native PHP coordinator for ATTACH file URI schema-cache behavior.
- Reuses loaded schema records only when `cache=shared`, the normalized URI identity matches, and the current schema cookie matches.
- Treats `cache=private` and plain filenames as uncacheable, and reports `next.requires_reload` when the next schema cookie changes.
- Preserves decoded URI path, open-plan metadata, database-list rows, and attached schema resolution through `SQLiteAttachedSchemaCatalog`.

## Focused evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachUriSchemaCacheCurrentNext31Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
47 PASS lines
1 test files, 104 assertions, 0 failures
```

Example smoke:

```bash
php lanes/libsqlite/examples/application-attach-uri-schema-cache-current-next31.php --self-test
application-attach-uri-schema-cache-current-next31 self-test passed
```

## Non-overlap

This slice does not repeat accepted ATTACH temp/VFS open planning, attach schema shadowing, parser-level JSON table SELECT sources, VFS file writer/sync/lock state, WAL checkpoint/savepoint byte truncation, B-tree page relocation/root collapse/overflow freelist release, SQL expression ORDER BY, or grouped SELECT text. It is limited to current/next schema-cookie reuse semantics for ATTACH file URI `cache=shared` opens.

## Dependency closure

No new support component is needed. The slice reuses existing bounded URI parsing (`SQLiteFileUri`), open metadata (`SQLiteOpenPlan`), schema records (`SQLiteSchemaRecord`), and attached schema catalog execution (`SQLiteAttachedSchemaCatalog`).
