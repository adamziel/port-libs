# PRAGMA index_xinfo / foreign-key current-source next233

## Behavior

This slice adds a current-source diagnostic for child foreign-key indexes whose
FK columns are only reachable after one or more expression key terms in
`PRAGMA index_xinfo` output. SQLite requires the child-key lookup to use the
leftmost index prefix; an index such as `lower(meta_key), post_id` does not
serve `FOREIGN KEY(post_id)` even though the child column appears in the index.

The helper reports:

- the child FK rows from `PRAGMA foreign_key_list`;
- the child index key terms from `PRAGMA index_xinfo`;
- the expression terms that precede the FK columns;
- current/next counts and repaired/changed deltas.

## Verification

```text
$ php -l lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php
No syntax errors detected in lanes/libsqlite/src/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext.php

$ php -l lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php

$ php -l lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next233.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next233.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 61 assertions, 0 failures
48 PASS lines

$ php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next233.php --self-test
wordpress-pragma-index-xinfo-foreignkey-current-source-next233 self-test passed
```

## Non-overlap

This does not repeat accepted next230 parent pseudo-rowid handling, next227
generic suffix child-index handling, next212 partial child-index action lookup,
or next194 partial child-index diagnostics. The new rows are specifically for
expression key terms (`cid = -2`, `name = NULL`) that precede child FK columns
and explain why a WordPress staging index must be reordered.

## Dependency Closure

No new support component is needed. The slice reuses the existing schema
catalog, `PRAGMA foreign_key_list`, `PRAGMA index_xinfo`, and current-source
pagination helpers.
