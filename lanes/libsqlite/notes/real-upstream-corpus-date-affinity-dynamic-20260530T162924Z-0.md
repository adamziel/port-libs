# real-upstream-corpus-date-affinity-dynamic-20260530T162924Z-0

Base accepted HEAD: `72e7cdb1ae891bd4c5cdf5658524a5a35974f525`.

## Upstream Source

- Hydrated upstream checkout: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Ported scenarios: `date4-0` through `date4-511`
- Behavior: `strftime()` parity for UTC unixepoch timestamps spaced by `86390` seconds, matching upstream's libc-format comparison surface for `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.

## Delta

- Focused date corpus before this edit: `1 test files / 88 assertions / 0 failures`
- Focused date corpus after this edit: `1 test files / 600 assertions / 0 failures`
- Focused assertion delta: `+512`
- Public PASS estimate delta in `lane-status.json`: `188568 -> 189080`
- Mapped denominator delta: `0`; this is real upstream behavior coverage, not a new denominator row claim.

## Non-Overlap

This extends the existing real date corpus beyond the accepted `date.test` scalar cases by taking the looped `date4.test` libc strftime parity rows. It does not touch VFS, window, suite-evidence, source-neutral API cleanup, static metadata admission, generated fake `.test` names, or domain-shaped API/smoke paths.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `SQLiteCoreScalarFunction::sqlFunctionArguments('strftime', ...)` implementation and PHP UTC `DateTimeImmutable` only for test expected-value construction.
