# JSON Table Rowid Hidden Constraint Current Source Next84

Slice: `json-table-rowid-hidden-constraint-current-source-next84`.

Behavior:
- Normalizes shorthand JSON table `rowid`, `_rowid_`, and `oid` predicates into the current-source JSON table `id` constraint when parsing `FROM json_each AS j WHERE j.json = ... AND j.rowid = ...` and equivalent `json_tree` SQL.
- Removes the rowid alias predicate with the other hidden current-source constraints after it has narrowed the JSON table rows.
- Keeps remaining aliased visible residual predicates executable by unqualifying the bare JSON table alias after hidden-constraint removal.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenConstraintCurrentSourceNext84Test.php
Focused test run: 1 selected test files (root lock skipped)
33 PASS lines
1 test files, 52 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-rowid-hidden-constraint-current-source-next84.php --self-test
application-json-table-rowid-hidden-constraint-current-source-next84 self-test passed
```

Expected dashboard movement: `phpPass` `31557 -> 31590` after clean integration of this lane patch. Mapped upstream coverage remains `465 / 1589`.

Dependency closure: no new support component is needed; this reuses native PHP JSON table planning, JSON tree/each row materialization, and SELECT SQL predicate execution.

Non-overlap: avoids accepted parser-level JSON table SELECT/FROM sources, JSON hidden `json/root` extraction, visible constraint pushdown, JSON table cursor behavior, left/lateral rowid join handling, JSON aggregate/window work, WAL/VFS/B-tree/storage clusters, SQL subquery/group/order clusters, and Unicode GLOB behavior. The new surface is shorthand current-source `rowid/_rowid_/oid` constraint extraction for bare JSON table sources plus residual alias repair after hidden constraints are removed.

Next task: continue JSON table planner/VDBE behavior beyond accepted source/cursor/visible/hidden/root clusters, preferably dynamic joins or malformed JSONB planner edges with comparable focused PASS growth.
