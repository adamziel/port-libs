# PRAGMA index_xinfo foreign-key current-source next228-231

This combined smoke slice keeps the accepted next228-231 PRAGMA/FK diagnostics
visible together:

- next228: `PRAGMA index_xinfo` DESC flags on a matching UNIQUE parent index do
  not block a foreign key;
- next229: a parent key that is only the left prefix of a wider UNIQUE index is
  not an exact parent key;
- next230: explicit references to pseudo-rowid names remain distinct from
  declared columns named `rowid`, `_rowid_`, or `oid`;
- next231: expression UNIQUE indexes reported by `PRAGMA index_xinfo` cannot
  satisfy a column foreign-key parent key.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext228231Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next228-231.php --self-test
```

No new source component is required. This note documents the cross-slice
WordPress import smoke only; the individual next228, next229, next230, and
next231 notes remain the detailed behavior handoffs.
