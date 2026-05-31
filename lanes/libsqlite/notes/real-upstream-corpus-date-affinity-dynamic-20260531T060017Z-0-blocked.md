# real-upstream-corpus-date-affinity-dynamic-20260531T060017Z-0 blocked

Slice: `real-upstream-corpus-date-affinity-dynamic-20260531T060017Z-0`

Base accepted HEAD: `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`

Attempted upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/affinity3.test`

Blocker:

The current accepted tree already contains broad, non-overlapping real upstream
date/affinity coverage for the high-yield parts of this domain. In particular:

- `date4.test` dynamic strftime rows are already split across focused files
  through `date4` row `24858`, which covers the generated upstream loop.
- `date5.test` calendar/Julian-day roundtrip and Gregorian cycle behavior is
  already covered by dedicated date5 files.
- `date2.test` deterministic expression-index rows, modifier index rows,
  nondeterministic `now`/`localtime`/`utc` guards, date2 range rows, and schema
  determinism checks are already covered.
- `date3.test` unixepoch/auto/modifier placement and boundary behavior is
  already covered.
- `date.test` fractional unixepoch, timezone/UTC suffix, localtime-chain,
  floor/ceiling, component validation, leading-zero strftime, invalid
  strftime, and statement-now style behavior are already covered.
- `affinity2.test` and `affinity3.test` are already covered by date-affinity
  and expression-affinity dynamic batches, including storage classes,
  comparison affinity, unary text/blob numeric coercion, REAL view/join
  affinity, and automatic-index text-id preservation.

Because this is a `real-upstream-corpus-*` throughput slice, a ready handoff
must either add at least 1,000 distinct TestRunner PASS cases, add at least
5,000 behavior assertions, fix a blocker that unlocks at least 2,000 PASS cases
or 10,000 assertions, or move real mapped denominator coverage with guarded
runner evidence. The inspected date/affinity upstream surface does not offer a
fresh non-overlapping batch in this worktree that can honestly meet that floor.
A small new file would overlap existing accepted rows and would be rejected as
metadata/PASS inflation.

Suggested next larger batch:

Pivot out of this saturated date/affinity subdomain to one of the currently
known-red broad diagnostic clusters named in `lanes/libsqlite/lane-status.json`,
especially date cast-affinity if it is assigned as a behavior-fix slice rather
than a corpus-growth slice. A valid follow-up should first reproduce the
specific failing broad diagnostic file, fix the shared date/affinity behavior,
and then rerun enough adjacent date/affinity focused tests to prove it unlocks
the required PASS/assertion volume.

Dependency closure:

No new support component was needed for this audit. The blocked decision reuses
the hydrated SQLite upstream checkout and existing lane-local
`SQLiteCoreScalarFunction`, date/time, SELECT, and affinity comparison coverage.
