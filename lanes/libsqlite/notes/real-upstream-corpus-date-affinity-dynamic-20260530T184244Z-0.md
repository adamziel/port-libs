# real-upstream-corpus-date-affinity-dynamic-20260530T184244Z-0

Base accepted HEAD: `3b5859ae04f0cdc4e296cdcbe93d14e8b284a829`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date3.test`
  - `date3-1.7.1..100`: generated unixepoch identity loop.
  - `date3-2.1..2.30`: `auto`, `unixepoch`, and `julianday` modifier boundary behavior.
  - `date3-2.40`: mixed text, Julian day, and unix timestamp row values under `auto`.
  - `date3-3.1..4.3`: placement rules for `unixepoch` and `julianday` modifiers.
  - `date3-5.0`: first 63 days of 1970 remain ambiguous under `auto`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date.test`
  - `date-14.2.0..255`: floating-point boundary values never render `24:00:00`.

## Behavior

- Adds `SQLiteRealUpstreamDate3AutoBoundaryDynamicCorpusTest.php` with 377 focused PASS cases and 5,038 behavior assertions.
- Fixes `SQLiteCoreScalarFunction::dateTimeFromJulianDay()` to derive the instant through the equivalent Unix timestamp instead of the older split Julian-calendar conversion. This fixes the upstream `date3-2.4` boundary where `2440587.49998843` must render `1969-12-31 23:59:59`, not one second earlier.
- The application scenario uses generic `app_events`-style expiry boundaries and no domain-specific API names.

## Non-overlap

This slice does not repeat the accepted date4/date5 bulk strftime/Gregorian-cycle corpus, date2 deterministic schema guard corpus, date2 generated modifier/range corpus, or affinity2/affinity3 storage/comparison corpus. It claims focused PASS-line growth and behavior assertions only; mapped denominator coverage remains unchanged.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate3AutoBoundaryDynamicCorpusTest.php`
  - `1 test files, 5038 assertions, 0 failures`
  - `377` focused PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDate3AutoBoundaryDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityModifierDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicNextCorpusTest.php`
  - `4 test files, 38494 assertions, 0 failures`
- PHP lint and `git diff --check -- lanes/libsqlite` passed in the final handoff.

## Dependency Closure

No new support component is needed. The slice reuses `SQLiteCoreScalarFunction` date/time parsing and tightens the existing Julian-day conversion.
