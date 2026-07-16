# real-upstream-corpus-vfs-io-dynamic-20260531T161337Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260531T161337Z-0`

Base accepted HEAD: `8c7b034bb5fb3d061acb6b56e46103da8721d7a6`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/atomic.test`
- Covered scenarios:
  - `atomic.test 1.0`: `CREATE TABLE t1(x, y); BEGIN; INSERT INTO t1 VALUES(1, 2);`
  - `atomic.test 1.1`: `file exists test.db-journal` returns `0` before `COMMIT`
  - `atomic.test 1.2`: `COMMIT` succeeds

This is intentionally separate from existing `io.test` atomic journal admission,
`io.test` multipage fallback, and `atomic2.test` injected fault fallback
coverage. The earlier blocker note for `atomic.test` only had three top-level
rows; this handoff widens it into a 1000-case focused matrix over batch-atomic
device flags, page size, sector size, row counts, index counts, and payload
sizes.

## Implementation

- Added `SQLiteVfsIoDynamicPlan::atomicBatchCommitProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsAtomicCommitDynamicTest.php`.
- Updated `lane-status.json` from `3194686` to `3195690` selected PHP PASS
  cases (`+1004`), with mapped coverage unchanged at `1589 / 1589`.

The profile records the upstream f2fs batch-atomic contract that the rollback
journal path `test.db-journal` does not exist after the `BEGIN` plus `INSERT`
sequence and before `COMMIT`. It also exposes the generic legacy rollback
journal fallback when the `batch_atomic` capability is absent.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicCommitDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicCommitDynamicTest.php`
  - `1 test files, 35038 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomic2BatchFallbackDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicAdmissionDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicBatchDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsAtomicCommitDynamicTest.php`
  - `4 test files, 97183 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - no output, exit 0

## Dependency Closure

No new support component is needed. This reuses existing VFS capability flag
normalization and the current VFS IO dynamic plan surface.

## Non-Overlap

Avoids accepted and existing coverage for `SQLiteVfsFileWriter`, VFS sync/apply,
VFS lock state/process locking, rollback-journal application/commit,
`atomic2.test` fault fallback, and `io.test` atomic admission or multipage
journal fallback. The new behavior is scoped to upstream `atomic.test` batch
atomic commit/no-journal visibility.
