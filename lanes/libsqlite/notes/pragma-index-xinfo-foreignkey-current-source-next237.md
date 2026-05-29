# PRAGMA index_xinfo foreign key current-source next237

This slice adds exact parent UNIQUE-key arity diagnostics for the PRAGMA
`index_xinfo` plus `foreign_key_list` current-source path. SQLite does not
allow a foreign key to use only the leading prefix of a longer UNIQUE index as
its parent key, even when `PRAGMA index_xinfo` shows the prefix columns in the
right order.

Implementation:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` wraps the accepted
  next234 page and appends `foreign_key_parent_prefix_unique` rows.
- Current/next source IDs now include exact-vs-prefix parent key summaries, so
  paged resumes are rejected after schema/index arity drift.
- The WordPress smoke models copied `wp_options` import references where
  `UNIQUE(blog_id, option_name, autoload)` must not satisfy
  `REFERENCES parent(blog_id, option_name)` until an exact UNIQUE index exists.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next237.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next237.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: focused PHP PASS-line growth from the next237
test file. Mapped upstream coverage is unchanged; this is additional focused
PHP behavior over already mapped PRAGMA `index_xinfo` and `foreign_key_list`
inventory.

Non-overlap: avoids accepted next234 expression-parent rejection, next231
expression parent-key diagnostics, next230 pseudo-rowid parent names, next226
missing parent tables, next220 parent collation checks, and earlier
foreign-key action/deferral/current-source pagination clusters. The new
surface is exact parent UNIQUE-key arity, specifically rejection of prefix-only
matches from longer `PRAGMA index_xinfo` key lists.

Dependency closure: no new support component is needed. The slice reuses the
existing schema catalog, `PRAGMA index_xinfo`, `PRAGMA foreign_key_list`, and
current-source cursor plumbing.
