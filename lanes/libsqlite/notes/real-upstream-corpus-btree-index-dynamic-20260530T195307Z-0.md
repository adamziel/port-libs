# real-upstream-corpus-btree-index-dynamic-20260530T195307Z-0

- Base accepted HEAD: `a279204339e8bc1ec8d0d4db06bea5b6a6d043b5`.
- Added `SQLiteRealUpstreamBtreeIndexNumericAffinityDynamicTest.php` as a real upstream B-tree/index corpus batch.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`.
- Ported upstream scenarios:
  - `index-15.2`: NUMERIC-affinity exponent/string values are stored as integer, real, or text values and sort through index key order as `{13 14 15 12 8 5 2 1 3 6 10 11 9 4 7}`.
  - `index-15.3`: indexed `typeof(a) IN ('integer','real')` keeps only numeric storage classes in the upstream order.
- Focused growth:
  - `1001` distinct TestRunner PASS cases.
  - `10009` focused behavior assertions.
- Non-overlap:
  - This slice does not touch page relocation, root collapse, overflow freelist release, freeblock materialization, expression-index range costs, PRAGMA/schema, JSON, WAL, VFS, or suite-evidence metadata.
  - It adds PASS-line growth only; mapped denominator remains unchanged because `index.test` is already in the hydrated upstream runner inventory.
- Dependency closure:
  - No new support component is needed. The batch reuses `SQLiteAffinityComparison`, `SQLiteRecord`, `SQLiteIndexCell`, `SQLiteIndexLeafPage`, and `SQLiteBTreePageHeader`.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexNumericAffinityDynamicTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 10009 assertions, 0 failures
```
