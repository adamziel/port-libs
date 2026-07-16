# real-upstream-corpus-btree-index-dynamic-20260530T212255Z-0

Base accepted HEAD: `0c8f3edfb501039f3334d15acf03c96514063bb1`.

This slice adds a non-overlapping B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid6.test`
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid7.test`
- Upstream sections covered:
  - `without_rowid6-100` through `without_rowid6-140`
  - `without_rowid6-200` through `without_rowid6-220`
  - `without_rowid6-300` through `without_rowid6-320`
  - `without_rowid6-500` through `without_rowid6-600`
  - `without_rowid7-1.0` through `without_rowid7-3.6`
- Focus: WITHOUT ROWID primary-key B-tree de-duplication, 1000 distinct recursive-row primary-key/aux-index probes, redundant UNIQUE/PRIMARY KEY autoindex coalescing, composite primary-key metadata, duplicate collated key slots, rowid-name rejection in WITHOUT ROWID primary keys, and missing-collation errors during primary-key/index access.
- New focused TestRunner PASS cases: `1007`.
- Focused behavior assertions: `55094`.
- Expected public pass movement after acceptance: `760877 -> 761884`.
- Non-overlap: avoids accepted B-tree page relocation, overflow freelist release, bulk overflow freeblocks, index-interior merge, index8 order/limit, autoindex1 planner, index7 partial-index mutation, and numindex/indexfault batches already present in the accepted B-tree/index corpus.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowidBtreeIndexDynamicTest.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowidBtreeIndexDynamicTest.php`
  - `1 test files, 55094 assertions, 0 failures`
  - `1007` selected PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - pass
- `git diff --check -- lanes/libsqlite`
  - pass

Dependency closure: no new support component is required. The batch reuses existing lane-local corpus-plan arrays and the existing focused PHP TestRunner.

Root harness: not run - isolated micro-slice.
