# real-upstream-corpus-date-affinity-dynamic-20260531T010039Z-0

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDateContinuation20260531T010039ZTest.php` as an additive real upstream `date4.test` continuation batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: `date4-3300` through `date4-4299`
- Tcl loop: `for {set i 0} {$i<=24858} {incr i}` with `TS = i*86390`
- SQL scenario: `SELECT strftime($::FMT,$::TS,'unixepoch');`

Focused coverage:

- 1 source-truth citation test.
- 1,000 real upstream row tests for the non-overlapping `date4-3300..4299` range.
- 1 generic application audit-rollup test over sampled retention-window timestamps.
- 5,006 focused behavior assertions.
- 1,002 focused TestRunner PASS lines.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDateContinuation20260531T010039ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDateContinuation20260531T010039ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4RealDateContinuation20260531T010039ZTest.php`
  - `1 test files, 5006 assertions, 0 failures`

Non-overlap:

- Existing accepted date4 continuation coverage in this worktree reaches `date4-3299`.
- This slice owns only the next real upstream range, `date4-3300` through `date4-4299`.
- It does not repeat accepted `date.test`, `date2.test`, `date3.test`, `date5.test`, expression-affinity, cast, JSON, WAL, VFS, B-tree, trigger, window, or suite-evidence clusters.
- No generated fake upstream script ids, metadata-only admission rows, domain-specific API names, or compatibility wrappers were added.

Dependency closure:

- No new support component is needed.
- The slice reuses existing native PHP `SQLiteCoreScalarFunction::sqlFunctionArguments()` date/time and `strftime()` dispatch.
