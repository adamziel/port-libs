# yield-sqlite-attach-temp-wal-schema-cache-current-next53

Slice: parser-level prepared SQL text extraction for ATTACH temp/main WAL schema-cache current/next invalidation.

Implementation:
- Extended `SQLiteAttachTempMainWalSchemaCachePlan` with `currentNextSql()`, a bounded SQL-text wrapper that extracts table references from SELECT/FROM/JOIN, INSERT INTO, UPDATE, and DELETE FROM statements, resolves them through existing temp/main/attached schema-cache current/next behavior, and reports per-statement reprepare decisions.
- Added `SQLiteAttachTempWalSchemaCacheSqlCurrentNext53Test.php` with 62 focused PASS cases covering temp shadowing, qualified main/archive WAL schema-cookie invalidation, committed page-1 WAL frame cookies, uncommitted/non-page-1 WAL frames, quoted identifiers, DELETE subqueries, attached schemas, missing schemas/tables, and invalid SQL/schema inputs.
- Added `application-attach-temp-wal-schema-cache-current-next53.php` smoke for copied Application `wp_options` import previews where temp staging shadows main while qualified main/archive statements reprepare after WAL schema-cookie changes.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalSchemaCacheSqlCurrentNext53Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 62 assertions, 0 failures
```

Status delta:
- `phpPass`: +62 focused PASS lines, from 19277 to 19339.
- `benchmarkDenominator.mapped`: +1, from 462 to 463, for the newly mapped prepared SQL text schema-cache invalidation unit.

Dependency closure:
- No new support component is required. This reuses the existing ATTACH schema-cache and WAL page-1 schema-cookie planner primitives.

Non-overlap:
- Avoids accepted current-next49 table-list `SQLiteAttachTempMainWalSchemaCachePlan` behavior by adding SQL-text statement extraction and per-statement reprepare reporting.
- Does not repeat ATTACH temp/VFS open planning, temp/main view-trigger resolution, URI schema-cache reuse, JSON table SELECT source/cursor/constraint work, VFS writer/sync/lock/rollback paths, WAL byte truncation/checkpoint transactions, B-tree page relocation/root collapse/overflow freelist release, grouped SELECT SQL text, expression ORDER BY, or Unicode GLOB clusters.
