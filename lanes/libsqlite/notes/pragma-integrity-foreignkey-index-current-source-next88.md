# pragma-integrity-foreignkey-index-current-source-next88

This slice adds a schema-aware combined PRAGMA integrity/FK/index current-source
collector for copied Application databases with `main`, `temp`, and attached
option archives. It reuses the existing native FK parent-index admission,
`foreign_key_check`, and `integrity_check` primitives, but keeps the owning
schema and current-source provenance on every paged row.

Behavior covered:

- combined index-admission, live FK violation, and integrity rows across
  attached schemas in database-list order;
- schema-prefixed messages so temp/archive `wp_options` violations do not look
  like main-schema failures;
- page offsets over mixed schema rows;
- blocker summaries for parent UNIQUE-index gaps, FK violations, and integrity
  rows;
- rejection of negative offsets, zero limits, and schemas not attached to the
  current catalog.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyIndexCurrentSourceNext88Test.php`
- `php -l lanes/libsqlite/src/SQLitePragmaIntegrityForeignKeyIndexCurrentSourceYield.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyIndexCurrentSourceNext88Test.php`
- `php -l lanes/libsqlite/examples/application-pragma-integrity-foreignkey-index-current-source-next88.php`
- `php lanes/libsqlite/examples/application-pragma-integrity-foreignkey-index-current-source-next88.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted next81 raw combined streams, next82
schema-qualified FK targets, next86 table-valued `pragma_foreign_key_check`,
PRAGMA pointer-map/freelist integrity pagination, or accepted B-tree/VFS/WAL
storage clusters. The new surface is the current-source schema preservation
for combined FK parent-index admission plus FK/integrity pagination.

Dependency closure: no new support component is needed; the patch reuses the
lane-local schema catalog, PRAGMA FK/index integrity, and integrity-check
helpers.
