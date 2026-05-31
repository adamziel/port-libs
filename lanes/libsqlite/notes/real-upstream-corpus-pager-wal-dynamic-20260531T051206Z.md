# real-upstream-corpus-pager-wal-dynamic-20260531T051206Z

Base accepted HEAD: 7174979f2808c9ccf08c3331545660695c77e192

Added focused real-upstream pager/WAL corpus coverage in
`SQLiteRealUpstreamCorpusPagerWalDynamic20260531T051206ZTest.php`.

Upstream source sections:

- `walrestart.test`: `walrestart-1.*` and `walrestart-2.*` restart/truncate checkpoint reader boundaries.
- `walsetlk.test`: `walsetlk-2.*` blocking lock handoff readmark stability.
- `walsetlk_snapshot.test`: `walsetlk_snapshot-3.*` snapshot readers pin checkpoint progress.
- `walro.test`: `walro-1.*` read-only WAL open sidecar behavior.
- `walnoshm.test`: `walnoshm-4.*` no-SHM reader behavior.
- `walpersist.test`: `walpersist-1.*` and `walpersist-3.*` persistent WAL close and journal-size-limit truncation.
- `pager2.test`: `pager2-14.*` and `pager2-20.*` reader/checkpoint page-cache image boundaries.

Focused evidence:

- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamic20260531T051206ZTest.php`
- Result: `1 test files, 40001 assertions, 0 failures`
- PASS growth: 1001 focused TestRunner PASS cases.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP WAL
parser/checkpoint, reader-visibility, persistent-WAL close, WAL VFS dynamic,
and pager journal-mode helpers.

Non-overlap:

This does not repeat accepted WAL byte truncation, rollback-journal apply,
checkpoint transaction plan, VFS file writer/locked writer/sync/lock-state,
or previous `20260531T045404Z` pager/WAL corpus sections. It covers distinct
`walrestart`, `walsetlk`, `walsetlk_snapshot`, `walro`, `walnoshm`,
`walpersist`, and `pager2` upstream sections.
