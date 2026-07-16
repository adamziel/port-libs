# Real Upstream Corpus VFS I/O Dynamic Slice

- Session: `port-dev-sqlite-yield-dyn-real-vfs-20260530T182503Z`
- Micro-slice: `real-upstream-corpus-vfs-io-dynamic-20260530T182503Z-0`
- Base accepted HEAD: `f9e4e2d5498742752e9304fb10cad66aa60851fc`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Ported scenarios: `io-5.1` through `io-5.11`, default page-size selection from device characteristics and sector size.

## Behavior

Added `SQLiteVfsIoTransactionSequencePlan::defaultPageSize()` to model the upstream `io.test` `io-5.*` behavior:

- no atomic flags select at least 1024 bytes and grow to the VFS sector size, clamped by the configured maximum page size;
- generic `atomic` selects the maximum default page size;
- explicit `atomic512`, `atomic1k`, `atomic2k`, `atomic4k`, and `atomic8k` raise the default only when they fit under the configured maximum;
- explicit atomic flags larger than the maximum do not force an oversized default page;
- sector sizes larger than the current selection round up to the next power of two and clamp to the maximum.

The focused test file keeps the prior `io-2.*`, `io-3.*`, and `io-4.*` checks, adds the exact upstream `io-5.*` table rows, and adds a dynamic matrix over device flags, sector sizes, and maximum page-size bounds. The new section contributes `1053` distinct TestRunner PASS cases and `9485` assertions inside the focused `9680` assertion run.

## Non-Overlap

This slice does not touch accepted VFS atomic pager-cache, rollback-journal apply, VFS writer, sync, lock-state, process-lock, locked-writer, or prior `io.test` `io-2.*` through `io-4.*` coverage. It owns only `io.test` `io-5.*` default page-size behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoTransactionSequenceTest.php`
  - `1 test files, 9680 assertions, 0 failures`
  - New focused PASS cases: `1053`

## Dependency Closure

No new support component is needed. This reuses existing bounded VFS capability and I/O transaction planning helpers in native PHP and does not require ext/sqlite, upstream `testfixture`, live services, or external processes.
