# real-upstream-corpus-date-affinity-dynamic-20260530T193632Z-0 blocker

Base accepted HEAD: `28f29f1b7137ae1bf099a6bea9838aec79fed0b3`.

Attempted upstream domain: hydrated SQLite date/affinity corpus under
`/home/claude/port-libs/.upstream-cache/libsqlite/test`, specifically:

- `date.test`
- `date2.test`
- `date3.test`
- `date4.test`
- `date5.test`
- `affinity2.test`
- `affinity3.test`

Current-base inspection found the high-yield real upstream date/affinity
surfaces already present in accepted lane tests:

- `SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php` covers broad
  `date.test` Julian day, datetime modifier, timezone, `date5.test`
  Gregorian-cycle, and `date4.test` strftime parity cases.
- `SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php` covers the
  `date.test` fractional unixepoch millisecond loop plus `affinity2.test` and
  `affinity3.test` storage/comparison behavior.
- `SQLiteRealUpstreamDate3AutoBoundaryDynamicCorpusTest.php` covers
  `date3.test` unixepoch identity, auto-boundary, modifier-placement, first
  63-days ambiguity, and `date.test` floating-point boundary cases.
- `SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php` covers `date.test`
  negative years, fractional modifiers, range overflows, and start-of
  boundary behavior.
- Additional accepted files cover `date2.test` deterministic schema guards,
  date floor/ceiling behavior, fractional unixepoch, strftime/date4, timediff,
  date5 Gregorian cycles, and modifier batches.

Focused evidence on this base:

```text
php tools/run-tests.php \
  lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php \
  lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php \
  lanes/libsqlite/tests/SQLiteRealUpstreamDate3AutoBoundaryDynamicCorpusTest.php \
  lanes/libsqlite/tests/SQLiteRealUpstreamDateBoundaryDynamicCorpusTest.php

4 test files, 39965 assertions, 0 failures
```

Why this slice is blocked:

- The current micro-slice cannot add a non-overlapping ready patch that meets
  the hard real-corpus floor of at least 1,000 distinct focused PASS cases or
  5,000 behavior assertions without duplicating already accepted date/affinity
  loops.
- The remaining visible upstream `date.test` gap is the `localtime`/`utc`
  section using `SQLITE_TESTCTRL_LOCALTIME_FAULT` semantics. That is not a
  large high-yield corpus batch; it needs a bounded implementation decision for
  deterministic test-control localtime emulation before it can be safely
  counted.

Next larger batch to try:

- Start a dedicated `real-upstream-corpus-date-localtime-utc-*` slice that
  implements and tests SQLite's deterministic localtime fault model from
  `date.test` date-6.1 through date-6.32, including the explicit UTC/no-op
  cases. Count it as a blocker-removal slice only if it is paired with another
  uncovered date/time section or unlocks a broader upstream runner admission
  batch.
- Otherwise pivot new throughput workers to another real upstream domain with
  uncopied high-yield rows, rather than adding more date/affinity metadata or
  duplicate strftime/Julian-day loops.

Dependency-closure note: no new support component is needed for the already
accepted date/affinity coverage. The blocked localtime section would require a
small lane-local deterministic time-zone/test-control shim, not an external
dependency.
