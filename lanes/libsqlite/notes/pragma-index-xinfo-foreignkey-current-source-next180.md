# pragma-index-xinfo-foreignkey-current-source-next180

This slice adds parent-index candidate diagnostics to the existing
`PRAGMA index_xinfo` plus foreign-key current-source page.

- `SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext180` reuses accepted
  `index_xinfo`, `foreign_key_list`, constraint-name, action, deferral, and
  current/next pagination behavior.
- The new surface explains why a parent key is accepted or blocked:
  `rowid-primary-key`, matching UNIQUE index, partial UNIQUE rejection,
  non-UNIQUE rejection, parent-column order mismatch, and collation mismatch.
- Rows for `index_admission` and `foreign_key_check` are decorated with the
  parent-index reason and candidate rejection counts.
- The source hash includes the parent-index candidate summary, so a cursor
  becomes stale when the repaired UNIQUE parent index appears.

Verification:

```bash
php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext180.php
php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext180Test.php
php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next180.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext180Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next180.php --self-test
git diff --check -- lanes/libsqlite
```

Non-overlap: this avoids accepted next159 FK extraction, next161 implicit
parent-column handling, next165/167 action rows, next170/173 deferral timing,
next175 `foreign_key_list` row shape, next176/177 constraint-name/origin
decoration, and accepted quickcheck/rootpage/integrity PRAGMA clusters. The new
behavior is parent-index candidate classification attached to the current-source
PRAGMA/FK page.

Dependency closure: no new support component is needed. The slice reuses the
lane-local schema catalog, `PRAGMA index_list`, `PRAGMA index_xinfo`,
`PRAGMA foreign_key_list`, and current-source pagination helpers.
