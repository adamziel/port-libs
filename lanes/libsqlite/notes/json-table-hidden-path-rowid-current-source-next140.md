# JSON table hidden path rowid current-source next140

Behavior slice: adds `SQLiteJsonTablePlan::currentSourceHiddenPathRowidNext140()` for the current-source boundary where a `json_tree()` cursor has both hidden `path = ?` and rowid/`_rowid_`/`oid = ?` constraints. The profile records the exact point-seek row, value fingerprint, source kind, seek tape, miss handling, and current-to-next replan reasons without repeating accepted path/rowid cost, order-by, nested path, or hidden-rowid-order slices.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableHiddenPathRowidCurrentSourceNext140Test.php
```

Application smoke:

```text
php lanes/libsqlite/examples/application-json-table-hidden-path-rowid-current-source-next140.php --self-test
```

Dependency closure: no new support component is needed; this reuses the native JSON table planner, JSONB decoding, hidden path constraints, and rowid alias normalization.

Non-overlap: avoids accepted JSON table hidden `json`/`root` constraints, visible constraints, SELECT source/cursor wiring, lateral rowid behavior, path/rowid cost `next126`, hidden path order `next128`, nested path rowid `next133`, hidden rowid order `next135`, and generated path/order slices.
