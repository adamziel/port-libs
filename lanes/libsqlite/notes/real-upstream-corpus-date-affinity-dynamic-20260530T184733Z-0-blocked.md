# real-upstream-corpus-date-affinity-dynamic-20260530T184733Z-0

Status: blocked for ready handoff under the 2026-05-30 hard throughput floor.

Attempted upstream section:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cast.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`

Existing accepted coverage in this worktree already owns the practical
date/affinity dynamic corpus surfaces for this micro-slice:

- `SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php`
  covers `date.test` `date-2.2c-0..999`, `affinity2.test`
  storage/comparison rows, `affinity3.test` real-division affinity rows, and
  selected `types3.test` manifest-type behavior.
- `SQLiteRealUpstreamDateAffinityModifierDynamicCorpusTest.php`
  covers `date.test` `date-2.2c-0..399`, `date-2.3..2.51`,
  `affinity2.test` `affinity2-100..150`, `affinity2-200..300`, and
  `affinity2-500..507`.
- `SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php`
  covers `date.test` `date-9.1..9.7`, `date-13.11..13.37`,
  `date-16.1..16.31`, and `date-17.1..17.7`, including dynamic overflow and
  underflow sweeps.

Focused verification on the launcher base `7e63d4798cb030955a466f3272d59cba9c03648e`
passed:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityModifierDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 9698 assertions, 0 failures
```

No additional ready patch was emitted because the available non-overlapping
work in this micro-slice did not satisfy any hard handoff gate without
fabricating repetitive PASS rows around already accepted `date.test` and
`affinity2.test` sections. A valid next larger batch should pivot to one of
these instead:

- a runner-map or denominator admission change for the remaining date/cast/type
  upstream scripts, with guarded upstream-runner evidence and no fabricated
  script ids;
- a real behavior fix for the rejected real-cast handoff noted in
  `lane-status.json`, then admit its affected `cast.test`/`types*.test`
  sections as a larger batch;
- a source-neutral cleanup lane if the goal is domain debt removal rather than
  PASS-line growth.

Dependency closure: no new support component was added. Existing
`SQLiteCoreScalarFunction`, `SQLiteAffinityComparison`, and `SQLiteSelectSql`
already cover the attempted date/affinity surfaces; the blocker is
non-overlapping throughput eligibility, not a missing dependency.
