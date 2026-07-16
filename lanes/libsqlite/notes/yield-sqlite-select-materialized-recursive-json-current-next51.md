# yield-sqlite-select-materialized-recursive-json-current-next51

This slice extends `SQLiteSelectRecursiveJsonMaterialization` with
`recursiveCurrentNext` boundary metadata. Each traced recursive CTE current row
now reports:

- the next emitted recursive row;
- json_tree() rows yielded by the current recursive source;
- json_tree() rows for the next recursive source;
- accepted next rows and UNION duplicate skips from the recursive queue.

Focused behavior:

- multiple recursive anchors preserve current/next order;
- `UNION` duplicate option targets are skipped and recorded;
- materialized JSON rows remain scoped to the current recursive source row;
- terminal recursive rows expose `next = null` and empty next JSON rows;
- derived SELECTs can still filter and group the materialized JSON source.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectMaterializedRecursiveJsonCurrentNext51Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 60 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-select-materialized-recursive-json-current-next51.php --self-test
application-select-materialized-recursive-json-current-next51 self-test passed
```

Expected dashboard movement: `phpPass` `18565 -> 18625` from 60 newly verified
focused PASS lines. Mapped upstream denominator is unchanged.

Dependency closure: reuses existing `SQLiteSelectSql` recursive CTE tracing,
parser-level `json_each()` / `json_tree()` source execution, and
`SQLiteJsonTableDerivedIndex`. No new support component is needed.

Non-overlap: this avoids accepted batch48 recursive materialized JSON indexed
row scans by adding recursive CTE boundary-to-JSON yield metadata. It also
avoids accepted JSON table cursor/source/constraint pushdown, SELECT SQL
subqueries, GROUP BY/HAVING, expression ORDER BY, VFS/WAL/B-tree/encoding, and
derived-table materialization clusters.
