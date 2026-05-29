# PRAGMA index_xinfo / foreign-key current-source next230

This slice adds current-source PRAGMA diagnostics for explicit foreign keys
that reference SQLite pseudo-rowid names (`rowid`, `_rowid_`, or `oid`) as
parent columns. SQLite requires a parent key to be a named column, so copied
WordPress schemas that use `REFERENCES parent(rowid)` need a blocker distinct
from accepted rowid-alias coverage.

Behavior:

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext` composes the accepted
  next227 current-source page.
- Adds `foreign_key_parent_pseudo_rowid` rows from `PRAGMA foreign_key_list`
  and `PRAGMA table_info`.
- Distinguishes invalid pseudo-rowid parent keys from declared columns named
  `rowid`, `_rowid_`, or `oid`.
- Includes rows in source hashing, pagination, counts, deltas, stale-cursor
  validation, and a copied WordPress postmeta-import smoke.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next230.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php`
- `php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next230.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: avoids accepted rowid-alias parent-key coverage, implicit parent
primary-key arity, parent UNIQUE prefix/collation/permutation checks, child
nullability/action/suffix-index checks, missing parent-table checks, and
accepted PRAGMA index_xinfo/current-source pagination. The new surface is
explicit pseudo-rowid names in `PRAGMA foreign_key_list.to`.

Dependency closure: no new support component is needed. The slice reuses
`SQLitePragmaSchemaCatalog`, `PRAGMA foreign_key_list`, `PRAGMA table_info`,
and the accepted current-source pagination chain.
