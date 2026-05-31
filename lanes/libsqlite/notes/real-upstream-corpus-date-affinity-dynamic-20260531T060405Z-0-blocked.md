# real-upstream-corpus-date-affinity-dynamic-20260531T060405Z-0

Status: blocked by accepted-base overlap; no behavior patch emitted.

Assigned upstream domain:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date2.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date5.test`
- Affinity-adjacent upstream files checked in the current base: `affinity2.test`, `affinity3.test`, `types2.test`, and `types3.test`.

Overlap found on accepted base `5a0bbcc53e4d53b976a73e07fed57fd92e934f80`:

- `date4.test` is fully exhausted by existing focused files, including `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows20400To24858Test.php`. The upstream loop runs `date4-0` through `date4-24858`; the current base already owns the final `20400..24858` range.
- `date.test` high-yield areas are already covered by focused files for fractional unixepoch milliseconds, weekday and modifier validation, UTC suffixes and timezone offsets, deterministic localtime/UTC chains, localtime failure propagation, NULL propagation, Julian and range boundaries, `subsec`/`subsecond`, floor/ceiling month/year ambiguity, strftime extended formats, invalid strftime conversions, leading-zero formatting, component validation, and date-20 fractional truncation.
- `date2.test` deterministic CHECK/index/generated-column guards, expression-index rows, modifier-index rows, `localtime`/`utc` schema rejection, and `julianday('now')` nondeterminism are already covered by existing focused date2 files.
- `date3.test` unixepoch, auto-boundary, modifier-placement, and the generated `date3-1.7` unixepoch roundtrip loop are already covered.
- `date5.test` Gregorian calendar and Julian-day roundtrip behavior is already covered by exact and bulk cycle files.
- Affinity-adjacent behavior is already represented by current expression-affinity, `types2`, `types3`, `affinity2`, and `affinity3` dynamic corpus files.

Why this handoff is blocked:

The current hard floor for `real-upstream-corpus-*` requires at least 1,000 distinct focused PASS cases, 5,000 behavior assertions, a named behavior/tooling blocker that unlocks at least 2,000 PASS cases or 10,000 assertions, or guarded denominator movement. On this accepted base, the assigned `date-affinity-dynamic` corpus does not expose a non-overlapping section large enough to satisfy that floor. Adding another date4 range, date20 truncation variant, localtime chain variant, or metadata-only source citation would duplicate accepted behavior and inflate marker volume.

Next larger batch to try:

- Pivot the next real corpus worker to a known-red broad diagnostic cluster listed in `lanes/libsqlite/lane-status.json`, especially the remaining date cast-affinity diagnostic if it is still red under the current accepted source.
- If date-affinity remains the required domain, first reproduce the broad diagnostic failure and fix the shared date/affinity behavior. A valid follow-up should name the failing focused command, show the fixed command, and prove it unlocks at least the hard-floor volume in adjacent date/affinity files.

Dependency closure:

No new external support component is needed for this blocked overlap audit. A future accepted date-affinity fix should reuse the native PHP date/time dispatcher and existing affinity comparison helpers unless the reproduced broad diagnostic identifies a concrete missing lane-local primitive.
