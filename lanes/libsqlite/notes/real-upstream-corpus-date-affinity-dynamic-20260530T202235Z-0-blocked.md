# real-upstream-corpus-date-affinity-dynamic-20260530T202235Z-0

Status: blocked for ready handoff on current accepted base `a5d711ea245dda1130ca2ff1ba1b791f9a863c2b`.

Upstream source checked:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/types3.test`

Overlap found:

- Existing focused PHP coverage already owns the high-yield date/affinity surfaces for this slice family:
  `date.test` fractional unixepoch milliseconds, modifier validation, timezone/UTC/null handling, floor/ceiling, month-matrix, Gregorian-cycle, and fractional truncation; `date2.test` deterministic CHECK/index/generated-column guards and dynamic indexed predicate rows; `date3.test` auto/unixepoch/julianday modifier order plus a 1000-case unixepoch roundtrip batch; `date4.test` strftime parity through `date4-24858`; `date5.test` Gregorian-cycle JD/calendar roundtrips; `affinity2.test` storage/comparison/unary-ticket behavior; `affinity3.test` REAL left-join division; `types2.test` and `types3.test` affinity matrices.
- The remaining obvious `date.test` sections that are not already represented are not a valid high-yield ready patch as-is:
  `date-6.*` and `date-18.1` depend on SQLite's `SQLITE_TESTCTRL_LOCALTIME_FAULT` localtime shim, not modeled by the current PHP scalar date dispatcher; `date-14.*` mutates on-disk floating-point bytes with `hexio_write`; `date-15.*` needs per-step stable `now` state across delayed function calls; `date-16.*` extreme overflow boundaries and `date-20.*` fractional truncation are small batches below the hard floor unless combined with a broader scalar date behavior fix.

Why no ready patch:

- The current hard handoff floor for `real-upstream-corpus-*` requires at least 1000 distinct focused PASS cases, 5000 behavior assertions, a blocker fix unlocking at least 2000 PASS cases / 10000 assertions, or mapped denominator movement with guarded upstream-runner evidence.
- Adding another small `date-16`/`date-20` style file would be a convenience-sized green patch and would duplicate the already accepted date-affinity sweep pattern without meeting the floor.
- Generating more cases from already accepted `date4.test` or `date3.test` loops would be overlapping PASS inflation, not new upstream behavior.

Next larger batch to try:

- Implement a lane-local deterministic localtime test-control mode for `SQLiteCoreScalarFunction` date dispatch that can model upstream `date.test` `date-6.1..6.32` and `date-18.1`, then batch it with the stable `now` per-step behavior from `date-15.1..15.2`.
- That blocker fix would make a follow-up real upstream date batch countable without relying on process timezone state and would also allow later broad admission of localtime/utc upstream rows in `date.test`.

Dependency closure:

- No new external support component is needed. The missing piece is lane-local PHP date/time dispatcher state: a deterministic localtime fault-mode shim and per-step `now` snapshot support.

Verification:

- No PHP files changed.
- `git diff --check -- lanes/libsqlite`
