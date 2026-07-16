# pragma-quickcheck-index-xinfo-current-source-next103

This slice adds source-aware pagination for the existing combined
`PRAGMA index_xinfo` plus `PRAGMA quick_check`/`integrity_check` root-diagnostic
stream. The new `pageWithSourceCursor()` result records hashes for the database
image, schema catalog, normalized index_xinfo SQL, normalized integrity SQL,
and table-valued mode, then rejects stale resume cursors when any of those
sources change.

Focused coverage:

- direct and table-valued `index_xinfo` streams combined with quick_check root
  diagnostics;
- current/next pagination across index metadata rows and quick_check rows;
- source IDs for database bytes, schema catalog, SQL text, integrity SQL, and
  table-valued mode;
- stale cursor rejection for changed database bytes, changed catalog, changed
  SQL, changed integrity PRAGMA, and mismatched offsets;
- Application copied `wp_options` smoke for paged JSON expression index metadata
  plus quick_check root diagnostics.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaQuickCheckIndexXinfoCurrentSourceNext103Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 73 assertions, 0 failures
PASS_LINES=63

$ php lanes/libsqlite/examples/application-pragma-quickcheck-index-xinfo-current-source-next103.php --self-test
application-pragma-quickcheck-index-xinfo-current-source-next103 self-test passed
```

Dashboard delta:

- `lane-status.json` `phpPass`: `39474 -> 39537` (+63), matching the verified
  focused PASS-line delta.
- `benchmarkDenominator.mapped`: `587 -> 588` for one newly mapped
  PRAGMA current-source cursor evidence row; no upstream execution is claimed.

Dependency closure: no new support component is needed. This reuses the
lane-local schema catalog, PRAGMA row cursor, and integrity-check primitives.

Non-overlap: this avoids accepted PRAGMA index_xinfo expression metadata,
standalone row cursors, root integrity diagnostics, foreign-key/index
current-source pagination, batch99 PRAGMA integrity FK/index checks, JSON,
WAL, VFS, B-tree, encoding, and planner clusters. The new behavior is the
source-fresh resume contract for a mixed `index_xinfo` plus `quick_check`
stream.
