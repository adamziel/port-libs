# Real upstream pager/WAL dynamic apply corpus

Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T223208Z-0`

Base accepted HEAD: `9f789d799d368a95f9314c9ed366646dd5d17143`

Added `SQLiteRealUpstreamPagerWalDynamicApplyCorpusTest.php`, a focused dynamic
corpus with 1,001 TestRunner PASS cases and 5,001 assertions. The cases port
distinct pager/WAL behavior from the hydrated upstream SQLite checkout:

- `walpersist.test` `walpersist-1.*`: persistent WAL lifecycle after
  close/reopen keeps the committed prefix and discards post-commit draft tail.
- `walprotocol2.test` `2.0..2.5`: concurrent reader protocol keeps restart/full
  checkpoints busy while preserving WAL bytes.
- `waloverwrite.test`: repeated updates to the same database page checkpoint
  the last committed WAL frame.
- `pagerfault.test` `pagerfault-1..36`: fault/corrupt tail handling recovers
  the committed WAL prefix and uses the checkpoint database for the next image.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicApplyCorpusTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicApplyCorpusTest.php`
  passed with `1 test files, 5001 assertions, 0 failures`.

Non-overlap:

This batch avoids the accepted sixteenth rapid sweep surfaces called out in
`lane-status.json`: pager/WAL protocol-no-shm and rollback-savepoint dynamics,
noop/restart/overwrite-restart matrices, and WAL persist mode follow-up. It
uses a new file and focuses on persistent committed-prefix recovery,
walprotocol2 busy-reader checkpoint protocol, repeated-page overwrite final
frame selection, and pagerfault corrupt-tail committed-prefix recovery.

Dependency closure:

No new support component is needed. The batch reuses existing native
`SQLiteWal` checksum, transaction recovery, checkpoint, and reader snapshot
helpers against synthetic page images derived from real upstream scenario
semantics.
