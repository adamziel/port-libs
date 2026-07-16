# Real Upstream Corpus Pager/WAL Dynamic 20260531

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T043649Z-0`

Accepted base: `0b81729d69877023d4b2607c8a1ffc5fac25bee0`

Added `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531Test.php`
with 1,001 focused TestRunner PASS cases and 22,085 behavior assertions.

Upstream source truth from `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `walhook.test`: `walhook-1.1`, `walhook-1.5`, `walhook-2.3`
- `walblock.test`: `walblock-1.3`, `walblock-2.1`
- `walckptnoop.test`: `walckptnoop-1.2`, `walckptnoop-2.4`
- `walrestart.test`: `walrestart-2.1`, `walrestart-3.2`
- `walpersist.test`: `walpersist-1.10`, `walpersist-2.3`
- `pagerfault.test`: `pagerfault-21`
- `pagerfault2.test`: `pagerfault2-2-pre1`
- `pagerfault3.test`: `pagerfault3-pre2`
- `pager1.test`: `pager1-9.4`
- `pager4.test`: `pager4-2.2`

Behavior exercised:

- WAL transaction recovery keeps the committed prefix and discards valid
  uncommitted fault tails.
- Committed transaction ranges and hook-style commit frame counts remain
  stable across varied page sizes and salts.
- `noop`, passive, full, restart, and truncate checkpoints preserve the same
  plan/result relationship exposed by the native WAL primitives.
- Reader-visible page images before and after checkpoint planning stay
  inspectable across blocked-reader, restart, persistence, fault, and pager
  visibility cases.
- Persistent WAL close planning is covered for enabled/disabled and
  journal-size-limit cases.

Non-overlap:

This does not add manifest-only rows and does not repeat the accepted
20260530 real-pager dynamic file. It targets upstream `walhook`, `walblock`,
`walckptnoop`, `walrestart`, `walpersist`, `pagerfault`, `pagerfault2`,
`pagerfault3`, `pager1`, and `pager4` sections with a new 20260531 test file.

Dependency closure:

No new support component is required. The batch reuses existing native PHP
`SQLiteWal`, `SQLiteWalHeader`, checkpoint, transaction recovery, reader
visibility, and persistent-WAL close primitives.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamic20260531Test.php`
  passed: 1 test file, 22,085 assertions, 0 failures, 1,001 PASS lines.
