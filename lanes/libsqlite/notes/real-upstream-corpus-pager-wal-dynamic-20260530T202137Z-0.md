# real-upstream-corpus-pager-wal-dynamic-20260530T202137Z-0

Base accepted HEAD: `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`.

Added `SQLitePagerWalDynamicCorpusTest.php` as a real upstream pager/WAL corpus
batch. It cites and ports behavior from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  (`wal2-6.4.*`, `wal2-13.*`, `wal2-14.*`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walbig.test`
  (`walbig-1.*`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walbak.test`
  (`walbak-3.*`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walckptnoop.test`
  (`1.0` through `1.10`)
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pageropt.test`
  (`pageropt-2.*`)

The PHP tests exercise native WAL header/frame checksum parsing, committed
frame boundaries, transaction recovery boundaries, checkpoint database image
materialization, reader snapshot page lookup, base-page replacement, and
rollback-tail truncation across 512, 1024, 2048, 4096, and 8192 byte pages.

Focused result:

`php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerWalDynamicCorpusTest.php`

`1 test files, 4369 assertions, 0 failures`

Distinct focused PASS cases: `3365`.

Mapped denominator movement: none claimed. This is PASS-line growth over real
hydrated upstream pager/WAL scenarios.

Dependency closure: no new support component is needed; the slice reuses the
existing native PHP WAL parser/recovery/checkpoint support.
