# Real upstream corpus VFS IO transaction sequence

Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T174707Z-0`

Accepted base: `e12ceba2fd83282957420709bd781aee710bc7ca`

## Upstream source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
  - `io-2.9.1` through `io-2.9.3`: atomic-write optimization is disabled when the VFS sector size is larger than the database page size.
  - `io-2.10.1` through `io-2.10.3`: specific `IOCAP_ATOMIC1K` and `IOCAP_ATOMIC2K` byte ceilings decide whether the rollback journal is created.

## Ported behavior

- Extended `SQLiteVfsIoTransactionSequencePlan::transactionSequence()` with an optional VFS sector-size argument.
- Modeled atomic byte ceilings for `atomic512`, `atomic1k`, `atomic2k`, `atomic4k`, `atomic8k`, `atomic16k`, `atomic32k`, and `atomic64k`.
- Preserved the existing generic `atomic` behavior while making rollback-journal creation explicit when a requested atomic path is unavailable because the sector/page geometry or atomic byte ceiling cannot cover the write.
- Added 9 focused TestRunner PASS cases to `SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`.

Non-overlap: this slice stays within real upstream `io.test` atomic-write sector and `IOCAP_ATOMIC*` behavior. It does not repeat VFS file writer, locked writer, sync apply, rollback-journal apply/commit, appendvfs growth/tiny-open refusal, safe-append journal sizing, default page-size choice, nolock, file-control, or atomic reader-visibility coverage.

Expected dashboard movement: `+9` focused PHP TestRunner PASS lines in an existing real-upstream VFS I/O test file; no mapped denominator change is claimed.

## Verification

- Red-first focused command before the behavior fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`
  - `1 test files, 218 assertions, 3 failures`
- Focused command after the fix:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`
  - `1 test files, 230 assertions, 0 failures`
- PHP lint:
  - `php -l lanes/libsqlite/src/SQLiteVfsIoTransactionSequencePlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`

Dependency closure: no new support component is needed. The slice reuses the existing bounded VFS capability flag map and transaction-sequence planner.
