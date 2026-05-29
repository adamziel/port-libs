# PRAGMA index_xinfo foreign-key current-source next197

Slice: `pragma-index-xinfo-foreignkey-current-source-next197`.

Behavior: add a current-source diagnostic layer for SQLite foreign-key parent
admission when `PRAGMA index_xinfo` shows that a parent index has exactly the
referenced columns but is not UNIQUE. SQLite does not accept that index as a
parent key, so copied WordPress imports must keep the FK repair blocked until
the parent catalog has a matching UNIQUE index.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext197Test.php`
- `php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next197.php --self-test`

Dependency closure: no new support component is needed. This reuses the
existing `SQLitePragmaSchemaCatalog`, `PRAGMA index_list`, `PRAGMA index_xinfo`,
and current-source pagination helpers.

Non-overlap: avoids accepted PRAGMA partial UNIQUE, expression UNIQUE,
collation, DESC/ASC sort, superset unique, rejected collation, order mismatch,
and next193 parent unique-column-order clusters. This slice only covers
matching non-UNIQUE parent indexes.
