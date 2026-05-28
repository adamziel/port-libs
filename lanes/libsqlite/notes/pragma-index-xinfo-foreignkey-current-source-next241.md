# PRAGMA index_xinfo/FK current-source next241

Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241`, layered on the
accepted next238 PRAGMA/FK page. The new current-source behavior compares raw
`PRAGMA foreign_key_list` rows whose `to` column is NULL for shorthand
`REFERENCES parent` clauses with the derived parent primary-key resolution used
by the catalog path.

This covers WordPress import/copy DDL where shorthand foreign keys should be
made explicit before schema repair or generated DDL emission:

- inline shorthand `owner_id REFERENCES wp_posts` resolves to `wp_posts.ID`;
- composite shorthand `FOREIGN KEY(term_site, term_slug) REFERENCES wp_terms`
  resolves to the parent primary-key sequence;
- explicit references remain visible as explicit rows and are excluded from
  the implicit count;
- missing parent primary keys are reported by the helper without hiding the
  raw PRAGMA NULL parent-column state.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241Test.php`
  - `1 test files, 77 assertions, 0 failures`
  - 60 focused `PASS` lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next241.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next241 self-test passed`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext241Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next241.php`
  - `No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next241.php`
- `git diff --check -- lanes/libsqlite`
  - clean

Non-overlap:

- Avoids accepted next238 descending parent UNIQUE index admission and accepted
  next217/next220 parent-prefix/collation checks.
- Does not repeat accepted JSON, WAL, B-tree, VFS, SELECT, or encoding
  clusters named by the supervisor overrides.

Dependency closure:

- No new support component is needed. The slice reuses
  `SQLitePragmaSchemaCatalog`, accepted raw `foreign_key_list` parsing, and the
  next238 current-source pagination layer.
