# VACUUM Page-size / Auto-vacuum Corpus Next7

This slice adds `SQLiteVacuumPageSizeAutoVacuumPlan`, a bounded native PHP plan
for the SQLite behavior where pending `PRAGMA page_size` and `PRAGMA
auto_vacuum` settings are materialized by a VACUUM rewrite.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVacuumPageSizeAutoVacuumCorpusTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 107 assertions, 0 failures
```

New focused PASS-line delta: `+52`.

The corpus covers:

- valid SQLite page sizes from 512 through 65536, including the 65536 header sentinel;
- target page-count rounding when a copied database image is rewritten at a different page size;
- `auto_vacuum` `none`, `full`, and `incremental` mode materialization through the header largest-root and incremental-vacuum fields;
- operation/dependency metadata for a VACUUM rewrite;
- round-trip parsing through `SQLiteDatabase` and `SQLitePragmaSnapshot`;
- invalid page-size and auto-vacuum inputs.

Application smoke:

```text
php lanes/libsqlite/examples/application-vacuum-page-size-autovacuum.php
```

The smoke previews a copied Application database VACUUM that rewrites a 1024-byte
page image to 4096-byte pages and enables incremental auto-vacuum without
requiring `ext-sqlite`.

Non-overlap: this does not repeat accepted `SQLiteVacuumBackupSerializePlan`
backup/serialize/VACUUM INTO coverage, auto-vacuum pointer-map page moves,
overflow freelist release, rollback/VFS writer application, grouped SELECT,
JSON table source/cursor/constraint work, Unicode GLOB, or SELECT subquery
clusters.

Dependency closure: no new external support component is needed; the slice
reuses existing native PHP database/header parsing and emits a bounded VACUUM
rewrite plan for later pager/VFS application.
