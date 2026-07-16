# Real upstream corpus date affinity dynamic 20260531T025431Z

Added `SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows11300To12299Test.php` as an additive real upstream `date4.test` continuation batch.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/date4.test`
- Scenario: `date4-$i` loop over `SELECT strftime($::FMT,$::TS,'unixepoch')`.
- Owned section: `date4-11300` through `date4-12299`.

## Focused coverage

- 1,000 real upstream row cases for `strftime($FMT, i*86390, 'unixepoch')`.
- Each row checks integer and text-affinity timestamp inputs, text return storage, delimiter count, and stable prefix/suffix fragments against the computed UTC libc-style expectation.
- 3 additional focused PASS cases cite the upstream loop, assert a generic application retention rollup, and record the non-overlapping owned range.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows11300To12299Test.php`
  - `1 test files, 6014 assertions, 0 failures`
  - `1003` focused PASS lines
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusDateAffinityDynamicDate4Rows11300To12299Test.php`
  - `No syntax errors detected`
- `git diff --check -- lanes/libsqlite`
  - passed

## Non-overlap

This follows accepted date4 coverage through `date4-11299` and owns only rows `11300..12299`. It does not repeat accepted `date.test`, `date2.test`, `date3.test`, `date5.test`, expression-affinity, JSON, WAL, VFS, B-tree, trigger, window, PRAGMA, source-neutral cleanup, or metadata-only runner surfaces.

## Dependency closure

No new support component is needed. The slice reuses the existing native `SQLiteCoreScalarFunction::strftime` implementation against hydrated upstream `date4.test` behavior.
