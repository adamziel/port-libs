# JSON table generated-path rowid current-source yield next218

This slice adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext218()`.
It extends the accepted generated-path rowid range/xCurrent chain with a current-source yield
gate that only reuses `xCurrent` output when the observed source generation and source
fingerprint still match the materialized row.

Behavior covered:

- Reuses generated-path `json_tree` rowid output for the current source when rowid aliases,
  requested projection columns, row materialization, source generation, and source fingerprint
  are all stable.
- Forces reprepare when the next source changes generated path/current-source fingerprint
  before stale rowid output can be yielded.
- Forces materialization when a requested output column was not in the active projected row.
- Preserves rowid alias normalization for `rowid`, `_rowid_`, and `oid`.
- Preserves the next212 range/xCurrent replan reasons while adding next218 source, rowset,
  row, admission, and cost reasons.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext218Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 67 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-json-table-generated-path-rowid-cost-current-source-next218.php --self-test
wordpress-json-table-generated-path-rowid-cost-current-source-next218 self-test passed
```

Non-overlap:

- Avoids accepted/queued JSON table visible and hidden constraint pushdown, SELECT/FROM source
  wiring, cursor rewind/EOF behavior, generated-path rowid range next209, and xCurrent
  materialization next212.
- This patch only gates final current-source yield admission after xCurrent has already
  materialized a generated-path rowid row.

Dependency closure:

- No new support component is needed. The slice reuses the native JSON table generated-path,
  rowid range, alias projection, xCurrent, and source fingerprint metadata already present in
  the lane.
