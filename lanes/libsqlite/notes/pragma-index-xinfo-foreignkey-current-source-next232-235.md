# PRAGMA index_xinfo foreign-key current-source next232-235

This combined smoke slice keeps the accepted next232-235 PRAGMA/FK diagnostics
visible together:

- next232: child-side FK action indexes must place child key columns at the
  leftmost prefix;
- next233: child indexes with expression terms before FK child columns are
  blockers for FK lookup support;
- next234: expression UNIQUE indexes reported by `PRAGMA index_xinfo` cannot
  satisfy a column parent key;
- next235: DESC flags on a matching UNIQUE parent index remain visible and do
  not block FK parent-key admission.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext232235Test.php
php lanes/libsqlite/examples/application-pragma-index-xinfo-foreignkey-current-source-next232-235.php --self-test
```

No new source component is required. This note documents the cross-slice
Application import smoke only; the individual next232, next233, next234, and
next235 notes remain the detailed behavior handoffs.
