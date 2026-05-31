# real-upstream-corpus-date-affinity-dynamic-20260531T053705Z-0

## Scope

- Base accepted HEAD: `4492e9529d6540daf2941a27323f36260b8cf64c`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`.
- Ported upstream scenario: `date4.test` loop `for {set i 0} {$i<=24858} {incr i}` using `SELECT strftime($::FMT,$::TS,'unixepoch');`.
- Owned non-overlapping range: `date4-20400` through `date4-24858`.
- Avoided overlap: current lane status already records accepted `date4` rows `19400-20399`, and older local corpus files cover earlier date/date2/date3/date4/date5 and expression-affinity behavior.

## Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows20400To24858Test.php`.
- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows20400To24858Test.php`.
- Result: `1 test files, 31228 assertions, 0 failures`.
- PASS-line delta: `4463` focused TestRunner PASS cases.
- Local selected movement recorded in `lane-status.json`: `2323745` to `2328208` pass / `0` fail.

## Dependency Closure

No new support component is needed. The slice reuses existing `SQLiteCoreScalarFunction` `strftime` / `unixepoch` dispatch and validates integer, text, and real timestamp affinity inputs against the real upstream `date4.test` loop tail.

## Next

The `date4.test` dynamic loop tail is now covered through `24858`. Remaining date-affinity follow-up should move to a distinct upstream file or a named known-red date cast-affinity blocker, not another `date4` row-range split.
