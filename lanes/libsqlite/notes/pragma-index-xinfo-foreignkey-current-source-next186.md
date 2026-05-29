# PRAGMA index_xinfo foreign-key current-source next186

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
PRAGMA helper layered on next183 that keeps child-side foreign-key index
collation visible beside `PRAGMA index_xinfo` and `foreign_key_list` rows.

Behavior covered:

- Derives child table column collations from `CREATE TABLE` column definitions.
- Compares those collations with the matching child-key index prefix reported by
  `PRAGMA index_xinfo`.
- Distinguishes `ok`, `collation_mismatch`, and `missing_child_index` states.
- Decorates accepted child-index rows with child-column collation status.
- Carries current/next counts, blocking state, repaired deltas, source hashing,
  cursor paging, table-valued `pragma_index_xinfo(...)`, and malformed-input
  guards.

WordPress relevance:

- The smoke models taxonomy/post relationship imports where child-key indexes
  exist but were created without the `NOCASE` / `RTRIM` collations declared by
  the copied WordPress columns. The helper now identifies those as repairable
  PRAGMA admission blockers instead of treating every child index prefix as
  equivalent.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 76 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next186.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next186.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure:

- No new support component is needed. This slice reuses
  `SQLitePragmaSchemaCatalog`, accepted next183 child-index prefix rows, and the
  existing current-source cursor model.

Non-overlap:

- Avoids accepted parent-key collation next182, child-index prefix next183,
  PRAGMA optimize/index_xinfo/table_info analysis, recursive foreign-key catalog
  output, pointer-map integrity checks, and queued WAL/VFS/B-tree/JSON planner
  surfaces. The new behavior is specifically child-side FK index collation
  admission.
