### 2026-05-27 JSON table indexed derived current-next25

Behavior slice: parser-level `json_tree()` rows are materialized through a
derived SELECT, then indexed by composite derived output columns for
current/next scans. The focused helper preserves typed lookup keys, SQL NULL
lookups, derived row order within each composite key, and terminal no-next rows.

Application smoke:

```bash
php lanes/libsqlite/examples/application-json-table-indexed-derived-current-next25.php --self-test
application-json-table-indexed-derived-current-next25 self-test passed
```

Focused verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableIndexedDerivedCurrentNext25Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures
```

Status delta:

- Focused PHP PASS lines: `+59` from the new lane-scoped test file.
- `lane-status.json` `phpPass`: `8739 -> 8798`.
- Mapped upstream denominator: unchanged; this is focused PHP behavior growth
  for an indexed derived JSON table materialization path, not a fresh upstream
  Tcl/C execution claim.

Non-overlap:

- Avoids accepted parser-level JSON table SELECT source/cursor behavior, hidden
  and visible constraint pushdown, JSON host-row joins, derived-table import
  staging, and batch23 derived materialization coverage.
- This slice is specifically the indexed derived-row lookup/current-next layer
  over materialized JSON table output.

Dependency closure:

No new support component is needed. The slice reuses existing
`SQLiteSelectSql`, `SQLiteJsonTablePlan`, `json_tree()` row generation, and
lane-local PHP row-array execution.
