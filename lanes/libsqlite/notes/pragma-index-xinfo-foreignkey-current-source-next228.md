# PRAGMA index_xinfo foreign-key current-source next228

Slice: `pragma-index-xinfo-foreignkey-current-source-next228`.

Behavior: SQLite accepts a parent UNIQUE index as a foreign-key parent key when
the parent columns and collations match, even if `PRAGMA index_xinfo` reports
`desc=1` for one or more key columns. This adds a current/next diagnostic layer
that keeps the `desc` flags visible without turning DESC parent-key indexes into
false FK blockers.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIndexXinfoForeignKeyCurrentSourceNextTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 66 assertions, 0 failures
```

WordPress smoke:

```text
$ php lanes/libsqlite/examples/wordpress-pragma-index-xinfo-foreignkey-current-source-next228.php
wordpress-pragma-index-xinfo-foreignkey-current-source-next228 self-test passed
```

Status delta: adds 51 focused PASS lines in one new lane-scoped test file. No
fresh mapped upstream denominator row is claimed; this is PHP behavior coverage
over a current-source PRAGMA/FK compatibility edge.

Dependency closure: no new support component is needed. The slice reuses the
existing native `SQLitePragmaSchemaCatalog`, `PRAGMA index_xinfo`, and
`PRAGMA foreign_key_list` parsing.

Non-overlap: avoids accepted next218 RESTRICT timing, next223 MATCH-clause
diagnostics, next224 parent-key collation matching, batch107/108
PRAGMA optimize/index_xinfo/table_info analysis, and batch199 next220-next224
PRAGMA index_xinfo/foreign-key coverage. The new behavior is specifically the
DESC sort-order compatibility of parent UNIQUE indexes.
