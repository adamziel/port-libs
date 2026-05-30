# real-upstream-corpus-date-affinity-dynamic-20260530T225518Z-0

Base accepted HEAD: `6e94a67dd020b9cfec1567bd7fbc6ebe5e036bda`.

Added focused real-upstream coverage for SQLite upstream
`test/date2.test` scenario `date2-500`, the deterministic
`datetime(y,m) IS NOT NULL` partial-index modifier table. This slice owns
the non-overlapping continuation range `rowid 69..128` across the 17
upstream modifier strings:

`+10 days`, `-10 days`, `+10 hours`, `-10 hours`, `+10 minutes`,
`-10 minutes`, `+10 seconds`, `-10 seconds`, `+10 months`,
`-10 months`, `+10 years`, `-10 years`, `start of month`,
`start of year`, `start of day`, `weekday 1`, and `unixepoch`.

The new PHP test exercises `SQLiteCoreScalarFunction` date, time,
datetime, `typeof`, and deterministic-function classification behavior
for REAL Julian-day inputs. It is intentionally separate from the
existing `rowid 5..68` date2 modifier-row file.

Focused evidence:

`php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamDateAffinityDynamicDate2ModifierRowsContinuationTest.php`

Result: `1 test files, 6124 assertions, 0 failures`; 1021 focused
TestRunner PASS cases.

Dependency closure: no new support component is needed. The slice reuses
the existing native PHP scalar date/time function implementation and the
hydrated upstream SQLite checkout only as source truth.
