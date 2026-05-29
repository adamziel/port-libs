# PRAGMA Foreign Key Recursive Schema Reparse

## Behavior

- Consolidates the generated numbered foreign-key-list schema reparse API into the stable `SQLiteAttachedSchemaCatalog::foreignKeyListAfterSchemaReparse()` method for table-valued `pragma_foreign_key_list(...)` schema catalog snapshots.
- The current cursor remains stable after schema records are replaced, while the next cursor reparses the owning schema and exposes recursive self-reference rows.
- Covers recursive composite foreign keys (`FOREIGN KEY(previous_option, fallback_option) REFERENCES wp_option_dependency(...)`) without overlapping PRAGMA integrity/check pagination.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyRecursiveSchemaReparseTest.php`
  - `1 test files, 64 assertions, 0 failures`
  - `41` PASS lines.
- `php lanes/libsqlite/examples/wordpress-pragma-fkey-recursive-schema-reparse.php --self-test`
  - `wordpress-pragma-fkey-recursive-schema-reparse self-test passed`

## Non-Overlap

Avoids accepted PRAGMA quickcheck/index_xinfo integrity, foreign-key integrity pagination, pointer-map integrity, attach/schema trigger reprepare, and queued PRAGMA foreign-key integrity pointer-map slices. This patch is schema-catalog `foreign_key_list` cursor/reparse behavior only.

## Dependency Closure

No new support component is needed. The slice reuses the existing bounded schema catalog, PRAGMA row cursor, and create-table foreign-key parser.
