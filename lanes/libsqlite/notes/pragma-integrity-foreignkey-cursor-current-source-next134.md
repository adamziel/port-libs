# pragma-integrity-foreignkey-cursor-current-source-next134

This slice extends `PRAGMA foreign_key_check` current-source cursor behavior for
quoted attached schema names that contain dots, including Application-style copied
archive/import schema names such as `wp.archive` and `wp.import.2026`.

Behavior covered:

- statement form: `PRAGMA "wp.archive".foreign_key_check(wp_options)`;
- table-valued form:
  `SELECT * FROM "wp.archive".pragma_foreign_key_check(wp_options)`;
- qualified target form:
  `PRAGMA foreign_key_check('wp.import.2026'.wp_options)`;
- current-source cursor hashes, pagination, stale source rejection, schema row
  hash changes, and malformed dotted schema/table rejection.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyCursorCurrentSourceNext134Test.php`
  - `1 test files, 86 assertions, 0 failures`
  - `52` PASS lines
- `php lanes/libsqlite/examples/application-pragma-integrity-foreignkey-cursor-current-source-next134.php --self-test`
  - `application-pragma-integrity-foreignkey-cursor-current-source-next134 self-test passed`

Non-overlap:

This avoids accepted PRAGMA FK/index integrity next82/86/92/104/117/120/131,
index-xinfo quoted attached schema behavior, rootpage/pointer-map integrity,
JSON table cursor/source work, and the batch109-113 plus batch131 accepted
surfaces. The new behavior is specifically quoted dotted attached schema
admission for `foreign_key_check` current-source cursor resumes.

Dependency closure:

No new support component is needed. The patch reuses the existing native PHP
schema catalog, FK check executor, PRAGMA current-source cursor, and stable
source hashing primitives.
