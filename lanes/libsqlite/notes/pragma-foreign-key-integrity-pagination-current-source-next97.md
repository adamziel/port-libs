# PRAGMA foreign-key integrity pagination current-source next97

Slice: `pragma-foreign-key-integrity-pagination-current-source-next97`.

This patch tightens resumable `PRAGMA foreign_key_check` + integrity pagination
current-source validation. `SQLitePragmaIntegritySourceCursor` now includes the
attached schema catalog in the source token, including database order and
schema records. That prevents a stale `next` cursor from continuing after a
TEMP schema starts shadowing `main.wp_options` while the SQL text, database
bytes, and FK row data remain otherwise unchanged.

Focused behavior:

- direct `PRAGMA foreign_key_check('wp_options')` pagination source IDs include
  a catalog hash;
- table-valued `pragma_foreign_key_check('wp_options')` pagination source IDs
  include the same catalog-sensitive current-source token;
- stale next cursors are rejected after TEMP schema shadowing changes
  unqualified target resolution from `main` to `temp`;
- schema hashes remain stable when only catalog source resolution changes.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityPaginationCurrentSourceNext97Test.php`
  - `1 test files, 45 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-fk-integrity-pagination-current-source-next97.php --self-test`
  - `application-pragma-fk-integrity-pagination-current-source-next97 self-test passed`

Non-overlap:

This does not repeat accepted PRAGMA foreign-key row generation, table-valued
FK checks, pointer-map/freelist integrity pagination, FK/index admission, or
batch86/batch88/batch90 PRAGMA current-source row surfaces. The new behavior is
catalog-sensitive stale-cursor prevention for resumable FK integrity pages.

Dependency closure:

No new support component is needed. This reuses the existing attached schema
catalog, FK integrity, and current/next source cursor primitives.
