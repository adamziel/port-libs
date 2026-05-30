# JSON table nested path rowid current source next133

Implemented `SQLiteJsonTablePlan::currentSourceNestedPathRowid()` for
the current/next planner case where a `json_tree()` cursor uses a source-row
`base_root` plus `nested_path` and also carries a hidden rowid alias
constraint (`rowid`, `_rowid_`, or `oid`).

The helper reuses the current nested hidden-cost planner, then records a
rowid-scoped profile for the composed current and next nested roots: normalized
rowid constraint signature, scoped rowid tape, relative fullkeys under the
nested root, point/miss cost class, transition fields, and next133 replan
reasons.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableNestedPathRowidTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 52 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-nested-path-rowid.php
```

Dependency closure: no new support component is needed. This reuses native PHP
JSON table planning, JSON path composition, JSONB/subtype validation, hidden
rowid alias normalization, and row-array JSON table filtering already present
in the lane.

Non-overlap: avoids accepted JSON table source/cursor wiring, hidden and
visible constraint extraction, current-source nested path planning next121,
path/rowid cost next126, nested hidden-cost next129, JSON aggregate/window
work, and accepted WAL/B-tree/VFS surfaces. The new surface is the
current-source nested-path rowid tape profile and replan evidence for next133.
