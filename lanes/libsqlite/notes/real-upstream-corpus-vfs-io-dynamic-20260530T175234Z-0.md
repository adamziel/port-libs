# Real upstream corpus VFS I/O dynamic

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T175234Z-0`
Base accepted HEAD: `e35ca6042fb93bdd5d8709bbc17efa06e6d9c2b0`

## Upstream source truth

Hydrated upstream file read from `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`.

Owned real upstream scenarios:

- `io.test` `io-2.6.1` through `io-2.6.4`: append-page transactions on atomic-capable devices defer rollback-journal creation until commit and roll back if the journal path cannot be opened.
- `io.test` `io-2.7.1` through `io-2.7.6`: multi-file commit forces journal admission even on atomic-capable devices and rolls all files back on commit failure.
- `io.test` `io-2.8.1` through `io-2.8.3`: explicit rollback before journal materialization restores the previous committed rows.
- `io.test` `io-2.9.1` through `io-2.9.3`: atomic-write optimization is gated by sector size relative to page size.
- `io.test` `io-2.10.1` through `io-2.10.3`: specific `atomic1k` and `atomic2k` device flags admit only compatible page sizes.
- `io.test` `io-2.11.1` and `io-2.11.2`: exclusive locking keeps the rollback journal unlinked for the covered insert commits.

## Behavior added

Extended `SQLiteVfsIoDynamicPlan` with `atomicJournalAdmission()` for upstream-backed VFS/pager journal admission decisions. The helper records atomic capability, sector-size gating, append-page and multi-file fallback, deferred journal materialization, commit-failure rollback status, explicit rollback visibility, and exclusive-locking journal suppression.

## Verification

- Accepted-base comparison: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicBeforeTest.php` passed `1 test files, 2894 assertions, 0 failures` before the temporary comparison file was removed.
- Focused patched test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicTest.php` passed `1 test files, 3069 assertions, 0 failures`.

## Countability

This handoff owns `+175` focused PHP assertions in an existing real upstream corpus test file. It does not claim mapped denominator growth, release/all parity, or upstream Tcl runner pass rows.

## Dependency closure

No new support component is needed. The implementation reuses existing VFS device flag normalization from `SQLiteVfsCapabilityPlan` and stays inside the generic libsqlite VFS/pager model.
