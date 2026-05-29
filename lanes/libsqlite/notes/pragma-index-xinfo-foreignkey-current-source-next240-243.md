# PRAGMA index_xinfo foreign-key current-source next240-243

This combined smoke slice keeps the accepted next240-243 PRAGMA/FK diagnostics
visible together:

- next240: omitted parent columns resolve through parent primary-key metadata and
  report arity mismatches;
- next241: implicit parent references resolve to the parent primary-key column
  order while explicit parent columns remain distinguishable;
- next242: rowid aliases are rejected as FK parent keys unless the parent table
  declares that column name;
- next243: child and parent affinity comparisons surface mismatches for FK
  equality checks.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext240243Test.php
php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next240-243.php --self-test
```

No new source component is required. This note documents the cross-slice
WordPress import smoke only; the individual next240, next241, next242, and
next243 notes remain the detailed behavior handoffs.
