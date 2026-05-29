# PRAGMA index_xinfo foreign-key current-source next219

## Behavior

- Adds `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext`, extending the accepted current-source PRAGMA index/FK page with diagnostics for a UNIQUE parent index that contains exactly the referenced parent columns but in a different order.
- SQLite requires the referenced parent key columns to match a parent primary key or UNIQUE index in the declared order. This slice reports `foreign_key_parent_key_permutation` rows so a copied WordPress taxonomy/termmeta import does not treat `UNIQUE(slug, blog_id)` as satisfying `REFERENCES parent(blog_id, slug)`.
- The current/next page tracks repaired deltas, stable pagination/resume cursors, and the existing base PRAGMA source hash.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
  - `1 test files, 58 assertions, 0 failures`
  - `48` PASS lines
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next219.php --self-test`
  - `wordpress-pragma-index-xinfo-foreignkey-current-source-next219 self-test passed`

## Non-Overlap

- Avoids accepted next217 parent-key prefix/suffix/partial diagnostics, next181 parent collation checks, next184 parent sort-order reporting, next188/next189 rejected partial/expression UNIQUE parent rows, next194 partial child-index diagnostics, and batch196 parent-prefix coverage.
- This slice only covers full non-partial UNIQUE parent indexes whose column set matches the FK parent column set but whose key order differs.

## Dependency Closure

- No new support component is needed. The implementation reuses `SQLitePragmaSchemaCatalog`, accepted `PRAGMA index_xinfo` rows, accepted `PRAGMA foreign_key_list` rows, and current-source pagination/resume plumbing.
