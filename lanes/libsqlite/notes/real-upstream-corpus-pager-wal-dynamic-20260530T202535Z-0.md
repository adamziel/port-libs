# Real Upstream Pager/WAL Dynamic Corpus

- Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T202535Z-0`
- Base accepted HEAD: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
- Ported upstream section: `wal.test` `wal-18.1.*`, checksum-prefix recovery after corrupting the second 32-bit frame checksum word.
- PHP coverage added: `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalChecksumPrefixCorpusTest.php`

The focused PHP corpus adds 125 generated-from-upstream WAL recovery scenarios,
each with 8 distinct TestRunner cases, for 1,000 focused PASS cases. The matrix
varies WAL page size, frame count, valid prefix length, commit-frame positions,
database growth through commit records, recovery byte boundaries, and
checkpoint page-count visibility through `SQLiteWal::transactionRecoveryBoundary()`.

Non-overlap: existing accepted WAL coverage already includes noop checkpoint,
reader snapshots, persist handling, overwrite/restart, transaction recovery, and
checkpoint visibility. This slice specifically owns `wal-18.1` checksum-prefix
recovery dynamics and does not add new WordPress/domain-shaped APIs or
metadata-only runner rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalChecksumPrefixCorpusTest.php`
  - Result: no syntax errors detected
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalChecksumPrefixCorpusTest.php`
  - Result: `1 test files, 1000 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - Result: no whitespace errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Result: not run; guard file is absent in this worktree

Dependency closure: no new support component is needed; the existing
`SQLiteWal` parser/checksum/recovery primitives are reused.
