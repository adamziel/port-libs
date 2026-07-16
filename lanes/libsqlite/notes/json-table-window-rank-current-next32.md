# JSON table window rank current-next32

2026-05-27 isolated slice `yield-sqlite-json-table-window-rank-current-next32`.

- Behavior: adds `SQLiteJsonTablePlan::rankedCurrentNextRows()` as a bounded current/next cursor over already window-ranked `json_each()` / `json_tree()` rows. It preserves current row payloads, next row payloads within the same partition, current/next rank values, peer flags, partition-boundary EOF, and stable row indexes.
- Application smoke: `examples/application-json-table-window-rank-current-next32.php --self-test` verifies copied plugin settings expanded through `json_tree()` and traversed as ranked current/next priority rows with peer ties.
- Focused test delta: `SQLiteJsonTableWindowRankCurrentNext32Test.php` adds 54 focused PASS assertions.
- Non-overlap: avoids accepted JSON table window ranking math, JSON table cursor/source/hidden/visible constraints, JSON host joins, indexed derived current/next lookup, grouped JSON rows, SELECT SQL text/JOIN/GROUP BY/subquery/ORDER BY clusters, and accepted WAL/VFS/B-tree/encoding clusters. This slice is only the current/next traversal layer over ranked JSON table rows.
- Dependency closure: no new support component is needed; the patch reuses existing lane-local JSON table row generation, residual filtering, ordering, and window ranking helpers.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableWindowRankCurrentNext32Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-json-table-window-rank-current-next32.php --self-test
application-json-table-window-rank-current-next32 self-test passed
```
