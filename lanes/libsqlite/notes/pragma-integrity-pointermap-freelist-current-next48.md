# PRAGMA Integrity Pointer-map Freelist Current Next48

This slice adds a bounded native PHP yield surface for deep
`PRAGMA integrity_check` pointer-map and freelist diagnostics. It filters the
existing integrity checker down to pointer-map/freelist rows, annotates each row
with the affected page and pointer-map page where available, and paginates the
result with current/next48 metadata for copied Application SQLite repair UIs.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPointerMapFreelistCurrentNext48Test.php
```

Output:

```text
Focused test run: 1 selected test files (root lock skipped)
53 PASS lines
1 test files, 53 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-pragma-integrity-pointermap-freelist-current-next48.php
```

The smoke reports a copied `wp_options` repair preflight with 57 pointer-map
diagnostics, a first page of 48 rows, and a second complete page of 9 rows.

Non-overlap:

- Avoids accepted `SQLitePragmaIntegrityCheck` whole-check corpus,
  `SQLitePragmaIntegrityForeignKeyYield` current/next32 combined integrity/FK
  pagination, batch37 PRAGMA integrity pointer-map diagnostics, B-tree page
  relocation/root-collapse/overflow freelist release, VFS writer/sync/lock, WAL
  checkpoint/savepoint byte truncation, and JSON/SELECT accepted clusters.
- The new surface is specifically resumable pointer-map/freelist integrity
  diagnostics for current/next48 repair pagination.

Dependency closure: no new support component is needed; the helper reuses the
existing native PHP SQLite header, database page, pointer-map, and integrity
checker primitives.
