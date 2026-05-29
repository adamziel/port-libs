# JSON Table Generated Path Rowid Alias Current Source Next174

Slice: `json-table-generated-path-rowid-cost-current-source-next174`

Behavior implemented:

- Adds `SQLiteJsonTablePlan::generatedPathRowidAliasPlan()` on top of accepted next170 generated-path/current-source cost planning.
- Normalizes SQLite JSON virtual-table rowid aliases (`rowid`, `_rowid_`, `oid`) before calling the next170 planner so identical point aliases bind one canonical rowid seek.
- Preserves original alias constraint provenance for xBestIndex-style diagnostics.
- Short-circuits contradictory point aliases to an empty, zero-cost current-source cursor instead of reusing stale pinned rows.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasPlanTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 58 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-json-table-rowid-alias-next174.php
```

Non-overlap:

- Avoids accepted JSON table SELECT-source/cursor, hidden/visible constraint pushdown, generated path rowid next161/next170 cost, and JSON dynamic join surfaces.
- This slice is specifically the virtual-table rowid alias edge for generated-path current-source reuse.

Dependency closure:

- No new support component is needed; this reuses the existing JSON table planner and TestRunner bootstrap.

Next task:

- Continue JSON table planner work on non-overlapping malformed JSONB or dynamic source planning, not rowid alias dedupe.
