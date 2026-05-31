# real-upstream-corpus-date-affinity-dynamic-20260531T050534Z-0

Base accepted HEAD: `7d59ee97325649cafd2449deb321f30571bf474f`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: `date4.test` loop `for {set i 0} {$i<=24858} {incr i}` executing `SELECT strftime($::FMT,$::TS,'unixepoch');`.

## Non-overlap

- Existing accepted local file `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDate20260531T002307ZTest.php` owns upstream `date4.test` rows `1300..2299`.
- This slice owns upstream `date4.test` rows `2300..3299` only.
- Countable movement is focused PHP TestRunner PASS-line growth from real upstream behavior. No mapped denominator movement; mapped inventory is already complete at `1589 / 1589`.

## Coverage

- Adds 1000 distinct upstream dynamic behavior cases plus source-citation and rollup checks.
- Each behavior case verifies `strftime()` libc-style format parity for a unique Unix timestamp, text storage-class output, comma-field shape, numeric-string `unixepoch` affinity parity, and date-prefix stability.
- Dependency closure: no new support component is needed; this reuses existing native `SQLiteCoreScalarFunction::dateTime()` / `strftime()` behavior and the hydrated upstream SQLite checkout as source truth.
