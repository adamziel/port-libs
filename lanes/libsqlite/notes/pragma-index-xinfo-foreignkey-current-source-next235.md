# PRAGMA index_xinfo foreign key current-source next235

This slice adds current-source coverage for a foreign-key parent-key catalog
edge exposed by `PRAGMA index_xinfo`: descending terms in a UNIQUE parent index
are metadata for ordering and do not disqualify the index from satisfying a
foreign key. The new helper reports `foreign_key_parent_desc_unique` rows so a
copied WordPress taxonomy import can distinguish valid DESC UNIQUE parent keys
from expression, partial, non-unique, or missing parent-key cases.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 61 assertions, 0 failures`
  - 48 focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next235.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next235 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next235.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next235.php`
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

This avoids accepted next231 expression UNIQUE parent rejection, next232 child
action leftmost-prefix diagnostics, next233 child expression-prefix indexes,
next220 parent collation checks, next217 partial/suffix parent key checks,
next229 exact parent arity checks, and the accepted batch205 next233 PRAGMA
index_xinfo/foreign-key coverage. The new surface is specifically the `desc`
column from `PRAGMA index_xinfo` on otherwise valid UNIQUE parent indexes.

Dependency closure:

No new support component is needed. The slice reuses the schema catalog,
`PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, and current-source pagination
helpers already present in the libsqlite lane.
