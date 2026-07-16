# real-upstream-corpus-btree-index-dynamic-20260601T173642Z-0

## Scope

- Lane: `libsqlite`
- Base accepted HEAD: `334219c7e6cca6231b1d29ea31b8ea2ce0640e69`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/without_rowid5.test`
- Owned upstream sections: `without_rowid5-1.1` through `without_rowid5-6.2`

This slice adds a source-neutral dynamic B-tree/index corpus for SQLite
`WITHOUT ROWID` requirements from upstream `without_rowid5.test`: rowid alias
omission, case-insensitive `WITHOUT ROWID` declarations, invalid table option
keywords, mandatory primary keys, `INTEGER PRIMARY KEY` behavior, implicit
primary-key `NOT NULL`, NOT NULL conflict policies, and incremental blob I/O
rejection.

## Non-overlap

- Does not repeat existing `without_rowid1.test` secondary-index primary-key
  tail coverage.
- Does not repeat existing `without_rowid6.test` / `without_rowid7.test`
  redundant primary-key and collation coverage.
- Does not repeat current `wherelimit.test`, JSON, WAL, VFS, expression, or
  source-neutral cleanup batches.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid5RequirementsDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid5RequirementsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWithoutRowid5RequirementsDynamicTest.php`
  - `1 test files, 20653 assertions, 0 failures`
  - Adds 1204 focused TestRunner PASS cases.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 7 assertions, 0 failures`

## Status Delta

- Expected `phpPass` delta: `6155549 -> 6156753` (`+1204`).
- Mapped coverage remains `1589 / 1589`.
- Broad full-lane/release parity remains blocked by the existing broad failures
  and memory limit behavior; this isolated slice did not run the root harness.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local
`SQLiteBTreeIndexDynamicCorpusPlan` dynamic corpus planner and the existing
TestRunner harness.

## Next Task

Continue B-tree/index real corpus coverage with non-overlapping upstream files
such as `without_rowid2.test`, `without_rowid4.test`, `wherefault.test`, or
`wherelfault.test`, or pivot to source behavior if one exposes a current
WITHOUT ROWID executor/parser blocker.
