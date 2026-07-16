# PRAGMA foreign_key_check + Root Integrity Current Source Next117

This slice adds a source-stable current/next yield for Application-style repair
preflights that need `PRAGMA foreign_key_check(table)` rows and sqlite_schema
rootpage blockers in one pass.

- `SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield` emits rootpage
  integrity blockers first, then targeted `foreign_key_check` rows.
- The source cursor hashes database bytes, the target FK PRAGMA, schema rows,
  and attached-catalog metadata, so stale resume cursors are rejected after
  database/schema/catalog changes.
- The rows preserve duplicate rootpage, pointer-map rootpage, freelist,
  largest-root mismatch, beyond-image, and foreign-key mismatch messages.
- Application smoke:
  `php lanes/libsqlite/examples/application-pragma-fk-root-integrity-current-source-next117.php`
  reports 3 root blockers and 1 stale `wp_options` FK blocker.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyRootIntegrityCurrentSourceNext117Test.php
Focused test run: 1 selected test files (root lock skipped)
70 PASS lines
1 test files, 112 assertions, 0 failures

php lanes/libsqlite/examples/application-pragma-fk-root-integrity-current-source-next117.php
status ok, total 4, integrity_root 3, foreign_key 1
```

Non-overlap: avoids accepted standalone rootpage analysis next111, FK
pointer-map/source cursors next83/next90, table-valued FK next86, PRAGMA
index_xinfo/root diagnostics, and accepted batch109-113 PRAGMA surfaces. This
adds the missing combined current-source resume contract for targeted
foreign-key checks plus sqlite_schema root blockers.

Dependency closure: no new support component is needed. The patch reuses
native SQLite database image parsing, schema row parsing, rootpage integrity
analysis, attached schema catalogs, and foreign-key check primitives.
