# pragma-index-xinfo-foreignkey-current-source-next176

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a current-source
PRAGMA helper that layers on accepted `index_xinfo`, `foreign_key_check`,
action, implicit-key, and timing behavior while deriving foreign-key constraint
names from `CREATE TABLE` DDL.

Behavior covered:

- named inline column constraints such as `blog_id CONSTRAINT "fk site"
  REFERENCES ...`;
- named table constraints with bare, quoted, and bracketed identifiers;
- anonymous table constraints, preserved as `<anonymous>` in source summaries
  and `null` on row diagnostics;
- row decoration for both parent-index admission and `foreign_key_check`
  diagnostics;
- current/next source hashes and cursor validation that change when constraint
  names change even if the inherited `PRAGMA foreign_key_list` shape is stable.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next176.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next176.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 75 assertions, 0 failures`
  - `67` PASS lines

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next176.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next176 self-test passed`

Non-overlap:

This avoids accepted next161/163 implicit parent key and catalog-derived column
metadata, next165/167 action and match metadata, next169/171/173 deferrable
timing, next172 target filtering, accepted PRAGMA optimize/index_xinfo/table
info analysis, recursive FK catalog output, and the next120/root integrity
foreign-key pagination surfaces. The new surface is DDL-derived foreign-key
constraint names carried beside current-source `index_xinfo` and
`foreign_key_check` rows.

Dependency closure:

No new support component is needed. The slice reuses the existing schema
catalog, `index_xinfo`, `foreign_key_list`, current-source cursor, and
foreign-key-check helpers.
