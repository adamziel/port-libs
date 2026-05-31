# real-upstream-corpus-date-affinity-dynamic-20260531T012442Z-0

Base accepted HEAD: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Section: `date4-$i` loop, `for {set i 0} {$i<=24858} {incr i}`, with
  `TS = i*86390` and `SELECT strftime($::FMT,$::TS,'unixepoch')`.

Behavior added:

- Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows5300To6299Test.php`.
- Expands real upstream `date4.test` rows `date4-5300` through `date4-6299`.
- Each row asserts SQLite-port `strftime()` parity for integer and text
  unixepoch arguments, output storage class, comma-separated libc field shape,
  and ISO-week/year suffix stability.

Non-overlap:

- This owns only upstream `date4.test` rows `5300..6299`.
- It avoids accepted date4 rows `0..5299`, date/date2/date3/date5 modifier and
  calendar batches, expression-affinity batches, and all source-neutral API
  cleanup work.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is
  selected PASS-line growth only.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  `SQLiteCoreScalarFunction` date/time behavior and the existing upstream
  source-citation pattern used by neighboring date-affinity tests.

Expected dashboard movement:

- Count as PASS-line growth only: `1003` focused TestRunner PASS cases if
  accepted.
- `lane-status.json` selected `phpPass` is updated from `1493978` to
  `1494981`.
