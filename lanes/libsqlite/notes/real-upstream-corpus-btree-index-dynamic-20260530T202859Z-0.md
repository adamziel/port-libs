# Real Upstream Corpus B-tree Index Dynamic 20260530T202859Z-0

Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`.

This slice expands the existing real upstream B-tree/index dynamic corpus for
SQLite upstream `test/indexA.test` sections `indexA-2.1` and `indexA-3.1`.
The expanded focused PHP coverage keeps rowid and WITHOUT ROWID partial-index
affinity combinations distinct across 80 generated upstream-derived batches,
instead of the previously admitted 50 batches.

Countable movement:

- Before: `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed with
  `6713` PASS lines and `96960` assertions.
- After: `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passes with
  `7913` PASS lines and `118320` assertions.
- Delta: `+1200` focused TestRunner PASS cases and `+21360` behavior
  assertions.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
  passed: `1 test files, 118320 assertions, 0 failures`.

Non-overlap:

- The slice only increases the already real-upstream `indexA.test` partial
  affinity matrix depth in `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- It does not add metadata-only admission rows, fake upstream script ids,
  WordPress-specific APIs, dashboard edits, or new source compatibility
  wrappers.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded
  `SQLiteBTreeIndexDynamicCorpusPlan::indexAPartialAffinityMatrixCases()`
  corpus generator and the existing PHP TestRunner path.
