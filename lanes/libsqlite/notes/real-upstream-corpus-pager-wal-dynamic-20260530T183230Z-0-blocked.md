# real-upstream-corpus-pager-wal-dynamic-20260530T183230Z-0

Status: blocked by overlap with accepted current-base pager/WAL corpus coverage.

Accepted base inspected: `2b09fd94bbc734a3a9855d41884522c7a5a06914`.

Attempted upstream source sections:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
  - `walrestart-1.0` initial populate/checkpoint
  - `walrestart-1.1` large update/checkpoint
  - `walrestart-1.2` mxFrame-before-nBackfill race
  - `walrestart-1.4` post-race checkpoint
  - `walrestart-1.5` integrity check after race
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - `pager1-*.1` through `pager1-*.29` multi-client lock transitions
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-1.*` WAL-index header recovery
  - `wal2-2.*` stale checksum-valid WAL-index header reads
  - `wal2-4.1..4.3` xShmOpen requirement
  - `wal2-5.1` checkpoint recovery-before-backfill locks

Overlap found:

- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
  already covers `walrestart-1.*`, `pager1-*`, `wal2-1.*`, and `wal2-2.*`
  through `SQLiteRealUpstreamPagerWalDynamicPlan`.
- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
  already covers `wal2-1.*`, `wal2-2.*`, `wal2-4.1..4.3`, and `wal2-5.1`
  through `SQLiteRealUpstreamPagerWalDynamicCorpusPlan`.
- `lanes/libsqlite/tests/SQLitePagerWalDynamicRealCorpusTest.php` already
  covers representative `wal.test`, `wal2.test`, `walcksum.test`, and
  `pager1.test` checkpoint, recovery, corruption, and hot-journal behavior.

Why no ready patch was emitted:

The current hard handoff floor requires at least 1,000 distinct focused
TestRunner PASS cases, 5,000 behavior assertions, a named blocker fix that
unlocks at least 2,000 PASS cases or 10,000 assertions, or real mapped
denominator movement with guarded upstream-runner evidence. The inspected
pager/WAL dynamic sections above are already represented in focused PHP tests,
and adding another small local batch would be duplicate PASS-line inflation.

Next larger batch to try:

Use a guarded runner-backed exact shard for a disjoint pager/WAL file that is
not already modeled by `SQLiteRealUpstreamPagerWalDynamicPlan` or
`SQLiteRealUpstreamPagerWalDynamicCorpusPlan`, preferably `walpersist.test`,
`walnoshm.test`, `walprotocol*.test`, `walsetlk*.test`, or `pagerfault*.test`.
Before writing PHP cases, produce a current-base inventory of scenario names
that are absent from `lanes/libsqlite/tests/*Pager*Wal*` and verify that the
candidate can reach either 1,000 focused PASS cases or a named runner/parser
blocker that unlocks at least 2,000 PASS cases for the next admitted batch.

Dependency closure:

No new support component was added. The blocker is overlap and throughput
floor compliance, not a missing native PHP dependency.
