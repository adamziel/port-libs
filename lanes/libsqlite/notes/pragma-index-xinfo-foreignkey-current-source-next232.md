# PRAGMA index_xinfo / foreign-key current-source next232

This slice adds current/next PRAGMA evidence for child-side foreign-key action
indexes whose columns are present but not in leftmost-prefix order. SQLite can
use a child index to probe rows affected by parent deletes or updates only when
the child FK columns are the leading `PRAGMA index_xinfo` key terms.

Behavior:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` composes the accepted
  next229 current-source page.
- Adds deterministic `foreign_key_child_action_prefix` rows for FK constraints
  with `ON DELETE` / `ON UPDATE` actions.
- Reports `misordered_child_action_index` when all child columns appear in a
  non-partial child index but not as the leftmost prefix, and reports repair
  when the next source adds a leftmost-prefix index.
- Resume cursors include the child-prefix summaries and reject stale repaired
  pages.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next232.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next232.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

Avoids accepted parent-key collation/order/arity diagnostics, partial child
index diagnostics, RESTRICT timing, MATCH-name handling, visible JSON, VFS/WAL,
B-tree, encoding, and SELECT planner clusters. This patch only adds leftmost
child-prefix diagnostics for FK action lookup indexes.

Dependency closure:

No new support component is needed. The slice reuses `SQLitePragmaSchemaCatalog`
`index_list`, `index_xinfo`, and `foreign_key_list` rows plus the accepted
current-source PRAGMA pagination chain.
