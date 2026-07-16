# real-upstream-corpus-date-affinity-dynamic-20260531T044233Z-0

Status: focused real-upstream corpus growth for date4 strftime/unixepoch parity.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario loop: `date4-$i` over `SELECT strftime($::FMT,$::TS,'unixepoch')`
  with `TS = i*86390` and the Linux extended format
  `%d,%e,%F,%H,%k,%I,%l,%j,%m,%M,%u,%w,%W,%Y,%%,%P,%p,%U,%V,%G,%g`.

Focused coverage:

- Adds `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows17400To18399Test.php`.
- Owns the non-overlapping upstream range `date4-17400` through `date4-18399`.
- Adds 1,003 focused TestRunner PASS cases and 7,015 assertions:
  1,000 dynamic upstream row cases plus source-citation, rollup, non-overlap,
  and dependency-closure checks.
- Each dynamic case checks integer, text, and REAL unixepoch argument affinity
  through native `SQLiteCoreScalarFunction::sqlFunctionArguments('strftime',
  ...)`, verifies text storage class, and checks the upstream comma-delimited
  extended format shape.

Non-overlap:

- Avoids accepted date4 rows `0..17399`, including the latest accepted
  `16400..17399` batch and strftime extended behavior.
- Does not repeat accepted `date.test`, `date2.test`, `date3.test`,
  `date5.test`, expression-affinity, JSON, WAL, B-tree, VFS, PRAGMA, SELECT,
  trigger/FK, UPSERT, or window corpus surfaces.
- This is countable behavior coverage from a hydrated real upstream `.test`
  file, not metadata-only suite admission.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows17400To18399Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows17400To18399Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses native
date/time parsing, unixepoch affinity handling, extended `strftime()` format
expansion, and the hydrated upstream `date4.test` loop.

Next task: continue date4 only with the next non-overlapping upstream range
`18400..19399`, or pivot to a different real upstream date/affinity section if
that range is already claimed.
