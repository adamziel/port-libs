# pragma-index-xinfo-foreignkey-current-source-next191

This slice adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, a
current-source PRAGMA helper layered on accepted `index_xinfo`,
`foreign_key_list`, null-child, partial-index, collation, and parent-key
metadata. It detects UNIQUE parent indexes whose left prefix matches a
referenced parent key but whose `PRAGMA index_xinfo` key terms include extra
columns. SQLite requires an exact UNIQUE parent key for FK admission, so these
prefix/superset indexes remain blockers until a current/next schema adds the
exact UNIQUE key.

Behavior covered:

- current/next source hashes include parent UNIQUE prefix-superset metadata;
- paged row streams append `foreign_key_parent_superset` rows after accepted
  FK/index rows;
- full UNIQUE superset indexes are blockers while partial UNIQUE superset
  indexes remain diagnostic rows;
- exact next-schema UNIQUE repairs clear the full-superset blockers;
- stale cursor validation catches changed exact parent-key repair state;
- non-prefix UNIQUE indexes are ignored.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next191.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next191.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 59 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next191.php --self-test`
  - `application-pragma-index-xinfo-foreignkey-current-source-next191 self-test passed`

Non-overlap: this avoids accepted next188 partial UNIQUE-only parent-key
admission, next187 partial parent-index diagnostics, next184 parent sort-order
metadata, next181 parent collation admission, next178 parent key column
mapping, and earlier FK/index_xinfo pagination clusters. The new surface is
exact parent-key cardinality: a UNIQUE index on `(referenced_columns, extra)`
does not satisfy `REFERENCES parent(referenced_columns)`.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `index_list`, `index_xinfo`, `foreign_key_list`,
partial-parent, and current-source pagination helpers.
