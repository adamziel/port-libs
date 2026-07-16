# pragma-foreignkey-integrity-current-source-next86

This slice adds table-valued `pragma_foreign_key_check(...)` current-source
coverage beside the existing statement-form `PRAGMA foreign_key_check(...)`
path.

Behavior:

- `SQLitePragmaForeignKeyIntegrity::executeTableValued()` accepts bounded
  `SELECT * FROM pragma_foreign_key_check(...)`,
  `schema.pragma_foreign_key_check(...)`, and direct
  `pragma_foreign_key_check(...)` rowset calls.
- Quoted table-valued arguments such as `'archive.wp_options'` resolve to the
  attached schema target instead of being treated as one malformed table name.
- `SQLitePragmaIntegrityCurrentNextYield` now pages table-valued FK PRAGMA
  rows together with integrity/quick-check rows via
  `pageForForeignKeyTableValuedPragma()`.
- Application smoke coverage exercises copied `wp_options` preflight queries
  where an unqualified table-valued PRAGMA resolves the temp/current schema
  while a quoted attached target resolves `archive.wp_options`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIntegrityCurrentSourceNext86Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-foreignkey-integrity-current-source-next86.php --self-test`
  - `application-pragma-foreignkey-integrity-current-source-next86 self-test passed`

Non-overlap:

This avoids the accepted batch82 statement-form
`SQLitePragmaForeignKeyIntegrity::execute()` schema-qualified
`PRAGMA foreign_key_check(...)` behavior and the accepted paged
integrity/FK/index rows. The new surface is the table-valued PRAGMA rowset
entrypoint plus quoted qualified argument parsing for that rowset form.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local
schema catalog, FK integrity, and current/next pagination primitives.
