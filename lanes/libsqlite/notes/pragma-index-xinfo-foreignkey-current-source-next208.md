# pragma-index-xinfo-foreignkey-current-source-next208

This slice adds current-source evidence for `PRAGMA foreign_key_list` rows that
omit parent-column lists, such as `REFERENCES parent` and
`FOREIGN KEY(...) REFERENCES parent`.

Behavior:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` wraps the accepted
  next206 `index_xinfo`/foreign-key page and appends deterministic
  `foreign_key_implicit_parent_key` rows.
- Omitted parent columns are resolved through `PRAGMA table_info` primary-key
  ordinals so current/next snapshots expose the exact parent primary-key column
  list selected by SQLite.
- Cursor source IDs include the implicit parent-key summary, rejecting stale
  pagination cursors when the parent primary key changes.
- The WordPress smoke models copied multisite `wp_options` rows whose shorthand
  FK references resolve to a renamed parent primary-key column between schema
  snapshots.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 65 assertions, 0 failures`
  - `56` focused PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next208.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next208 self-test passed`

Non-overlap:

This avoids accepted next206 rowid-alias parent-key coverage, next203 parent
unique-index coverage, next195 permuted UNIQUE parent indexes, next194 partial
child-index diagnostics, and next175 row-level `foreign_key_list` pagination.
The new surface is omitted parent-column shorthand resolution against parent
primary-key ordinals.

Dependency closure:

No new support component is needed. The slice reuses the existing schema
catalog, `PRAGMA table_info`, `PRAGMA foreign_key_list`, accepted next206
pagination, and current/next source hashing primitives.
