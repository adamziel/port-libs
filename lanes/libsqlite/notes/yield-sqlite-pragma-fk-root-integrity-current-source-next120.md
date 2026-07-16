# PRAGMA FK Root Integrity Current Source Next120

## Behavior

`SQLitePragmaForeignKeyRootIntegrityCurrentSourceYield` now accepts table-valued `pragma_foreign_key_check(...)` SQL in addition to statement-form `PRAGMA foreign_key_check`. This lets copied Application archive/current-source diagnostics page root-integrity rows together with `SELECT * FROM pragma_foreign_key_check('archive.wp_options')` rows, while preserving current-source hashes, pagination cursors, qualified-schema dispatch, and stale cursor rejection.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyRootIntegrityCurrentSourceNext120Test.php`
  - `1 test files, 80 assertions, 0 failures`
  - `43` focused PASS lines.
- `php lanes/libsqlite/examples/application-pragma-fk-root-integrity-current-source-next120.php`
  - Passed; reports `total: 4`, `integrity_root: 3`, `foreign_key: 1`, and normalized table-valued source SQL.

Expected dashboard movement after clean integration: `phpPass` `46412 -> 46455` (`+43`). Mapped coverage remains `604 / 1589`; this slice is focused PHP behavior coverage and does not claim a new manifest-backed upstream row.

## Non-Overlap

This slice avoids accepted next104 pointer-map FK integrity, next114 rootpage FK enrichment, and next117 PRAGMA-statement FK/root pagination. The new behavior is specifically table-valued FK current-source admission in the next117 root-integrity cursor.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP schema catalog, root integrity analysis, and FK check helpers.
