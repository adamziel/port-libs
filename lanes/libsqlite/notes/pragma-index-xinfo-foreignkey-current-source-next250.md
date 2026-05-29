# PRAGMA index_xinfo / foreign-key current-source next250

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, layered
on the accepted next246 PRAGMA/FK page. It reports foreign keys whose child
columns are generated columns visible through `PRAGMA table_xinfo` but omitted
from `PRAGMA table_info`.

Behavior:

- joins `PRAGMA foreign_key_list` child columns to `table_xinfo` hidden codes;
- distinguishes virtual (`hidden = 2`) and stored (`hidden = 3`) generated
  child columns;
- records current copied WordPress taxonomy import schemas where a generated
  child key can be falsely treated as missing by table_info-only diagnostics;
- verifies next-source repair when the child key is replayed as visible
  columns;
- includes the generated-child summaries in source hashing and resume cursor
  validation.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 55 assertions, 0 failures`
  - `48` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next250.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next250 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next250.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next250.php`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard movement: `phpPass +48` PASS lines from `131296` to
`131344` (`55` assertions). Mapped upstream coverage remains unchanged because
this is focused PHP behavior over the already mapped PRAGMA `index_xinfo` /
`foreign_key_list` family.

Non-overlap:

- avoids accepted next246 generated parent-column diagnostics by only checking
  generated child columns referenced by `PRAGMA foreign_key_list`;
- avoids accepted child-index prefix, collation, order, partial-index, and
  action lookup coverage by reporting child-column visibility through
  `table_xinfo`;
- avoids accepted parent UNIQUE arity, expression-index, rowid-alias,
  collation, generated-parent, action, deferral, and match-name PRAGMA/FK
  clusters.

Dependency closure:

No new support component is needed. The slice reuses `SQLiteSchemaRecord`,
`SQLitePragmaSchemaCatalog::tableInfo(..., true)`, accepted
`foreign_key_list` extraction, and the current-source PRAGMA pagination chain.
