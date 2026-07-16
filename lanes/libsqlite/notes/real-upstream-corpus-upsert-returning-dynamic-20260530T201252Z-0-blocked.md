# real-upstream-corpus-upsert-returning-dynamic-20260530T201252Z-0 blocked

Launcher base: `c1a0d2c80ea721e0595b20a5cbe43c5043856066`.

Attempted upstream domain:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert5.test`

Current accepted coverage already includes the large non-overlapping dynamic
UPSERT/RETURNING surfaces for this slice:

- `SQLiteRealUpstreamUpsertReturningDynamicStatementTest.php` covers
  `returning1.test` `returning1-17.1`, `returning1-17.2`, and
  `returning1-20.1` through `returning1-20.3`.
- `SQLiteRealUpstreamUpsert5FullMatrixTest.php` and
  `SQLiteRealUpstreamUpsertReturningDynamicCatchAllMatrixTest.php` cover
  `upsert5.test` `upsert5-1.1.100` through `upsert5-1.6.505`, including the
  catch-all and `DO NOTHING` matrix.
- Existing dynamic UPSERT/RETURNING files also cite accepted `upsert1`,
  `upsert2`, `upsert3`, `upsert4`, and `returning1` sections around target
  analysis, redundant conflicts, schema variants, priority matrices, tails,
  yield behavior, and multi-row returning behavior.

The remaining obvious real upstream sections in this narrow domain are
bounded tails, mainly `upsert4.test` section 7 alias/excluded references,
section 8 table named `excluded`, section 9 trigger-upsert accumulation, and
small `returning1.test` name-resolution/trigger edge cases not already covered
by the accepted dynamic files. Porting those would be useful, but it is a
small focused batch and would not satisfy any hard handoff gate for a fresh
`real-upstream-corpus-*` worker:

- it would not add 1,000 distinct focused TestRunner PASS cases;
- it would not add 5,000 behavior assertions;
- it would not fix a named behavior/runner blocker that unlocks 2,000 PASS
  cases or 10,000 assertions;
- it would not move mapped denominator coverage with guarded upstream-runner
  evidence.

Next larger batch to try:

- Refill this worker with a broader real-upstream corpus domain outside the
  already accepted UPSERT/RETURNING dynamic cluster, or explicitly assign a
  broad `returning1.test` + `upsert4.test` tail batch with a lower gate.
- If the supervisor wants to keep this exact domain, combine the remaining
  `upsert4.test` sections 7 through 9 with uncovered `returning1.test`
  trigger/name-resolution sections and admit it as a small tail cleanup, not as
  countable high-yield throughput.

Dependency closure: no new support component is needed for the attempted
inspection. The blocker is throughput floor versus available non-overlapping
upstream surface, not missing PHP infrastructure.
