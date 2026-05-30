# yield-sqlite-select-recursive-materialized-json-current-next48

This slice adds `SQLiteSelectRecursiveJsonMaterialization`, a bounded helper for
parser-level `WITH RECURSIVE` SELECTs whose recursive arm yields through
`json_each()` and whose final materialized current source expands reachable
rows through `json_tree()`.

Behavior covered:

- recursive current rows are traced from the materialized CTE queue;
- reachable copied `wp_options` JSON rows are materialized through
  parser-level SELECT/FROM/JOIN execution;
- materialized JSON rows are indexed by option/attribute with stable
  `current` / `next` pairs ordered by JSON `fullkey`;
- derived outer SELECTs can filter, group, order, and limit the materialized
  recursive JSON rows.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectRecursiveMaterializedJsonCurrentNext48Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 60 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-select-recursive-materialized-json-current-next48.php --self-test
application-select-recursive-materialized-json-current-next48 self-test passed
```

Expected dashboard movement: `phpPass` `17373 -> 17433` from 60 newly verified
focused PASS lines. Mapped upstream denominator is unchanged.

Dependency closure: reuses existing `SQLiteSelectSql`,
`SQLiteJsonTableDerivedIndex`, recursive CTE trace support, and
`json_each()`/`json_tree()` planning. No new support component is needed.

Non-overlap: this avoids accepted derived-table materialization, indexed
derived JSON table current/next scans, recursive DML CTE cycles, compound
recursive CTE materialization, lateral JSON flattening, JSON table cursor/source
wiring, JSON visible/hidden constraint pushdown, SELECT SQL subqueries,
GROUP BY/HAVING, expression ORDER BY, VFS/WAL/B-tree/encoding clusters, and the
batch23 derived materialization surface. The new surface is the intersection of
recursive SELECT queue materialization with JSON table current/next scans for
reachable Application JSON import rows.
