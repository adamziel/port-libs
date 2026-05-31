# real-upstream-corpus-date-affinity-dynamic-20260531T011040Z-0

Base accepted HEAD: `87abcd98ff24a32f5554f16930fc2af1462cc57c`.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Owned section: `date4.test` loop `date4-04300` through `date4-05299`.
- Non-overlap: earlier accepted date-affinity coverage already owns `date4.test` rows `0..4299` plus `date.test`, `date2.test`, `date3.test`, and `date5.test` clusters. This slice starts at row `4300` and ends at row `5299`.

## Behavior

The new PHP corpus file checks `strftime($FMT, $timestamp, 'unixepoch')` for the upstream `date4.test` libc-format matrix over 1,000 distinct timestamps. Each row also checks text-affinity timestamp input, returned storage type, delimiter shape, and stable leading/trailing formatted fragments. The generic rollup models application retention keys without domain-specific SQLite API names.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows4300To5299Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows4300To5299Test.php`
  - Result: `1 test files, 6014 assertions, 0 failures`
  - PASS lines: `1003`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP date/time scalar implementation and the hydrated upstream SQLite test cache as source truth.

## Next

If more date4 burn-down is desired, continue with `date4.test` rows `5300..6299`. Otherwise prioritize a release/all-runner blocker or a behavior cluster with higher functional risk.
