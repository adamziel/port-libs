# PRAGMA index_xinfo / foreign-key current-source next246

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, layered
on the accepted next243 PRAGMA/FK page. It reports foreign keys whose parent
columns are generated columns visible through `PRAGMA table_xinfo` but omitted
from `PRAGMA table_info`.

Behavior:

- joins `PRAGMA foreign_key_list` parent columns to `table_xinfo` hidden codes;
- distinguishes virtual (`hidden = 2`) and stored (`hidden = 3`) generated
  parent columns;
- records current copied Application taxonomy schemas where a generated UNIQUE
  parent key can be falsely treated as missing by table_info-only diagnostics;
- verifies next-source repair when the parent key is replayed as visible
  columns;
- includes the generated-parent summaries in source hashing and resume cursor
  validation.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 55 assertions, 0 failures`
  - `55` focused PASS lines
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next246.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next246 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next246.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next246.php`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected dashboard movement: `phpPass +55` from `127481` to `127536`. Mapped
upstream coverage remains unchanged because this is focused PHP behavior over
the already mapped PRAGMA `index_xinfo` / `foreign_key_list` family.

Non-overlap:

- avoids accepted next243 FK affinity diagnostics by only checking generated
  parent-column visibility through `table_xinfo`;
- avoids accepted generated-column table_xinfo catalog coverage by tying the
  hidden parent columns to `foreign_key_list` rows and current-source PRAGMA
  pagination;
- avoids accepted parent UNIQUE arity, expression-index, rowid-alias,
  collation, child-index, action, deferral, and match-name PRAGMA/FK clusters.

Dependency closure:

No new support component is needed. The slice reuses `SQLiteSchemaRecord`,
`SQLitePragmaSchemaCatalog::tableInfo(..., true)`, accepted
`foreign_key_list` extraction, and the current-source PRAGMA pagination chain.
