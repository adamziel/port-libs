# pragma-optimize-index-xinfo-current-source-next116

This slice adds a source-fresh row stream that joins `PRAGMA index_xinfo`
metadata to `PRAGMA optimize` table decisions. It lets copied Application
database tooling page through index metadata while carrying the optimize
decision (`analyze`, `skip`, or `unseen`), reason, generated `ANALYZE` SQL,
and current-source token for the owning table.

Focused coverage:

- temp/main/attached `index_xinfo` resolution and table-valued pragma form;
- expression, rowid auxiliary, descending, and collation metadata;
- `PRAGMA optimize` actions for analyzed, skipped, stale, forced, and unseen
  owner tables;
- current-source pagination and stale cursor rejection when index metadata or
  cursor offset changes;
- Application smoke for copied `wp_options` / `wp_postmeta` index repair import
  metadata.

Dependency closure: no new support component is needed. This reuses the
lane-local attached schema catalog and existing `SQLitePragmaOptimizePlan`.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaOptimizeIndexXinfoCurrentSourceNext116Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 67 assertions, 0 failures
PASS_LINES=51
```

Dashboard delta:

- `lane-status.json` `phpPass`: `43574 -> 43625` (+51), matching the verified
  focused PASS-line delta.
- `benchmarkDenominator.mapped`: unchanged at `604 / 1589`; this slice does
  not claim a new manifest-backed upstream row.

Non-overlap: avoids accepted standalone PRAGMA optimize current-source,
standalone index/table info analysis, quick_check/index_xinfo pagination,
PRAGMA table_info analysis, JSON, WAL, VFS, B-tree, encoding, and planner
clusters. The added behavior is the joined optimize/index_xinfo source cursor.
